<?php

namespace IPS\markassold\extensions\core\UIItem;

use IPS\Content\Item as BaseItem;
use IPS\forums\Topic;
use IPS\Helpers\Menu;
use IPS\Http\Url;
use IPS\Member;
use IPS\Output\UI\Item;
use IPS\Settings;

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
	 * Returns additional menu items for the moderation menu
	 *
	 * @param	BaseItem	$item
	 * @return	array
	 */
	public function menuItems( BaseItem $item ): array
	{
		$newLinks = [];

		/* Use shared permission check */
		$member = Member::loggedIn();
		if ( !\IPS\markassold\Application::canToggleSold( $item, $member ) )
		{
			return $newLinks;
		}

		/* Determine current sold state */
		$tagName = Settings::i()->markassold_tag ?: 'Sold';
		$currentTags = $item->tags() ?: array();
		$isSold = \in_array( $tagName, $currentTags );

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
