<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold;

use IPS\Application as SystemApplication;

class Application extends SystemApplication
{
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
	 * Output CSS files
	 *
	 * @return void
	 */
	public static function outputCss(): void
	{
		parent::outputCss();
	}

	/**
	 * Get all configured tag slots (1 and 2)
	 * Returns array of arrays with keys: tag, forums, autolock, bg_color, text_color
	 *
	 * @return array
	 */
	public static function getTagConfigs(): array
	{
		$configs = array();

		/* Tag 1 */
		$tag1 = \IPS\Settings::i()->markassold_tag ?? '';
		if ( !empty( $tag1 ) && !empty( \IPS\Settings::i()->markassold_forums ) )
		{
			$configs[] = array(
				'tag'        => $tag1,
				'forums'     => \IPS\Settings::i()->markassold_forums,
				'autolock'   => \IPS\Settings::i()->markassold_autolock,
				'bg_color'   => \IPS\Settings::i()->markassold_bg_color ?: '#e74c3c',
				'text_color' => \IPS\Settings::i()->markassold_text_color ?: '#ffffff',
			);
		}

		/* Tag 2 */
		$tag2 = \IPS\Settings::i()->markassold_tag2 ?? '';
		if ( !empty( $tag2 ) && !empty( \IPS\Settings::i()->markassold_forums2 ) )
		{
			$configs[] = array(
				'tag'        => $tag2,
				'forums'     => \IPS\Settings::i()->markassold_forums2,
				'autolock'   => \IPS\Settings::i()->markassold_autolock2,
				'bg_color'   => \IPS\Settings::i()->markassold_bg_color2 ?: '#27ae60',
				'text_color' => \IPS\Settings::i()->markassold_text_color2 ?: '#ffffff',
			);
		}

		return $configs;
	}

	/**
	 * Get tag configs available for a specific forum
	 *
	 * @param	int	$forumId
	 * @return	array
	 */
	public static function getTagConfigsForForum( int $forumId ): array
	{
		$result = array();
		foreach ( static::getTagConfigs() as $config )
		{
			$forumIds = array_map( 'intval', explode( ',', $config['forums'] ) );
			if ( \in_array( $forumId, $forumIds, TRUE ) )
			{
				$result[] = $config;
			}
		}
		return $result;
	}

	/**
	 * Check if a member can toggle tags on a topic
	 *
	 * @param	\IPS\forums\Topic	$topic	The topic
	 * @param	\IPS\Member			$member	The member
	 * @return	bool
	 */
	public static function canToggleSold( \IPS\forums\Topic $topic, \IPS\Member $member ): bool
	{
		/* Must be logged in */
		if ( !$member->member_id )
		{
			return FALSE;
		}

		/* Check if any tag config is enabled for this forum */
		$configs = static::getTagConfigsForForum( (int) $topic->forum_id );
		if ( empty( $configs ) )
		{
			return FALSE;
		}

		/* Topic author or moderator/admin */
		$isAuthor    = ( (int) $topic->starter_id === (int) $member->member_id );
		$isModerator = $member->modPermission( 'can_close_open' ) || $member->isAdmin();

		return $isAuthor || $isModerator;
	}
}
