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
 *   php remote-upgrade.php <ips-root> <app-directory>
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

[ , $root, $appDir ] = $argv + [ NULL, NULL, NULL ];
if ( !$root or !$appDir )
{
	fwrite( STDERR, "Usage: php remote-upgrade.php <ips-root> <app-directory>\n" );
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
	$installed = (int) $app->long_version;
	$versions  = $app->getAllVersions();
	$latest    = (int) array_key_last( $versions );

	echo "Installed: {$app->version} ({$installed}); package on disk: {$versions[ $latest ]} ({$latest})\n";

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
			\IPS\Db::i()->$method( ...$params );
			echo "upg_{$version} #{$index}: {$method} done\n";
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

	/* Verify straight from the database (in-memory application objects still hold the old version) */
	$row       = \IPS\Db::i()->select( 'app_version, app_long_version', 'core_applications', array( 'app_directory=?', $appDir ) )->first();
	$settings  = iterator_to_array( \IPS\Db::i()->select( 'conf_key, conf_value', 'core_sys_conf_settings', array( 'conf_app=?', $appDir ) )->setKeyField( 'conf_key' )->setValueField( 'conf_value' ) );
	$langWords = (int) \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', array( 'word_app=? AND lang_id=?', $appDir, \IPS\Lang::defaultLanguage() ) )->first();
	$hasLocks  = \IPS\Db::i()->checkForTable( 'markassold_locks' ) ? 'yes' : 'NO';

	echo "\nNow installed: {$row['app_version']} ({$row['app_long_version']})\n";
	echo "Table markassold_locks present: {$hasLocks}\n";
	echo "Settings rows (" . count( $settings ) . "):\n";
	foreach ( $settings as $key => $value )
	{
		echo "  {$key} = " . ( $value === '' ? '(empty)' : $value ) . "\n";
	}
	echo "Language strings in default language: {$langWords}\n";
	exit( 0 );
}
catch ( \Throwable $e )
{
	fwrite( STDERR, get_class( $e ) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n" );
	exit( 1 );
}
