<?php
/**
 * Minimal test bootstrap for the pure logic in sources/TagLogic.
 *
 * Runs without an IPS installation: no database, no \IPS\Settings.
 * Usage: see run.php
 */

declare( strict_types=1 );

/* CLI only: on an IN_DEV install the dev/ folder is web-reachable */
if ( PHP_SAPI !== 'cli' )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

if ( !defined( 'IPS\SUITE_UNIQUE_KEY' ) )
{
	define( 'IPS\SUITE_UNIQUE_KEY', 'test' );
}

require_once __DIR__ . '/../../sources/TagLogic/TagLogic.php';

final class T
{
	public static int $passed = 0;
	public static int $failed = 0;
	/** @var array<string> */
	public static array $failures = [];

	public static function test( string $name, callable $fn ): void
	{
		try
		{
			$fn();
			self::$passed++;
			echo "  ok   {$name}\n";
		}
		catch ( \Throwable $e )
		{
			self::$failed++;
			self::$failures[] = "{$name}: " . $e->getMessage();
			echo "  FAIL {$name}: " . $e->getMessage() . "\n";
		}
	}

	public static function eq( mixed $expected, mixed $actual, string $msg = '' ): void
	{
		if ( $expected !== $actual )
		{
			throw new \RuntimeException( ( $msg ? $msg . ': ' : '' ) . 'expected ' . var_export( $expected, TRUE ) . ', got ' . var_export( $actual, TRUE ) );
		}
	}

	public static function summary(): int
	{
		echo "\n" . self::$passed . " passed, " . self::$failed . " failed\n";
		return self::$failed === 0 ? 0 : 1;
	}
}
