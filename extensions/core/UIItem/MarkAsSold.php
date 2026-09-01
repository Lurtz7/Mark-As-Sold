<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\extensions\core\UIItem;

use IPS\Content\Item as BaseItem;
use IPS\forums\Topic;
use IPS\Helpers\Menu;
use IPS\Http\Url;
use IPS\markassold\Application;
use IPS\markassold\TagLogic;
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
	 * Inject the badge CSS for all configured tags into the page head.
	 *
	 * The rule is global: any tag with the configured name is styled wherever the
	 * page renders it, but only on pages where this extension runs for a topic.
	 *
	 * @return	void
	 */
	protected static function injectCss(): void
	{
		if ( static::$cssInjected )
		{
			return;
		}
		static::$cssInjected = TRUE;

		$css = '';
		foreach ( Application::getTagConfigs() as $config )
		{
			$bgColor   = TagLogic::isHexColor( $config['bg_color'] ) ? $config['bg_color'] : Application::SLOTS[ $config['slot'] ]['bg_color'];
			$textColor = TagLogic::isHexColor( $config['text_color'] ) ? $config['text_color'] : '#ffffff';
			$exact     = TagLogic::cssAttributeValue( $config['tag'] );
			$lower     = TagLogic::cssAttributeValue( mb_strtolower( $config['tag'] ) );

			/* Regular tags render as .ipsTags__tag, a prefix renders as .ipsBadge--prefix; both carry data-tag-label */
			$css .= <<<CSS
.ipsTags__tag[data-tag-label="{$exact}"],
.ipsTags__tag[data-tag-label="{$lower}"],
.ipsBadge--prefix[data-tag-label="{$exact}"],
.ipsBadge--prefix[data-tag-label="{$lower}"] {
	background-color: {$bgColor} !important;
	color: {$textColor} !important;
	font-weight: 700;
	border: none !important;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	border-radius: 3px;
	padding: 2px 8px;
}

CSS;
		}

		if ( $css !== '' )
		{
			Output::i()->headCss .= $css;
		}
	}

	/**
	 * Add CSS classes to the topic row (used here only to trigger the CSS injection)
	 *
	 * @param	BaseItem	$item
	 * @return	string
	 */
	public function css( BaseItem $item ): string
	{
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
		static::injectCss();

		if ( !( $item instanceof Topic ) )
		{
			return array();
		}

		$member = Member::loggedIn();
		if ( !Application::canToggleSold( $item, $member ) )
		{
			return array();
		}

		$newLinks = array();
		$language = $member->language();

		foreach ( Application::getTagConfigsForForum( (int) $item->forum_id ) as $config )
		{
			$tagName = $config['tag'];
			$hasTag  = Application::topicHasTag( $item, $tagName );

			$url = Url::internal(
				"app=markassold&module=markassold&controller=toggle&id={$item->tid}&tag=" . urlencode( $tagName ),
				'front'
			)->csrf();

			/*
			 * Per-tag language keys so the menu template's {lang} tag resolves to "Mark as <tag>".
			 * Same idiom as core (e.g. \IPS\Content\Item), the placeholder chain is resolved on output.
			 */
			$markKey   = 'markassold_mark_' . md5( $tagName );
			$unmarkKey = 'markassold_unmark_' . md5( $tagName );
			$language->words[ $markKey ]   = $language->addToStack( 'markassold_mark', FALSE, array( 'sprintf' => array( $tagName ) ) );
			$language->words[ $unmarkKey ] = $language->addToStack( 'markassold_unmark', FALSE, array( 'sprintf' => array( $tagName ) ) );

			$link = new Menu\Link(
				url: $url,
				languageString: $hasTag ? $unmarkKey : $markKey,
				icon: $hasTag ? 'fa-solid fa-times' : 'fa-solid fa-tag'
			);

			/*
			 * Confirmation dialog, mentioning the lock side effect when the slot auto-locks.
			 * Link::requiresConfirm( $desc ) writes the sub-message as "data_confirmSubmessage" (underscore),
			 * which the front-end JS never reads, so set the real data attribute ourselves.
			 */
			$link->requiresConfirm();
			if ( $config['autolock'] )
			{
				$link->addAttribute( 'data-confirmSubMessage', $language->addToStack( $hasTag ? 'markassold_confirm_unlock' : 'markassold_confirm_lock' ) );
			}

			$newLinks[ 'markassold_' . $config['slot'] ] = $link;
		}

		return $newLinks;
	}
}
