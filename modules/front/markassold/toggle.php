<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\modules\front\markassold;

use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Output;
use IPS\Request;
use IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class toggle extends Controller
{
	/**
	 * Execute
	 *
	 * @return void
	 */
	public function execute(): void
	{
		parent::execute();
	}

	/**
	 * Toggle the sold tag on a topic
	 *
	 * @return void
	 */
	protected function manage(): void
	{
		/* CSRF check */
		\IPS\Session::i()->csrfCheck();

		/* Load the topic */
		$topicId = (int) Request::i()->id;
		try
		{
			$topic = \IPS\forums\Topic::load( $topicId );
		}
		catch ( \OutOfRangeException $e )
		{
			Output::i()->error(
				Member::loggedIn()->language()->addToStack( 'markassold_invalid_topic' ),
				'2MAS01/1',
				404
			);
			return;
		}

		/* Use shared permission check */
		$member = Member::loggedIn();
		if ( !\IPS\markassold\Application::canToggleSold( $topic, $member ) )
		{
			Output::i()->error(
				Member::loggedIn()->language()->addToStack( 'markassold_no_permission' ),
				'2MAS01/2',
				403
			);
			return;
		}

		/* Determine current state and toggle */
		$tagName     = \IPS\Settings::i()->markassold_tag ?: 'Sold';
		$currentTags = $topic->tags();
		$prefix      = $topic->prefix();
		$isSold      = \in_array( $tagName, $currentTags );

		if ( $isSold )
		{
			/* Remove the sold tag, keep everything else */
			$newTags = array_values( array_filter( $currentTags, function( $tag ) use ( $tagName ) {
				return $tag !== $tagName;
			} ) );

			/* Re-add prefix if there was one */
			if ( $prefix )
			{
				$newTags = array_merge( array( 'prefix' => $prefix ), $newTags );
			}

			$topic->setTags( $newTags );

			/* Unlock if auto-lock is enabled */
			if ( \IPS\Settings::i()->markassold_autolock )
			{
				$topic->state = 'open';
				$topic->save();
			}

			$flashMessage = 'markassold_unmarked_msg';
		}
		else
		{
			/* Add the sold tag to existing tags */
			$currentTags[] = $tagName;

			/* Re-add prefix if there was one */
			if ( $prefix )
			{
				$currentTags = array_merge( array( 'prefix' => $prefix ), $currentTags );
			}

			$topic->setTags( $currentTags );

			/* Lock if auto-lock is enabled */
			if ( \IPS\Settings::i()->markassold_autolock )
			{
				$topic->state = 'closed';
				$topic->save();
			}

			$flashMessage = 'markassold_marked_msg';
		}

		/* Redirect back to the topic */
		Output::i()->redirect(
			$topic->url(),
			Member::loggedIn()->language()->addToStack( $flashMessage )
		);
	}
}
