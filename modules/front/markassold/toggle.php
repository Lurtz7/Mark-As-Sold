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
	 * Toggle a tag on a topic
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

		/* Check permissions */
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

		/* Which tag are we toggling? */
		$tagName = Request::i()->tag ?? '';
		if ( empty( $tagName ) )
		{
			Output::i()->error(
				Member::loggedIn()->language()->addToStack( 'markassold_no_permission' ),
				'2MAS01/3',
				400
			);
			return;
		}

		/* Find the matching config for this tag + forum */
		$configs  = \IPS\markassold\Application::getTagConfigsForForum( (int) $topic->forum_id );
		$config   = NULL;
		foreach ( $configs as $c )
		{
			if ( mb_strtolower( $c['tag'] ) === mb_strtolower( $tagName ) )
			{
				$config = $c;
				break;
			}
		}

		if ( !$config )
		{
			Output::i()->error(
				Member::loggedIn()->language()->addToStack( 'markassold_no_permission' ),
				'2MAS01/4',
				403
			);
			return;
		}

		/* Determine current state and toggle */
		$currentTags = $topic->tags();
		$prefix      = $topic->prefix();
		$hasTag      = \IPS\markassold\extensions\core\UIItem\MarkAsSold::hasTag( $topic, $config['tag'] );

		if ( $hasTag )
		{
			/* Remove the tag (case-insensitive) */
			$tagToRemove = $config['tag'];
			$newTags = array_values( array_filter( $currentTags, function( $tag ) use ( $tagToRemove ) {
				return mb_strtolower( $tag ) !== mb_strtolower( $tagToRemove );
			} ) );

			if ( $prefix )
			{
				$newTags = array_merge( array( 'prefix' => $prefix ), $newTags );
			}

			$topic->setTags( $newTags );

			/* Unlock if auto-lock is enabled for this tag */
			if ( $config['autolock'] )
			{
				$topic->state = 'open';
				$topic->save();
			}

			$flashMessage = 'markassold_unmarked_msg';
		}
		else
		{
			/* Add the tag */
			$currentTags[] = $config['tag'];

			if ( $prefix )
			{
				$currentTags = array_merge( array( 'prefix' => $prefix ), $currentTags );
			}

			$topic->setTags( $currentTags );

			/* Lock if auto-lock is enabled for this tag */
			if ( $config['autolock'] )
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
