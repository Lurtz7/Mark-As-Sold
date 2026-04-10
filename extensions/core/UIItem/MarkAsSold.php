<?php

namespace IPS\markassold\extensions\core\UIItem;

use IPS\Content\Item as BaseItem;
use IPS\forums\Topic;
use IPS\Helpers\Menu;
use IPS\Http\Url;
use IPS\Member;
use IPS\Output;
use IPS\Output\UI\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Content UI extension: MarkAsSold
 */
class MarkAsSold extends Item
{
	/**
	 * @brief	Class to extend
	 */
	public static ?string $class = Topic::class;

	/**
	 * @brief	Track if CSS has been injected already
	 */
	protected static bool $cssInjected = FALSE;

	/**
	 * Check if a topic has the sold tag (case-insensitive)
	 *
	 * @param	BaseItem	$item
	 * @return	bool
	 */
	public static function isSold( BaseItem $item ): bool
	{
		$tagName = \IPS\Settings::i()->markassold_tag ?: 'Sold';
		$currentTags = $item->tags() ?: array();

		foreach ( $currentTags as $tag )
		{
			if ( mb_strtolower( $tag ) === mb_strtolower( $tagName ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Inject CSS for the sold tag styling
	 *
	 * @return	void
	 */
	protected static function injectCss(): void
	{
		if ( static::$cssInjected )
		{
			return;
		}

		$bgColor   = \IPS\Settings::i()->markassold_bg_color ?: '#e74c3c';
		$textColor = \IPS\Settings::i()->markassold_text_color ?: '#ffffff';
		$tagName   = \IPS\Settings::i()->markassold_tag ?: 'Sold';

		/* Sanitize color values */
		if ( !preg_match( '/^#[0-9a-fA-F]{3,8}$/', $bgColor ) )
		{
			$bgColor = '#e74c3c';
		}
		if ( !preg_match( '/^#[0-9a-fA-F]{3,8}$/', $textColor ) )
		{
			$textColor = '#ffffff';
		}

		$tagNameSafe = htmlspecialchars( $tagName, ENT_QUOTES, 'UTF-8' );
		$tagNameLower = htmlspecialchars( mb_strtolower( $tagName ), ENT_QUOTES, 'UTF-8' );

		Output::i()->headCss .= "
.ipsTags__tag[data-tag-label=\"{$tagNameSafe}\"],
.ipsTags__tag[data-tag-label=\"{$tagNameLower}\"] {
	background-color: {$bgColor} !important;
	color: {$textColor} !important;
	font-weight: 700;
	border: none !important;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	border-radius: 3px;
	padding: 2px 8px;
}
";

		static::$cssInjected = TRUE;
	}

	/**
	 * Add CSS classes to the topic row
	 *
	 * @param	BaseItem	$item
	 * @return	string
	 */
	public function css( BaseItem $item ): string
	{
		/* Inject CSS on any page that renders topic items */
		static::injectCss();
		return '';
	}

	/**
	 * Returns additional menu items for the moderation menu
	 *
	 * @param	BaseItem	$item
	 * @return	array
	 */
	public function menuItems( BaseItem $item ): array
	{
		$newLinks = [];

		/* Inject CSS whenever menu items are rendered */
		static::injectCss();

		/* Use shared permission check */
		$member = Member::loggedIn();
		if ( !\IPS\markassold\Application::canToggleSold( $item, $member ) )
		{
			return $newLinks;
		}

		/* Determine current sold state */
		$isSold = static::isSold( $item );

		/* Build the toggle URL with CSRF token */
		$url = Url::internal(
			"app=markassold&module=markassold&controller=toggle&id={$item->tid}",
			'front'
		)->csrf();

		$link = new Menu\Link(
			url: $url,
			languageString: $isSold ? 'markassold_unmark' : 'markassold_mark',
			icon: $isSold ? 'fa-solid fa-times' : 'fa-solid fa-tag'
		);
		$link->requiresConfirm();
		$newLinks['markassold'] = $link;

		return $newLinks;
	}
}
