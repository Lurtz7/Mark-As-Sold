<?php
/**
 * Run the AdminCP "upgrade application" routine from the command line, after the
 * package files have been extracted over applications/<app>.
 *
 * Mirrors the steps in applications/core/modules/admin/applications/applications.php
 * (upgrade(): database queries -> db -> basics -> lang -> cmstemplates, then cache clear),
 * so that new tables, settings rows, language strings, templates and the recorded version
 * are installed, which a plain file copy never does.
 *
 *   php remote-upgrade.php <ips-root> <app-directory> [--from=<long version>]
 *
 * --from overrides the installed version recorded in the database. deploy.ps1 uses it for the
 * local rehearsal, because Application::build() has already recorded the new version there.
 *
 * Run as the web server user (e.g. "sudo -u www-data php ...") so that datastore and
 * cache files keep the right owner. Works on the local dev install too, which is how
 * deploy.ps1 rehearses it before touching production.
 *
 * Runs the queries.json of every setup/upg_<version> newer than the installed version
 * (createTable is skipped when the table already exists). Refuses if such a version
 * ships an upgrade.php with custom routines; those need the AdminCP upgrader.
 *
 * @package		Mark As Sold
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

if ( PHP_VERSION_ID < 80100 )
{
	fwrite( STDERR, "PHP 8.1 or newer is required, this is " . PHP_VERSION . "\n" );
	exit( 2 );
}

$from = NULL;
$args = array_values( array_filter( array_slice( $argv, 1 ), function( $a ) use ( &$from ) {
	if ( preg_match( '/^--from=(\d+)$/', $a, $m ) )
	{
		$from = (int) $m[1];
		return FALSE;
	}
	return TRUE;
} ) );
[ $root, $appDir ] = $args + [ NULL, NULL ];
if ( !$root or !$appDir )
{
	fwrite( STDERR, "Usage: php remote-upgrade.php <ips-root> <app-directory> [--from=<long version>]\n" );
	exit( 2 );
}

$root = rtrim( str_replace( '\\', '/', $root ), '/' );
if ( !is_file( "{$root}/init.php" ) )
{
	fwrite( STDERR, "init.php not found under {$root}\n" );
	exit( 2 );
}

require_once "{$root}/init.php";

try
{
	$app       = \IPS\Application::load( $appDir );
	$appPath   = "{$root}/applications/{$appDir}";
	$installed = ( $from !== NULL ) ? $from : (int) $app->long_version;
	$versions  = $app->getAllVersions();
	$latest    = (int) array_key_last( $versions );

	echo "Installed: {$app->version} ({$installed}" . ( $from !== NULL ? ', from --from' : '' ) . "); package on disk: {$versions[ $latest ]} ({$latest})\n";

	/* Versions newer than the installed one, lowest first */
	$pending = $app->getUpgradeSteps( $installed );

	/* Custom upgrade routines are not run here: check BEFORE changing anything */
	foreach ( $pending as $version )
	{
		if ( file_exists( "{$appPath}/setup/upg_{$version}/upgrade.php" ) )
		{
			fwrite( STDERR, "setup/upg_{$version}/upgrade.php contains custom upgrade routines. Upload the package through AdminCP > System > Applications instead.\n" );
			exit( 3 );
		}
	}

	/* Database changes (same instruction format Application::installDatabaseUpdates() executes) */
	foreach ( $pending as $version )
	{
		$queriesFile = "{$appPath}/setup/upg_{$version}/queries.json";
		if ( !file_exists( $queriesFile ) )
		{
			continue;
		}
		$instructions = json_decode( file_get_contents( $queriesFile ), TRUE );
		if ( !is_array( $instructions ) )
		{
			throw new \RuntimeException( "Cannot parse {$queriesFile}" );
		}
		ksort( $instructions, SORT_NUMERIC );
		foreach ( $instructions as $index => $instruction )
		{
			$method = (string) ( $instruction['method'] ?? '' );
			$params = (array) ( $instruction['params'] ?? array() );
			if ( $method === 'createTable' and isset( $params[0]['name'] ) and \IPS\Db::i()->checkForTable( $params[0]['name'] ) )
			{
				echo "upg_{$version} #{$index}: table {$params[0]['name']} already exists, skipped\n";
				continue;
			}
			try
			{
				\IPS\Db::i()->$method( ...$params );
				echo "upg_{$version} #{$index}: {$method} done\n";
			}
			catch ( \IPS\Db\Exception $e )
			{
				/* Same tolerance as Application::installDatabaseUpdates(): re-running after a half-finished deploy must not get stuck */
				$alreadyApplied = array( 1007, 1050, 1060, 1061, 1091 );	/* db exists, table exists, duplicate column, duplicate key, can't drop (missing) */
				if ( in_array( $e->getCode(), $alreadyApplied ) )
				{
					echo "upg_{$version} #{$index}: {$method} already applied ({$e->getCode()}), skipped\n";
					continue;
				}
				throw $e;
			}
		}
	}

	/* 'db': modules, tasks, settings, widgets, search keywords, recorded version */
	$app->installJsonData();
	echo "Rebuilt modules, tasks, settings, widgets and search keywords\n";

	/* 'basics': language strings from data/lang.xml */
	$app->installLanguages();
	echo "Installed language strings\n";

	/* 'lang': email templates */
	$app->installEmailTemplates();

	/* 'cmstemplates': theme templates/resources and javascript */
	$app->installSkins( TRUE );
	$app->installJavascript();
	echo "Installed templates and javascript\n";

	foreach ( $pending as $version )
	{
		\IPS\Db::i()->insert( 'core_upgrade_history', array(
			'upgrade_version_human'	=> $versions[ $version ] ?? (string) $version,
			'upgrade_version_id'	=> $version,
			'upgrade_date'			=> time(),
			'upgrade_mid'			=> 0,
			'upgrade_app'			=> $appDir,
		) );
	}

	/* Same cache handling as the AdminCP upgrader's final step */
	\IPS\Application::resetEditorPlugins();
	\IPS\Data\Store::i()->clearAll();
	\IPS\Data\Cache::i()->clearAll();
	echo "Caches cleared\n";

	/* Verify straight from the database (in-memory application objects still hold the old version) and fail loudly */
	$row       = \IPS\Db::i()->select( 'app_version, app_long_version', 'core_applications', array( 'app_directory=?', $appDir ) )->first();
	$settings  = iterator_to_array( \IPS\Db::i()->select( 'conf_key, conf_value', 'core_sys_conf_settings', array( 'conf_app=?', $appDir ) )->setKeyField( 'conf_key' )->setValueField( 'conf_value' ) );
	$langWords = (int) \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', array( 'word_app=? AND lang_id=?', $appDir, \IPS\Lang::defaultLanguage() ) )->first();
	$expectedSettings = count( json_decode( (string) file_get_contents( "{$appPath}/data/settings.json" ), TRUE ) ?: array() );
	$expectedTables   = array_keys( json_decode( (string) file_get_contents( "{$appPath}/data/schema.json" ), TRUE ) ?: array() );
	$problems = array();

	echo "\nNow installed: {$row['app_version']} ({$row['app_long_version']})\n";
	if ( (int) $row['app_long_version'] !== $latest )
	{
		$problems[] = "recorded version is {$row['app_long_version']}, package is {$latest}";
	}
	foreach ( $expectedTables as $table )
	{
		$present = \IPS\Db::i()->checkForTable( $table );
		echo "Table {$table} present: " . ( $present ? 'yes' : 'NO' ) . "\n";
		if ( !$present )
		{
			$problems[] = "table {$table} is missing";
		}
	}
	echo "Settings rows (" . count( $settings ) . " of {$expectedSettings}):\n";
	foreach ( $settings as $key => $value )
	{
		echo "  {$key} = " . ( $value === '' ? '(empty)' : $value ) . "\n";
	}
	if ( count( $settings ) < $expectedSettings )
	{
		$problems[] = "only " . count( $settings ) . " of {$expectedSettings} settings rows exist";
	}
	echo "Language strings in default language: {$langWords}\n";
	if ( $langWords === 0 )
	{
		$problems[] = 'no language strings installed';
	}

	if ( count( $problems ) )
	{
		fwrite( STDERR, "UPGRADE INCOMPLETE: " . implode( '; ', $problems ) . "\n" );
		exit( 1 );
	}
	echo "Upgrade verified\n";
	exit( 0 );
}
catch ( \Throwable $e )
{
	fwrite( STDERR, get_class( $e ) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n" );
	exit( 1 );
}
