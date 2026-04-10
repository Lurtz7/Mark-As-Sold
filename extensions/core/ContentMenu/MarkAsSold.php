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
	 * @param	array				$items	Existing menu items (passed by reference or returned)
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

		/* Check if this forum is enabled */
		$enabledForums = Settings::i()->markassold_forums;
		if ( empty( $enabledForums ) )
		{
			return $menuItems;
		}

		$enabledForumIds = explode( ',', $enabledForums );
		if ( !\in_array( $item->forum_id, $enabledForumIds ) )
		{
			return $menuItems;
		}

		/* Check permissions: topic author or moderator */
		$member = Member::loggedIn();
		if ( !$member->member_id )
		{
			return $menuItems;
		}

		$isAuthor    = ( (int) $item->starter_id === (int) $member->member_id );
		$isModerator = $member->modPermission( 'can_close_open' ) || $member->isAdmin();

		if ( !$isAuthor && !$isModerator )
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
