<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\extensions\core\ContentMenu;

use IPS\Member;
use IPS\Settings;

class _MarkAsSold
{
	/**
	 * Modify the content item menu
	 *
	 * @param	\IPS\Content\Item	$item	The content item
	 * @return	array				Menu items to add
	 */
	public function items( \IPS\Content\Item $item ): array
	{
		$menuItems = array();

		/* Only for forum topics */
		if ( !( $item instanceof \IPS\forums\Topic ) )
		{
			return $menuItems;
		}

		/* Use shared permission check */
		$member = Member::loggedIn();
		if ( !\IPS\markassold\Application::canToggleSold( $item, $member ) )
		{
			return $menuItems;
		}

		/* Determine current sold state */
		$tagName = Settings::i()->markassold_tag ?: 'Sold';
		$currentTags = $item->tags() ?: array();
		$isSold = \in_array( $tagName, $currentTags );

		/* Build the toggle URL with CSRF token */
		$url = \IPS\Http\Url::internal(
			"app=markassold&module=markassold&controller=toggle&id={$item->tid}",
			'front'
		)->csrf();

		/*
		 * Add menu item.
		 * IPS5 ContentMenu API — if \IPS\Content\Menu\Link does not exist,
		 * return an array with 'url', 'title', 'icon' keys instead, or check
		 * your IPS5 source at system/Content/Menu/ for the correct class.
		 */
		$menuItems['markassold'] = array(
			'url'   => $url,
			'title' => Member::loggedIn()->language()->addToStack(
				$isSold ? 'markassold_unmark' : 'markassold_mark'
			),
			'icon'  => $isSold ? 'times' : 'tag',
		);

		return $menuItems;
	}
}
