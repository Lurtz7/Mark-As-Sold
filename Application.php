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
	 * Called by IPS when this application's CSS should be loaded.
	 * Injects dynamic CSS for the sold tag styling based on admin settings.
	 *
	 * @return void
	 */
	public static function outputCss(): void
	{
		parent::outputCss();

		$bgColor   = \IPS\Settings::i()->markassold_bg_color ?: '#e74c3c';
		$textColor = \IPS\Settings::i()->markassold_text_color ?: '#ffffff';
		$tagName   = \IPS\Settings::i()->markassold_tag ?: 'Sold';

		/* Sanitize color values — must be valid hex colors */
		if ( !preg_match( '/^#[0-9a-fA-F]{3,8}$/', $bgColor ) )
		{
			$bgColor = '#e74c3c';
		}
		if ( !preg_match( '/^#[0-9a-fA-F]{3,8}$/', $textColor ) )
		{
			$textColor = '#ffffff';
		}

		/* Sanitize tag name for safe CSS selector injection */
		$tagName = htmlspecialchars( $tagName, ENT_QUOTES, 'UTF-8' );

		$css = "<style>
.ipsTags__tag[data-tag-label=\"{$tagName}\"] {
	background-color: {$bgColor} !important;
	color: {$textColor} !important;
	font-weight: 700;
	border: none !important;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	border-radius: 3px;
	padding: 2px 8px;
}
</style>";

		\IPS\Output::i()->headCss .= $css;
	}

	/**
	 * Check if a member can toggle the sold status on a topic
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

		/* Check if forum is enabled */
		$enabledForums = \IPS\Settings::i()->markassold_forums;
		if ( empty( $enabledForums ) )
		{
			return FALSE;
		}

		$enabledForumIds = array_map( 'intval', explode( ',', $enabledForums ) );
		if ( !\in_array( (int) $topic->forum_id, $enabledForumIds, TRUE ) )
		{
			return FALSE;
		}

		/* Topic author or moderator/admin */
		$isAuthor    = ( (int) $topic->starter_id === (int) $member->member_id );
		$isModerator = $member->modPermission( 'can_close_open' ) || $member->isAdmin();

		return $isAuthor || $isModerator;
	}
}
