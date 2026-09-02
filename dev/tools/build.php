<?php
/**
 * Build the application package from a local IN_DEV Invision Community install.
 *
 * Does exactly what AdminCP > Developer Center > Build does: regenerates the
 * data/*.xml and setup/upg_<version>/ files from dev/, records the version, and
 * writes a .tar that AdminCP > Applications > Upload accepts.
 *
 *   php build.php <ips-root> <app-directory> <output.tar>
 *
 * Run it with the same PHP configuration as the web install (php.ini with
 * mysqli, mbstring, gd, curl ...). MySQL must be running. Normally invoked by
 * dev/tools/deploy.ps1.
 *
 * @package		Mark As Sold
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

[ , $root, $appDir, $out ] = $argv + [ NULL, NULL, NULL, NULL ];
if ( !$root or !$appDir or !$out )
{
	fwrite( STDERR, "Usage: php build.php <ips-root> <app-directory> <output.tar>\n" );
	exit( 2 );
}

$root = rtrim( str_replace( '\\', '/', $root ), '/' );
$out  = str_replace( '\\', '/', $out );
if ( !is_file( "{$root}/init.php" ) )
{
	fwrite( STDERR, "init.php not found under {$root}\n" );
	exit( 2 );
}

require_once "{$root}/init.php";

if ( !\IPS\IN_DEV )
{
	fwrite( STDERR, "The install at {$root} is not in developer mode (IN_DEV); packages can only be built from a dev install.\n" );
	exit( 2 );
}

try
{
	$app = \IPS\Application::load( $appDir );

	/* Same as the Developer Center "Build" button */
	$app->build();

	/* Same as the Developer Center "Download" button */
	if ( file_exists( $out ) )
	{
		unlink( $out );
	}
	$phar = new \PharData( $out, 0, basename( $out ), \Phar::TAR );
	$phar->buildFromIterator( new \IPS\Application\BuilderIterator( $app ), "{$root}/applications/{$app->directory}/" );

	echo "Built {$app->directory} {$app->version} ({$app->long_version}) -> {$out}\n";
	exit( 0 );
}
catch ( \Throwable $e )
{
	fwrite( STDERR, get_class( $e ) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n" );
	exit( 1 );
}
