<?php
/**
 * Tests for \IPS\markassold\TagLogic (pure decision logic, no IPS runtime).
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

require_once __DIR__ . '/bootstrap.php';

use IPS\markassold\TagLogic;

$configs = [
	[ 'slot' => 1, 'tag' => 'Såld',       'forums' => '3,7',  'autolock' => TRUE,  'bg_color' => '#e74c3c', 'text_color' => '#ffffff' ],
	[ 'slot' => 2, 'tag' => 'Reserverad', 'forums' => '3',    'autolock' => TRUE,  'bg_color' => '#27ae60', 'text_color' => '#ffffff' ],
	[ 'slot' => 3, 'tag' => 'Bytt',       'forums' => '3',    'autolock' => FALSE, 'bg_color' => '#000000', 'text_color' => '#ffffff' ],
];

echo "requestTagName\n";
T::test( 'trims a string', fn() => T::eq( 'Såld', TagLogic::requestTagName( '  Såld ' ) ) );
T::test( 'array input becomes empty string', fn() => T::eq( '', TagLogic::requestTagName( [ 'x' ] ) ) );
T::test( 'null becomes empty string', fn() => T::eq( '', TagLogic::requestTagName( NULL ) ) );
T::test( 'int becomes empty string', fn() => T::eq( '', TagLogic::requestTagName( 5 ) ) );

echo "parseForumIds\n";
T::test( 'parses comma list', fn() => T::eq( [ 1, 2, 3 ], TagLogic::parseForumIds( '1,2,3' ) ) );
T::test( 'empty string gives no ids', fn() => T::eq( [], TagLogic::parseForumIds( '' ) ) );
T::test( 'ignores junk and zero', fn() => T::eq( [ 4 ], TagLogic::parseForumIds( ' 4 , x, 0' ) ) );

echo "configsForForum\n";
T::test( 'returns every config listing the forum', fn() => T::eq( [ 1, 2, 3 ], array_column( TagLogic::configsForForum( $configs, 3 ), 'slot' ) ) );
T::test( 'returns only matching configs', fn() => T::eq( [ 1 ], array_column( TagLogic::configsForForum( $configs, 7 ), 'slot' ) ) );
T::test( 'returns nothing for an unconfigured forum', fn() => T::eq( [], TagLogic::configsForForum( $configs, 99 ) ) );

echo "findConfig\n";
T::test( 'matches tag case-insensitively', fn() => T::eq( 1, TagLogic::findConfig( $configs, 'såld' )['slot'] ) );
T::test( 'returns null when no config matches', fn() => T::eq( NULL, TagLogic::findConfig( $configs, 'Köpt' ) ) );

echo "resolveTag\n";
$store = [ 12 => 'Såld', 15 => 'Reserverad', 20 => 'Bytt' ];
T::test( 'returns canonical casing from the tag store', fn() => T::eq( 'Såld', TagLogic::resolveTag( 'SÅLD', $store ) ) );
T::test( 'trims before matching', fn() => T::eq( 'Bytt', TagLogic::resolveTag( ' bytt ', $store ) ) );
T::test( 'returns null when the tag does not exist', fn() => T::eq( NULL, TagLogic::resolveTag( 'Köpt', $store ) ) );
T::test( 'returns null for an empty name', fn() => T::eq( NULL, TagLogic::resolveTag( '  ', $store ) ) );

echo "hasTag\n";
T::test( 'true when tag is in the list (case-insensitive)', fn() => T::eq( TRUE, TagLogic::hasTag( [ 'cykel', 'såld' ], NULL, 'Såld' ) ) );
T::test( 'true when tag is the prefix', fn() => T::eq( TRUE, TagLogic::hasTag( [ 'cykel' ], 'Såld', 'såld' ) ) );
T::test( 'false when absent', fn() => T::eq( FALSE, TagLogic::hasTag( [ 'cykel' ], 'Säljes', 'Såld' ) ) );

echo "tagsAfterAdd\n";
T::test( 'appends the canonical tag and keeps the prefix under the prefix key', fn() => T::eq( [ 'prefix' => 'Säljes', 0 => 'cykel', 1 => 'Såld' ], TagLogic::tagsAfterAdd( [ 'cykel' ], 'Säljes', 'Såld' ) ) );
T::test( 'no prefix key when there is no prefix', fn() => T::eq( [ 0 => 'cykel', 1 => 'Såld' ], TagLogic::tagsAfterAdd( [ 'cykel' ], NULL, 'Såld' ) ) );
T::test( 'does not duplicate an already present tag', fn() => T::eq( [ 0 => 'såld' ], TagLogic::tagsAfterAdd( [ 'såld' ], NULL, 'Såld' ) ) );
T::test( 'does not add a tag that is already the prefix', fn() => T::eq( [ 'prefix' => 'Såld', 0 => 'cykel' ], TagLogic::tagsAfterAdd( [ 'cykel' ], 'Såld', 'såld' ) ) );

echo "tagsAfterRemove\n";
T::test( 'removes the tag case-insensitively and keeps the prefix', fn() => T::eq( [ 'prefix' => 'Säljes', 0 => 'cykel' ], TagLogic::tagsAfterRemove( [ 'cykel', 'såld' ], 'Säljes', 'Såld' ) ) );
T::test( 'drops a prefix that equals the tag', fn() => T::eq( [ 0 => 'cykel' ], TagLogic::tagsAfterRemove( [ 'cykel' ], 'Såld', 'såld' ) ) );
T::test( 'reindexes remaining tags', fn() => T::eq( [ 0 => 'a', 1 => 'c' ], TagLogic::tagsAfterRemove( [ 'a', 'Såld', 'c' ], NULL, 'Såld' ) ) );

echo "otherAutolockTagRemains\n";
T::test( 'true when another autolock tag is still present', fn() => T::eq( TRUE, TagLogic::otherAutolockTagRemains( $configs, 'Såld', [ 'reserverad' ], NULL ) ) );
T::test( 'true when another autolock tag is the prefix', fn() => T::eq( TRUE, TagLogic::otherAutolockTagRemains( $configs, 'Såld', [], 'Reserverad' ) ) );
T::test( 'false when only non-autolock tags remain', fn() => T::eq( FALSE, TagLogic::otherAutolockTagRemains( $configs, 'Såld', [ 'bytt' ], NULL ) ) );
T::test( 'ignores the toggled tag itself', fn() => T::eq( FALSE, TagLogic::otherAutolockTagRemains( $configs, 'Såld', [ 'såld' ], NULL ) ) );
T::test( 'false when nothing remains', fn() => T::eq( FALSE, TagLogic::otherAutolockTagRemains( $configs, 'Såld', [], NULL ) ) );

echo "isTogglableState\n";
T::test( 'open is togglable', fn() => T::eq( TRUE, TagLogic::isTogglableState( 'open' ) ) );
T::test( 'closed is togglable', fn() => T::eq( TRUE, TagLogic::isTogglableState( 'closed' ) ) );
T::test( 'moved shadow (link) is not', fn() => T::eq( FALSE, TagLogic::isTogglableState( 'link' ) ) );
T::test( 'merged is not', fn() => T::eq( FALSE, TagLogic::isTogglableState( 'merged' ) ) );
T::test( 'null is not', fn() => T::eq( FALSE, TagLogic::isTogglableState( NULL ) ) );

echo "cssAttributeValue\n";
T::test( 'escapes double quotes and backslashes', fn() => T::eq( 'a\\"b\\\\c', TagLogic::cssAttributeValue( 'a"b\\c' ) ) );
T::test( 'keeps ampersand and apostrophe verbatim', fn() => T::eq( "Sold & Shipped's", TagLogic::cssAttributeValue( "Sold & Shipped's" ) ) );
T::test( 'strips angle brackets and control characters', fn() => T::eq( 'xstylex', TagLogic::cssAttributeValue( "x<style>\n\rx" ) ) );

echo "shouldLockOnMark\n";
T::test( 'locks when autolock is on and topic is open', fn() => T::eq( TRUE, TagLogic::shouldLockOnMark( TRUE, FALSE ) ) );
T::test( 'does nothing when already locked (not our lock)', fn() => T::eq( FALSE, TagLogic::shouldLockOnMark( TRUE, TRUE ) ) );
T::test( 'does nothing when autolock is off', fn() => T::eq( FALSE, TagLogic::shouldLockOnMark( FALSE, FALSE ) ) );

echo "lockIsReleasable (the app may release a lock only if it is its own and untouched)\n";
/* args: appLocked (our record exists), foreignActionAfter (any moderator-log entry by others since), fingerprintMatches (scheduled close time unchanged) */
T::test( 'releasable when the lock is ours and untouched', fn() => T::eq( TRUE, TagLogic::lockIsReleasable( TRUE, FALSE, TRUE ) ) );
T::test( 'not releasable when the lock is not ours', fn() => T::eq( FALSE, TagLogic::lockIsReleasable( FALSE, FALSE, TRUE ) ) );
T::test( 'not releasable after any other moderation action on the topic', fn() => T::eq( FALSE, TagLogic::lockIsReleasable( TRUE, TRUE, TRUE ) ) );
T::test( 'not releasable when the scheduled close time changed since our lock', fn() => T::eq( FALSE, TagLogic::lockIsReleasable( TRUE, FALSE, FALSE ) ) );

echo "shouldUnlockOnUnmark\n";
/* args: autolock, locked, otherAutolockRemains, releasable */
T::test( 'unlocks a releasable lock when nothing else holds it', fn() => T::eq( TRUE, TagLogic::shouldUnlockOnUnmark( TRUE, TRUE, FALSE, TRUE ) ) );
T::test( 'never unlocks a lock that is not releasable', fn() => T::eq( FALSE, TagLogic::shouldUnlockOnUnmark( TRUE, TRUE, FALSE, FALSE ) ) );
T::test( 'keeps the lock while another autolock tag remains', fn() => T::eq( FALSE, TagLogic::shouldUnlockOnUnmark( TRUE, TRUE, TRUE, TRUE ) ) );
T::test( 'nothing to do when the topic is not locked', fn() => T::eq( FALSE, TagLogic::shouldUnlockOnUnmark( TRUE, FALSE, FALSE, TRUE ) ) );
T::test( 'nothing to do when autolock is off', fn() => T::eq( FALSE, TagLogic::shouldUnlockOnUnmark( FALSE, TRUE, FALSE, TRUE ) ) );

echo "flashKey\n";
T::test( 'marked and locked', fn() => T::eq( 'markassold_marked_msg', TagLogic::flashKey( TRUE, TRUE, TRUE ) ) );
T::test( 'marked, autolock off', fn() => T::eq( 'markassold_marked_msg', TagLogic::flashKey( TRUE, FALSE, FALSE ) ) );
T::test( 'marked but lock was refused', fn() => T::eq( 'markassold_marked_not_locked', TagLogic::flashKey( TRUE, TRUE, FALSE ) ) );
T::test( 'unmarked and unlocked', fn() => T::eq( 'markassold_unmarked_msg', TagLogic::flashKey( FALSE, TRUE, TRUE ) ) );
T::test( 'unmarked but unlock was refused', fn() => T::eq( 'markassold_unmarked_not_unlocked', TagLogic::flashKey( FALSE, TRUE, FALSE ) ) );

echo "isHexColor\n";
T::test( 'accepts 3 to 8 hex digits', fn() => T::eq( TRUE, TagLogic::isHexColor( '#E74C3C' ) && TagLogic::isHexColor( '#fff' ) ) );
T::test( 'rejects names and injection', fn() => T::eq( FALSE, TagLogic::isHexColor( 'red' ) || TagLogic::isHexColor( '#fff;}body{' ) ) );

exit( T::summary() );
