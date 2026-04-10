<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold;

class _Application extends \IPS\Application
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
	 * Initialize application — called by IPS when the app is loaded.
	 * Injects dynamic CSS for the sold tag styling.
	 *
	 * @return void
	 */
	public function init(): void
	{
		parent::init();

		/* Only inject CSS on front-end pages */
		if ( \IPS\Dispatcher::hasInstance() && \IPS\Dispatcher::i()->controllerLocation === 'front' )
		{
			$this->outputCss();
		}
	}

	/**
	 * Output CSS custom properties for tag colors
	 *
	 * Adds an inline style block with the admin-configured colors
	 * as CSS custom properties, targeting the configured tag name.
	 *
	 * @return void
	 */
	protected function outputCss(): void
	{
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

		/*
		 * Inject inline CSS. IPS5 may use \IPS\Output::i()->headCss,
		 * \IPS\Output::i()->inlineStyles, or another property.
		 * Verify against your IPS5 source at system/Output/Output.php.
		 * If headCss is not available, try adding to endOfBody or
		 * use a globalTemplate theme hook instead.
		 */
		$css = "<style>
:root {
	--markassold-bg: {$bgColor};
	--markassold-text: {$textColor};
}
.ipsTag[data-tag=\"{$tagName}\"] {
	background-color: var(--markassold-bg) !important;
	color: var(--markassold-text) !important;
	font-weight: 700;
	border: none !important;
	text-transform: uppercase;
	letter-spacing: 0.5px;
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
