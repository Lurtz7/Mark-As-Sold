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
	 * Check if a topic has a specific tag (case-insensitive)
	 *
	 * @param	BaseItem	$item
	 * @param	string		$tagName
	 * @return	bool
	 */
	public static function hasTag( BaseItem $item, string $tagName ): bool
	{
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
	 * Inject CSS for all configured tag styles
	 *
	 * @return	void
	 */
	protected static function injectCss(): void
	{
		if ( static::$cssInjected )
		{
			return;
		}

		$css = '';
		foreach ( \IPS\markassold\Application::getTagConfigs() as $config )
		{
			$bgColor   = $config['bg_color'];
			$textColor = $config['text_color'];
			$tagName   = $config['tag'];

			/* Sanitize colors */
			if ( !preg_match( '/^#[0-9a-fA-F]{3,8}$/', $bgColor ) )
			{
				$bgColor = '#e74c3c';
			}
			if ( !preg_match( '/^#[0-9a-fA-F]{3,8}$/', $textColor ) )
			{
				$textColor = '#ffffff';
			}

			$tagSafe  = htmlspecialchars( $tagName, ENT_QUOTES, 'UTF-8' );
			$tagLower = htmlspecialchars( mb_strtolower( $tagName ), ENT_QUOTES, 'UTF-8' );

			$css .= "
.ipsTags__tag[data-tag-label=\"{$tagSafe}\"],
.ipsTags__tag[data-tag-label=\"{$tagLower}\"] {
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
		}

		if ( $css )
		{
			Output::i()->headCss .= $css;
		}

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

		static::injectCss();

		/* Check permissions */
		$member = Member::loggedIn();
		if ( !\IPS\markassold\Application::canToggleSold( $item, $member ) )
		{
			return $newLinks;
		}

		/* Get tag configs for this forum */
		$configs = \IPS\markassold\Application::getTagConfigsForForum( (int) $item->forum_id );

		foreach ( $configs as $index => $config )
		{
			$tagName = $config['tag'];
			$hasTag  = static::hasTag( $item, $tagName );

			$url = Url::internal(
				"app=markassold&module=markassold&controller=toggle&id={$item->tid}&tag=" . urlencode( $tagName ),
				'front'
			)->csrf();

			/*
			 * Create unique language keys per tag for the button labels.
			 * We register them dynamically so IPS's {lang} template tag can find them.
			 */
			$markKey   = 'markassold_mark_' . md5( $tagName );
			$unmarkKey = 'markassold_unmark_' . md5( $tagName );

			Member::loggedIn()->language()->words[ $markKey ]   = Member::loggedIn()->language()->addToStack( 'markassold_mark', FALSE, array( 'sprintf' => array( $tagName ) ) );
			Member::loggedIn()->language()->words[ $unmarkKey ] = Member::loggedIn()->language()->addToStack( 'markassold_unmark', FALSE, array( 'sprintf' => array( $tagName ) ) );

			$link = new Menu\Link(
				url: $url,
				languageString: $hasTag ? $unmarkKey : $markKey,
				icon: $hasTag ? 'fa-solid fa-times' : 'fa-solid fa-tag'
			);
			$link->requiresConfirm();
			$newLinks[ 'markassold_' . $index ] = $link;
		}

		return $newLinks;
	}
}
