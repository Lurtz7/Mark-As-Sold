<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold;

use IPS\Application as SystemApplication;
use IPS\Content\Tag;
use IPS\Db;
use IPS\forums\Topic;
use IPS\Member;
use IPS\Settings;
use UnderflowException;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class Application extends SystemApplication
{
	/**
	 * Tag slots: setting-key suffix and default badge colour
	 */
	public const SLOTS = array(
		1 => array( 'suffix' => '',  'bg_color' => '#e74c3c' ),
		2 => array( 'suffix' => '2', 'bg_color' => '#27ae60' ),
	);

	/**
	 * Icon for the application in AdminCP
	 *
	 * @return	string	Font Awesome icon name
	 */
	protected function get__icon(): string
	{
		return 'tag';
	}

	/**
	 * Get all configured tag slots.
	 * A slot is active only when both a tag name and at least one forum are set.
	 *
	 * @return	array	Each: slot, tag, forums (comma-separated ids), autolock (bool), bg_color, text_color
	 */
	public static function getTagConfigs(): array
	{
		$configs = array();

		foreach ( static::SLOTS as $slot => $meta )
		{
			$s      = $meta['suffix'];
			$tag    = trim( (string) ( Settings::i()->{"markassold_tag{$s}"} ?? '' ) );
			$forums = trim( (string) ( Settings::i()->{"markassold_forums{$s}"} ?? '' ) );

			if ( $tag === '' or $forums === '' )
			{
				continue;
			}

			$configs[] = array(
				'slot'       => $slot,
				'tag'        => $tag,
				'forums'     => $forums,
				'autolock'   => (bool) ( Settings::i()->{"markassold_autolock{$s}"} ?? FALSE ),
				'bg_color'   => (string) ( Settings::i()->{"markassold_bg_color{$s}"} ?: $meta['bg_color'] ),
				'text_color' => (string) ( Settings::i()->{"markassold_text_color{$s}"} ?: '#ffffff' ),
			);
		}

		return $configs;
	}

	/**
	 * Get tag configs enabled for a specific forum
	 *
	 * @param	int	$forumId
	 * @return	array
	 */
	public static function getTagConfigsForForum( int $forumId ): array
	{
		return TagLogic::configsForForum( static::getTagConfigs(), $forumId );
	}

	/**
	 * Get the config for a forum + tag name (case-insensitive)
	 *
	 * @param	int		$forumId
	 * @param	string	$tagName
	 * @return	array|NULL
	 */
	public static function getTagConfig( int $forumId, string $tagName ): ?array
	{
		return TagLogic::findConfig( static::getTagConfigsForForum( $forumId ), $tagName );
	}

	/**
	 * Resolve a configured tag name to the exact text of an enabled AdminCP tag.
	 * Taggable::setTags() silently drops unknown tags, so callers must check this first.
	 *
	 * @param	string	$tagName
	 * @return	string|NULL
	 */
	public static function resolveTag( string $tagName ): ?string
	{
		return TagLogic::resolveTag( $tagName, Tag::getStore() );
	}

	/**
	 * Does the topic carry the tag, as a tag or as its prefix?
	 *
	 * @param	Topic	$topic
	 * @param	string	$tagName
	 * @return	bool
	 */
	public static function topicHasTag( Topic $topic, string $tagName ): bool
	{
		return TagLogic::hasTag( $topic->tags(), $topic->prefix(), $tagName );
	}

	/**
	 * The app's own lock record for a topic: present when the app applied the current lock
	 *
	 * @param	Topic	$topic
	 * @return	array|NULL	lock_topic_id, lock_member_id, lock_time
	 */
	public static function appLock( Topic $topic ): ?array
	{
		try
		{
			return Db::i()->select( '*', 'markassold_locks', array( 'lock_topic_id=?', (int) $topic->tid ) )->first();
		}
		catch ( UnderflowException $e )
		{
			return NULL;
		}
	}

	/**
	 * Moderator-log keys written by this app itself
	 */
	public const OWN_LOG_KEYS = array( 'modlog__markassold_lock', 'modlog__markassold_unlock' );

	/**
	 * Record that the app locked a topic, with the scheduled close time as a fingerprint
	 *
	 * @param	Topic	$topic
	 * @param	Member	$member	Member who marked the topic
	 * @return	void
	 */
	public static function recordAppLock( Topic $topic, Member $member ): void
	{
		Db::i()->replace( 'markassold_locks', array(
			'lock_topic_id'   => (int) $topic->tid,
			'lock_member_id'  => (int) $member->member_id,
			'lock_time'       => time(),
			'lock_close_time' => (int) $topic->topic_close_time,
		) );
	}

	/**
	 * Forget the app's lock record for a topic
	 *
	 * @param	Topic	$topic
	 * @return	void
	 */
	public static function clearAppLock( Topic $topic ): void
	{
		Db::i()->delete( 'markassold_locks', array( 'lock_topic_id=?', (int) $topic->tid ) );
	}

	/**
	 * Drop a stale lock record: the topic is not locked, so whoever unlocked it ended our lock
	 *
	 * @param	Topic	$topic
	 * @return	void
	 */
	public static function syncAppLock( Topic $topic ): void
	{
		if ( !$topic->locked() and static::appLock( $topic ) !== NULL )
		{
			static::clearAppLock( $topic );
		}
	}

	/**
	 * Has anyone other than this app performed a logged moderation action on the topic after the given time?
	 * (Covers IPS's lock/unlock actions and saved actions alike.)
	 *
	 * @param	Topic	$topic
	 * @param	int		$since	Unix timestamp
	 * @return	bool
	 */
	public static function foreignModActionAfter( Topic $topic, int $since ): bool
	{
		return (bool) Db::i()->select( 'COUNT(*)', 'core_moderator_logs', array(
			array( 'class=? AND item_id=? AND ctime>?', Topic::class, (int) $topic->tid, $since ),
			array( Db::i()->in( 'lang_key', static::OWN_LOG_KEYS, TRUE ) ),
		) )->first();
	}

	/**
	 * May the app release the topic's current lock on the author's behalf?
	 * Only its own recorded lock, untouched by any other moderation action, with the scheduled
	 * close time unchanged (scheduled locks write no log entry, so the fingerprint catches them).
	 *
	 * @param	Topic	$topic
	 * @return	bool
	 */
	public static function authorMayReleaseLock( Topic $topic ): bool
	{
		$lock = static::appLock( $topic );
		if ( $lock === NULL )
		{
			return FALSE;
		}
		return TagLogic::lockIsReleasable(
			TRUE,
			static::foreignModActionAfter( $topic, (int) $lock['lock_time'] ),
			(int) $topic->topic_close_time === (int) $lock['lock_close_time']
		);
	}

	/**
	 * Is the member a moderator who may lock or unlock topics in this topic's forum?
	 * Uses the same permission resolution as \IPS\Content\Lockable (global can_lock_content
	 * or the per-forum moderator permission), so restricted moderators are handled correctly.
	 *
	 * @param	Topic	$topic
	 * @param	Member	$member
	 * @return	bool
	 */
	public static function isModeratorFor( Topic $topic, Member $member ): bool
	{
		$container = $topic->container();
		return Topic::modPermission( 'lock', $member, $container ) || Topic::modPermission( 'unlock', $member, $container );
	}

	/**
	 * Check if a member can toggle the configured tags on a topic
	 *
	 * @param	Topic	$topic	The topic
	 * @param	Member	$member	The member
	 * @return	bool
	 */
	public static function canToggleSold( Topic $topic, Member $member ): bool
	{
		/* Must be logged in */
		if ( !$member->member_id )
		{
			return FALSE;
		}

		/* Only real, visible topics: not moved shadows or merged rows, not hidden or pending approval, not archived */
		if ( !TagLogic::isTogglableState( $topic->state ) or $topic->hidden() !== 0 or $topic->isArchived() )
		{
			return FALSE;
		}

		/* Must be able to see the topic at all */
		if ( !$topic->canView( $member ) )
		{
			return FALSE;
		}

		/* Any tag slot configured for this forum? */
		if ( empty( static::getTagConfigsForForum( (int) $topic->forum_id ) ) )
		{
			return FALSE;
		}

		/* Tagging must be possible here: tags enabled globally and in this forum, member not banned from tagging */
		if ( !Topic::canTag( $member, $topic->container() ) )
		{
			return FALSE;
		}

		/* Under a posting restriction or with an unacknowledged warning? Applies to everyone, as in \IPS\Content\Item::couldEdit() */
		if ( $member->restrict_post or ( $member->members_bitoptions['unacknowledged_warnings'] and Settings::i()->warn_on and Settings::i()->warnings_acknowledge ) )
		{
			return FALSE;
		}

		/* Moderators of this forum */
		if ( static::isModeratorFor( $topic, $member ) )
		{
			return TRUE;
		}

		/* Otherwise only the topic author */
		if ( (int) $topic->starter_id !== (int) $member->member_id )
		{
			return FALSE;
		}

		/*
		 * A locked topic is a moderation state. The author may only touch it if IPS would let them
		 * unlock it themselves, or if the lock is one this app applied for them and nobody else has
		 * touched since. This is what stops an author from undoing a moderator's lock.
		 */
		static::syncAppLock( $topic );
		if ( $topic->locked() and !$topic->canUnlock( $member ) and !static::authorMayReleaseLock( $topic ) )
		{
			return FALSE;
		}

		return TRUE;
	}
}
