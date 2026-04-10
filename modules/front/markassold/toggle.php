<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\modules\front\markassold;

use IPS\Dispatcher;
use IPS\Output;
use IPS\Request;
use IPS\Member;
use IPS\Settings;

class _toggle extends \IPS\Dispatcher\Controller
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

		/* Check if the forum is enabled */
		$enabledForums = Settings::i()->markassold_forums;
		if ( empty( $enabledForums ) )
		{
			Output::i()->error(
				Member::loggedIn()->language()->addToStack( 'markassold_no_permission' ),
				'2MAS01/2',
				403
			);
			return;
		}

		$enabledForumIds = explode( ',', $enabledForums );
		if ( !\in_array( $topic->forum_id, $enabledForumIds ) )
		{
			Output::i()->error(
				Member::loggedIn()->language()->addToStack( 'markassold_no_permission' ),
				'2MAS01/3',
				403
			);
			return;
		}

		/* Check permissions: topic author or moderator */
		$member = Member::loggedIn();
		if ( !$member->member_id )
		{
			Output::i()->error(
				Member::loggedIn()->language()->addToStack( 'markassold_no_permission' ),
				'2MAS01/4',
				403
			);
			return;
		}

		$isAuthor    = ( (int) $topic->starter_id === (int) $member->member_id );
		$isModerator = $member->modPermission( 'can_close_open' ) || $member->isAdmin();

		if ( !$isAuthor && !$isModerator )
		{
			Output::i()->error(
				Member::loggedIn()->language()->addToStack( 'markassold_no_permission' ),
				'2MAS01/5',
				403
			);
			return;
		}

		/* Determine current state and toggle */
		$tagName     = Settings::i()->markassold_tag ?: 'Sold';
		$currentTags = $topic->tags() ?: array();
		$isSold      = \in_array( $tagName, $currentTags );

		if ( $isSold )
		{
			/* Remove the sold tag */
			$newTags = array_values( array_filter( $currentTags, function( $tag ) use ( $tagName ) {
				return $tag !== $tagName;
			} ) );

			$this->setTopicTags( $topic, $newTags );

			/* Unlock if auto-lock is enabled */
			if ( Settings::i()->markassold_autolock )
			{
				$topic->state = 'open';
				$topic->save();
			}

			$flashMessage = 'markassold_unmarked_msg';
		}
		else
		{
			/* Add the sold tag */
			$currentTags[] = $tagName;

			$this->setTopicTags( $topic, $currentTags );

			/* Lock if auto-lock is enabled */
			if ( Settings::i()->markassold_autolock )
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

	/**
	 * Set tags on a topic
	 *
	 * Uses IPS's built-in tag methods. If $topic->setTags() is not available
	 * in your IPS5 version, this falls back to direct DB manipulation.
	 *
	 * @param	\IPS\forums\Topic	$topic	The topic
	 * @param	array				$tags	Array of tag strings
	 * @return	void
	 */
	protected function setTopicTags( \IPS\forums\Topic $topic, array $tags ): void
	{
		/*
		 * IPS5 approach: use the built-in setTags method if available.
		 * The Content\Tags interface provides setTags() on content items.
		 *
		 * Verify this works against your IPS5 source code at:
		 *   system/Content/Tags.php
		 *   applications/forums/sources/Topic/Topic.php
		 *
		 * If setTags() is not available, use the fallback below.
		 */
		if ( method_exists( $topic, 'setTags' ) )
		{
			$topic->setTags( $tags );
			return;
		}

		/*
		 * Fallback: direct database manipulation.
		 * Delete existing tags and re-insert.
		 */
		$existingPrefix = $topic->prefix();

		\IPS\Db::i()->delete( 'core_tags', array(
			'tag_meta_app=? AND tag_meta_area=? AND tag_meta_id=?',
			'forums',
			'forums',
			$topic->tid
		) );

		$member = Member::loggedIn();
		$position = 0;

		/* Re-insert prefix if it existed and is not in the new tags */
		if ( $existingPrefix )
		{
			\IPS\Db::i()->insert( 'core_tags', array(
				'tag_aai_lookup'     => $topic->tagAAIKey(),
				'tag_aap_lookup'     => $topic->tagAAPKey(),
				'tag_meta_app'       => 'forums',
				'tag_meta_area'      => 'forums',
				'tag_meta_id'        => $topic->tid,
				'tag_meta_parent_id' => $topic->forum_id,
				'tag_member_id'      => $member->member_id,
				'tag_added'          => time(),
				'tag_prefix'         => 1,
				'tag_text'           => $existingPrefix,
			) );
			$position++;
		}

		foreach ( $tags as $tag )
		{
			if ( $tag === $existingPrefix )
			{
				continue;
			}

			\IPS\Db::i()->insert( 'core_tags', array(
				'tag_aai_lookup'     => $topic->tagAAIKey(),
				'tag_aap_lookup'     => $topic->tagAAPKey(),
				'tag_meta_app'       => 'forums',
				'tag_meta_area'      => 'forums',
				'tag_meta_id'        => $topic->tid,
				'tag_meta_parent_id' => $topic->forum_id,
				'tag_member_id'      => $member->member_id,
				'tag_added'          => time(),
				'tag_prefix'         => 0,
				'tag_text'           => $tag,
			) );
			$position++;
		}

		/* Clear tag cache on the topic */
		if ( method_exists( $topic, 'clearTagCache' ) )
		{
			$topic->clearTagCache();
		}
	}
}
