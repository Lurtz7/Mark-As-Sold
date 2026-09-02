<?php
/**
 * Run the AdminCP "upgrade application" routine from the command line, after the
 * package files have been extracted over applications/<app>.
 *
 * Mirrors the steps in applications/core/modules/admin/applications/applications.php
 * (upgrade(): db -> basics -> lang -> cmstemplates, then cache clear), so that new
 * settings rows, language strings, templates and the recorded version are installed,
 * which a plain file copy never does.
 *
 *   php remote-upgrade.php <ips-root> <app-directory>
 *
 * Run as the web server user (e.g. "sudo -u www-data php ...") so that datastore and
 * cache files keep the right owner. Refuses to run if the package contains database
 * upgrade steps (a queries.json or upgrade.php under setup/upg_<version>); those must
 * go through the AdminCP upgrader.
 *
 * @package		Mark As Sold
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
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
	$installed = (int) $app->long_version;
	$versions  = $app->getAllVersions();
	$latest    = (int) array_key_last( $versions );

	echo "Installed: {$app->version} ({$installed}); package on disk: {$versions[ $latest ]} ({$latest})\n";

	/* Database upgrade steps are not run here (getUpgradeSteps() lists the version numbers newer than the installed one) */
	foreach ( $app->getUpgradeSteps( $installed ) as $version )
	{
		$setupDir = "{$root}/applications/{$appDir}/setup/upg_{$version}";
		if ( file_exists( "{$setupDir}/queries.json" ) or file_exists( "{$setupDir}/upgrade.php" ) )
		{
			fwrite( STDERR, "setup/upg_{$version} contains database upgrade steps. Upload the package through AdminCP > System > Applications instead.\n" );
			exit( 3 );
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

	\IPS\Db::i()->insert( 'core_upgrade_history', array(
		'upgrade_version_human'	=> $versions[ $latest ],
		'upgrade_version_id'	=> $latest,
		'upgrade_date'			=> time(),
		'upgrade_mid'			=> 0,
		'upgrade_app'			=> $appDir,
	) );

	/* Same cache handling as the AdminCP upgrader's final step */
	\IPS\Application::resetEditorPlugins();
	\IPS\Data\Store::i()->clearAll();
	\IPS\Data\Cache::i()->clearAll();
	echo "Caches cleared\n";

	/* Verify */
	$app       = \IPS\Application::load( $appDir );
	$settings  = iterator_to_array( \IPS\Db::i()->select( 'conf_key, conf_value', 'core_sys_conf_settings', array( 'conf_app=?', $appDir ) )->setKeyField( 'conf_key' )->setValueField( 'conf_value' ) );
	$langWords = (int) \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', array( 'word_app=? AND lang_id=?', $appDir, \IPS\Lang::defaultLanguage() ) )->first();

	echo "\nNow installed: {$app->version} ({$app->long_version})\n";
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
