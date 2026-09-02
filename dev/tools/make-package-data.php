<?php
/**
 * Generate the package data files that AdminCP > Developer Center > Build would create,
 * without an IPS install or database:
 *
 *   data/lang.xml        from dev/lang.php (and dev/jslang.php if present)
 *   data/theme.xml       from dev/css/<location>/*.css
 *   data/javascript.xml  empty stub (this app ships no javascript)
 *   data/emails.xml      empty stub (this app ships no email templates)
 *
 * These are exactly the files the AdminCP upgrade routine reads (Application::installLanguages,
 * installSkins, installJavascript, installEmailTemplates). Templates (dev/html) are not
 * supported by this generator; use the full Developer Center build if the app ever gets any.
 *
 *   php make-package-data.php <repo-root> <staging-dir>
 *
 * @package		Mark As Sold
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

[ , $repo, $stage ] = $argv + [ NULL, NULL, NULL ];
if ( !$repo or !$stage or !is_dir( $repo ) or !is_dir( $stage ) )
{
	fwrite( STDERR, "Usage: php make-package-data.php <repo-root> <staging-dir>\n" );
	exit( 2 );
}
$repo  = rtrim( str_replace( '\\', '/', $repo ), '/' );
$stage = rtrim( str_replace( '\\', '/', $stage ), '/' );

if ( is_dir( "{$repo}/dev/html" ) and count( glob( "{$repo}/dev/html/*/*/*.phtml" ) ) )
{
	fwrite( STDERR, "dev/html contains templates; this generator cannot build theme templates. Use the Developer Center build.\n" );
	exit( 3 );
}

$application = json_decode( (string) file_get_contents( "{$repo}/data/application.json" ), TRUE );
$versions    = json_decode( (string) file_get_contents( "{$repo}/data/versions.json" ), TRUE );
if ( empty( $application['app_directory'] ) or !is_array( $versions ) or !count( $versions ) )
{
	fwrite( STDERR, "data/application.json or data/versions.json is missing or invalid\n" );
	exit( 3 );
}
$appDir      = $application['app_directory'];
$longVersion = (string) array_key_last( $versions );

@mkdir( "{$stage}/data", 0777, TRUE );

/* Write text as CDATA when it contains markup characters, like IPS does; plain escaped text otherwise */
function writeText( XMLWriter $xml, string $text ): void
{
	if ( preg_match( '/[<>&]/', $text ) )
	{
		$xml->writeCdata( $text );
	}
	else
	{
		$xml->text( $text );
	}
}

/* ---- lang.xml ---- */
$lang = array();
require "{$repo}/dev/lang.php";			/* defines $lang */
$jslang = array();
if ( file_exists( "{$repo}/dev/jslang.php" ) )
{
	$lang_js = $lang; $lang = array();
	require "{$repo}/dev/jslang.php";		/* defines $lang again */
	$jslang = $lang; $lang = $lang_js;
}
if ( !is_array( $lang ) or !count( $lang ) )
{
	fwrite( STDERR, "dev/lang.php did not define any strings\n" );
	exit( 3 );
}

$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent( TRUE );
$xml->setIndentString( ' ' );
$xml->startDocument( '1.0', 'UTF-8' );
$xml->startElement( 'language' );
$xml->startElement( 'app' );
$xml->writeAttribute( 'key', $appDir );
$xml->writeAttribute( 'version', $longVersion );
foreach ( array( array( $lang, '0' ), array( $jslang, '1' ) ) as [ $words, $js ] )
{
	foreach ( $words as $key => $value )
	{
		$xml->startElement( 'word' );
		$xml->writeAttribute( 'key', (string) $key );
		$xml->writeAttribute( 'js', $js );
		writeText( $xml, (string) $value );
		$xml->endElement();
	}
}
$xml->endElement();
$xml->endElement();
$xml->endDocument();
file_put_contents( "{$stage}/data/lang.xml", $xml->outputMemory() );
echo "data/lang.xml: " . count( $lang ) . " strings, " . count( $jslang ) . " js strings, version {$longVersion}\n";

/* ---- theme.xml ---- */
$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent( TRUE );
$xml->setIndentString( ' ' );
$xml->startDocument( '1.0', 'UTF-8' );
$xml->startElement( 'theme' );
$xml->writeAttribute( 'name', 'Default' );
$xml->writeAttribute( 'author_name', 'Invision Power Services, Inc' );
$xml->writeAttribute( 'author_url', 'https://www.invisioncommunity.com' );
$cssCount = 0;
foreach ( glob( "{$repo}/dev/css/*/*.css" ) ?: array() as $file )
{
	$location = basename( dirname( $file ) );
	$xml->startElement( 'css' );
	$xml->writeAttribute( 'css_location', $location );
	$xml->writeAttribute( 'css_app', $appDir );
	$xml->writeAttribute( 'css_attributes', '' );
	$xml->writeAttribute( 'css_path', '.' );
	$xml->writeAttribute( 'css_name', basename( $file ) );
	$xml->text( (string) file_get_contents( $file ) );
	$xml->endElement();
	$cssCount++;
}
$xml->endElement();
$xml->endDocument();
file_put_contents( "{$stage}/data/theme.xml", $xml->outputMemory() );
echo "data/theme.xml: {$cssCount} css files\n";

/* ---- stubs ---- */
file_put_contents( "{$stage}/data/javascript.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<javascript app=\"{$appDir}\"/>\n" );
file_put_contents( "{$stage}/data/emails.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<emails/>\n" );
echo "data/javascript.xml, data/emails.xml: empty\n";
exit( 0 );
