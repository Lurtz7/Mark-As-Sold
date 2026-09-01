<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Pure decision logic for Mark As Sold.
 *
 * No IPS runtime dependencies (no Settings, Db or Member), so it can be
 * unit-tested with plain PHP: see tests/run.php.
 *
 * Tag lists follow the \IPS\Content\Taggable convention: a plain list of tag
 * strings, optionally with the prefix under the string key 'prefix'.
 */
class TagLogic
{
	/**
	 * Case-insensitive, trimmed form of a tag used for every comparison
	 *
	 * @param	string	$tag
	 * @return	string
	 */
	public static function normalise( string $tag ): string
	{
		return mb_strtolower( trim( $tag ) );
	}

	/**
	 * Sanitise the raw "tag" request parameter: only a plain string is accepted
	 *
	 * @param	mixed	$raw	Value from \IPS\Request (may be an array for ?tag[]=)
	 * @return	string	Trimmed string, or '' when unusable
	 */
	public static function requestTagName( mixed $raw ): string
	{
		return is_string( $raw ) ? trim( $raw ) : '';
	}

	/**
	 * Parse the comma-separated forum id setting
	 *
	 * @param	string	$list	e.g. "3,7"
	 * @return	array<int>	Positive ids only
	 */
	public static function parseForumIds( string $list ): array
	{
		$ids = array();
		foreach ( explode( ',', $list ) as $part )
		{
			$id = (int) trim( $part );
			if ( $id > 0 )
			{
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Configs whose forum list contains the given forum, in slot order
	 *
	 * @param	array	$configs	As returned by Application::getTagConfigs()
	 * @param	int		$forumId
	 * @return	array
	 */
	public static function configsForForum( array $configs, int $forumId ): array
	{
		$result = array();
		foreach ( $configs as $config )
		{
			if ( in_array( $forumId, static::parseForumIds( (string) $config['forums'] ), TRUE ) )
			{
				$result[] = $config;
			}
		}
		return $result;
	}

	/**
	 * Find the config for a tag name (case-insensitive)
	 *
	 * @param	array	$configs
	 * @param	string	$tagName
	 * @return	array|NULL
	 */
	public static function findConfig( array $configs, string $tagName ): ?array
	{
		$needle = static::normalise( $tagName );
		if ( $needle === '' )
		{
			return NULL;
		}
		foreach ( $configs as $config )
		{
			if ( static::normalise( (string) $config['tag'] ) === $needle )
			{
				return $config;
			}
		}
		return NULL;
	}

	/**
	 * Resolve a configured tag name to the exact text of a tag in the store
	 *
	 * @param	string	$tagName	Admin-configured name, any casing
	 * @param	array	$store		\IPS\Content\Tag::getStore(): id => tag text (enabled tags only)
	 * @return	string|NULL	Canonical tag text, or NULL when no enabled tag matches
	 */
	public static function resolveTag( string $tagName, array $store ): ?string
	{
		$needle = static::normalise( $tagName );
		if ( $needle === '' )
		{
			return NULL;
		}
		foreach ( $store as $text )
		{
			if ( static::normalise( (string) $text ) === $needle )
			{
				return (string) $text;
			}
		}
		return NULL;
	}

	/**
	 * Does the item carry the tag, either as a regular tag or as its prefix?
	 *
	 * @param	array		$tags		Regular tags (Taggable::tags())
	 * @param	string|NULL	$prefix		Prefix (Taggable::prefix())
	 * @param	string		$tagName
	 * @return	bool
	 */
	public static function hasTag( array $tags, ?string $prefix, string $tagName ): bool
	{
		$needle = static::normalise( $tagName );
		if ( $needle === '' )
		{
			return FALSE;
		}
		if ( $prefix !== NULL and static::normalise( $prefix ) === $needle )
		{
			return TRUE;
		}
		foreach ( $tags as $tag )
		{
			if ( static::normalise( (string) $tag ) === $needle )
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Tag list to pass to setTags() when adding the tag
	 *
	 * @param	array		$tags			Current regular tags
	 * @param	string|NULL	$prefix			Current prefix (preserved)
	 * @param	string		$canonicalTag	Exact tag text from the store
	 * @return	array
	 */
	public static function tagsAfterAdd( array $tags, ?string $prefix, string $canonicalTag ): array
	{
		$list = array_values( $tags );
		if ( !static::hasTag( $list, $prefix, $canonicalTag ) )
		{
			$list[] = $canonicalTag;
		}
		return static::withPrefix( $list, $prefix );
	}

	/**
	 * Tag list to pass to setTags() when removing the tag.
	 * A prefix equal to the tag is dropped as well.
	 *
	 * @param	array		$tags
	 * @param	string|NULL	$prefix
	 * @param	string		$tagName
	 * @return	array
	 */
	public static function tagsAfterRemove( array $tags, ?string $prefix, string $tagName ): array
	{
		$needle = static::normalise( $tagName );
		$list   = array();
		foreach ( $tags as $tag )
		{
			if ( static::normalise( (string) $tag ) !== $needle )
			{
				$list[] = (string) $tag;
			}
		}
		if ( $prefix !== NULL and static::normalise( $prefix ) === $needle )
		{
			$prefix = NULL;
		}
		return static::withPrefix( $list, $prefix );
	}

	/**
	 * After removing $toggledTag, does any OTHER auto-lock tag still sit on the item?
	 * Used to decide whether unmarking may unlock the topic.
	 *
	 * @param	array		$configs			Configs for the item's forum
	 * @param	string		$toggledTag
	 * @param	array		$remainingTags		Regular tags after removal
	 * @param	string|NULL	$remainingPrefix	Prefix after removal
	 * @return	bool
	 */
	public static function otherAutolockTagRemains( array $configs, string $toggledTag, array $remainingTags, ?string $remainingPrefix ): bool
	{
		$toggled = static::normalise( $toggledTag );
		foreach ( $configs as $config )
		{
			if ( empty( $config['autolock'] ) )
			{
				continue;
			}
			$tag = (string) $config['tag'];
			if ( static::normalise( $tag ) === $toggled )
			{
				continue;
			}
			if ( static::hasTag( $remainingTags, $remainingPrefix, $tag ) )
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Should marking lock the topic? Only when the slot auto-locks and the topic is still open;
	 * an existing lock is left alone because it was not applied by this tag.
	 *
	 * @param	bool	$autolock	Slot setting
	 * @param	bool	$locked		Current lock state
	 * @return	bool
	 */
	public static function shouldLockOnMark( bool $autolock, bool $locked ): bool
	{
		return $autolock && !$locked;
	}

	/**
	 * Should unmarking unlock the topic? Only when the slot auto-locks, the topic is locked,
	 * and no other auto-lock tag is still holding it. Whether the member MAY unlock is decided
	 * by IPS (Lockable::canUnlock) at the call site.
	 *
	 * @param	bool	$autolock				Slot setting
	 * @param	bool	$locked					Current lock state
	 * @param	bool	$otherAutolockRemains	See otherAutolockTagRemains()
	 * @return	bool
	 */
	public static function shouldUnlockOnUnmark( bool $autolock, bool $locked, bool $otherAutolockRemains ): bool
	{
		return $autolock && $locked && !$otherAutolockRemains;
	}

	/**
	 * Language key for the redirect message, telling the member when a lock change was refused
	 *
	 * @param	bool	$marked			TRUE after marking, FALSE after unmarking
	 * @param	bool	$autolock		Slot setting
	 * @param	bool	$lockChanged	FALSE when a lock/unlock was wanted but not permitted
	 * @return	string
	 */
	public static function flashKey( bool $marked, bool $autolock, bool $lockChanged ): string
	{
		$refused = $autolock && !$lockChanged;
		if ( $marked )
		{
			return $refused ? 'markassold_marked_not_locked' : 'markassold_marked_msg';
		}
		return $refused ? 'markassold_unmarked_not_unlocked' : 'markassold_unmarked_msg';
	}

	/**
	 * Only real topics may be toggled: not moved shadows ('link') or merged rows
	 *
	 * @param	string|NULL	$state	forums_topics.state
	 * @return	bool
	 */
	public static function isTogglableState( ?string $state ): bool
	{
		return in_array( $state, array( 'open', 'closed' ), TRUE );
	}

	/**
	 * Escape a tag name for use inside a double-quoted CSS attribute selector.
	 * Control characters and angle brackets are removed (defence in depth inside <style>).
	 *
	 * @param	string	$value
	 * @return	string
	 */
	public static function cssAttributeValue( string $value ): string
	{
		$value = preg_replace( '/[\x00-\x1F\x7F<>]/u', '', $value ) ?? '';
		return str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value );
	}

	/**
	 * Is the value a safe CSS hex colour (#rgb, #rrggbb, #rrggbbaa)?
	 *
	 * @param	string	$value
	 * @return	bool
	 */
	public static function isHexColor( string $value ): bool
	{
		return (bool) preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value );
	}

	/**
	 * Put the prefix back under the 'prefix' key, as Taggable::setTags() expects
	 *
	 * @param	array		$list
	 * @param	string|NULL	$prefix
	 * @return	array
	 */
	protected static function withPrefix( array $list, ?string $prefix ): array
	{
		$list = array_values( $list );
		if ( $prefix !== NULL and trim( $prefix ) !== '' )
		{
			return array_merge( array( 'prefix' => $prefix ), $list );
		}
		return $list;
	}
}
