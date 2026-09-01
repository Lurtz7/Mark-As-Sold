<?php
/**
 * Runs every *Test.php in this directory. Exit code 1 on any failure.
 *
 *   php dev/tests/run.php
 *
 * On a CLI without a php.ini (e.g. Laragon's bundled PHP) enable mbstring:
 *   php -d extension_dir=<php>/ext -d extension=mbstring dev/tests/run.php
 * The ini flags are forwarded to each test process.
 */

declare( strict_types=1 );

/* CLI only: on an IN_DEV install the dev/ folder is web-reachable, and this file spawns PHP processes */
if ( PHP_SAPI !== 'cli' )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

$flags =' -d ' . escapeshellarg( 'extension_dir=' . ini_get( 'extension_dir' ) );
if ( extension_loaded( 'mbstring' ) )
{
	$flags .= ' -d extension=mbstring';
}

$exit = 0;
foreach ( glob( __DIR__ . '/*Test.php' ) as $file )
{
	echo basename( $file ) . "\n";
	passthru( escapeshellarg( PHP_BINARY ) . $flags . ' ' . escapeshellarg( $file ), $code );
	if ( $code !== 0 )
	{
		$exit = 1;
	}
	echo "\n";
}
exit( $exit );
