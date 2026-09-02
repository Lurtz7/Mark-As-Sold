<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\modules\front\markassold;

use InvalidArgumentException;
use IPS\Content\Search\Index;
use IPS\Content\Search\SearchContent;
use IPS\Dispatcher\Controller;
use IPS\Events\Event;
use IPS\forums\Topic;
use IPS\Log;
use IPS\markassold\Application;
use IPS\markassold\TagLogic;
use IPS\Member;
use IPS\Output;
use IPS\Request;
use IPS\Session;
use OutOfRangeException;

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
		/* This controller has exactly one action; refuse anything else routed via ?do= */
		if ( Request::i()->do and Request::i()->do !== 'manage' )
		{
			Output::i()->error( 'page_not_found', '2MAS01/0', 404, '' );
			return;
		}

		parent::execute();
	}

	/**
	 * Toggle a configured tag on a topic
	 *
	 * @return void
	 */
	protected function manage(): void
	{
		/* CSRF check */
		Session::i()->csrfCheck();

		$member = Member::loggedIn();

		/* Load the topic with the same permission check IPS's own controllers use */
		try
		{
			$topic = Topic::loadAndCheckPerms( (int) Request::i()->id, $member );
		}
		catch ( OutOfRangeException $e )
		{
			Output::i()->error( 'markassold_invalid_topic', '2MAS01/1', 404, '' );
			return;
		}

		/* Check permissions */
		if ( !Application::canToggleSold( $topic, $member ) )
		{
			Output::i()->error( 'markassold_no_permission', '2MAS01/2', 403, '' );
			return;
		}

		/* Which tag? Must be a plain string matching a slot configured for this forum */
		$tagName = TagLogic::requestTagName( Request::i()->tag );
		$config  = ( $tagName !== '' ) ? Application::getTagConfig( (int) $topic->forum_id, $tagName ) : NULL;
		if ( $config === NULL )
		{
			Output::i()->error( 'markassold_invalid_tag', '2MAS01/3', 400, '' );
			return;
		}

		/* The configured tag must exist and be enabled in AdminCP; setTags() would otherwise drop it silently */
		$canonicalTag = Application::resolveTag( $config['tag'] );
		if ( $canonicalTag === NULL )
		{
			Output::i()->error( 'markassold_tag_missing', '3MAS01/4', 500, '' );
			return;
		}

		$currentTags = $topic->tags();
		$prefix      = $topic->prefix();
		$autolock    = (bool) $config['autolock'];
		$lockChanged = TRUE;

		if ( Application::topicHasTag( $topic, $canonicalTag ) )
		{
			/* Remove the tag (and a prefix equal to it) */
			$topic->setTags( TagLogic::tagsAfterRemove( $currentTags, $prefix, $canonicalTag ), $member );
			if ( Application::topicHasTag( $topic, $canonicalTag ) )
			{
				Output::i()->error( 'markassold_tag_failed', '4MAS01/5', 500, '' );
				return;
			}
			$this->_reindex( $topic );

			if ( $topic->locked() )
			{
				/* Release only the app's own lock, and only when no other auto-lock tag still holds the topic */
				$otherRemains = TagLogic::otherAutolockTagRemains( Application::getTagConfigsForForum( (int) $topic->forum_id ), $canonicalTag, $topic->tags(), $topic->prefix() );
				$lock         = Application::appLock( $topic );
				$modAfter     = $lock ? Application::moderatorLockedAfter( $topic, (int) $lock['lock_time'] ) : FALSE;

				if ( TagLogic::shouldUnlockOnUnmark( $autolock, TRUE, $otherRemains, $lock !== NULL, $modAfter ) )
				{
					$lockChanged = $this->_unlock( $topic, $member );
				}
				elseif ( $autolock and !$otherRemains )
				{
					/* Locked by a moderator (or re-locked after our lock): leave it, and say so */
					$lockChanged = FALSE;
				}
			}
			else
			{
				/* Not locked any more (e.g. a moderator unlocked it manually): forget our record */
				Application::clearAppLock( $topic );
			}

			$flashMessage = TagLogic::flashKey( FALSE, $autolock, $lockChanged );
		}
		else
		{
			/* Add the tag, keeping the prefix and existing tags */
			$topic->setTags( TagLogic::tagsAfterAdd( $currentTags, $prefix, $canonicalTag ), $member );
			if ( !Application::topicHasTag( $topic, $canonicalTag ) )
			{
				Output::i()->error( 'markassold_tag_failed', '4MAS01/6', 500, '' );
				return;
			}
			$this->_reindex( $topic );

			/* Lock only an open topic; an existing lock belongs to whoever applied it and is left alone */
			if ( TagLogic::shouldLockOnMark( $autolock, $topic->locked() ) )
			{
				$lockChanged = $this->_lock( $topic, $member );
			}

			$flashMessage = TagLogic::flashKey( TRUE, $autolock, $lockChanged );
		}

		/* Redirect back to the topic */
		Output::i()->redirect( $topic->url(), $flashMessage );
	}

	/**
	 * Lock the topic on behalf of the member and record that this app did it.
	 *
	 * Members who may lock (moderators, or groups with "Can lock and unlock own content?") go through
	 * IPS's normal moderation action. Everyone else, i.e. the ordinary topic author, gets the same direct
	 * write IPS uses when it auto-closes oversized topics (Topic::autoCloseLargeTopic), plus the
	 * open-timer reset and a moderator-log entry, so the lock is visible and cannot be reopened by a
	 * scheduled open time.
	 *
	 * @param	Topic	$topic
	 * @param	Member	$member
	 * @return	bool	Whether the topic is now locked by this action
	 */
	protected function _lock( Topic $topic, Member $member ): bool
	{
		if ( $topic->canLock( $member ) )
		{
			try
			{
				$topic->modAction( 'lock', $member );
			}
			catch ( OutOfRangeException | InvalidArgumentException $e )
			{
				Log::log( $e, 'markassold' );
				return FALSE;
			}
		}
		else
		{
			$topic->state           = 'closed';
			$topic->topic_open_time = 0;
			$topic->save();
			Event::fire( 'onStatusChange', $topic, array( 'lock' ) );
			Session::i()->modLog( 'modlog__markassold_lock', array( Topic::$title => TRUE, (string) $topic->url() => FALSE, (string) $topic->mapped( 'title' ) => FALSE ), $topic );
		}

		Application::recordAppLock( $topic, $member );
		return TRUE;
	}

	/**
	 * Release a lock this app applied. Callers must have checked TagLogic::shouldUnlockOnUnmark().
	 *
	 * @param	Topic	$topic
	 * @param	Member	$member
	 * @return	bool	Whether the topic was unlocked
	 */
	protected function _unlock( Topic $topic, Member $member ): bool
	{
		if ( $topic->canUnlock( $member ) )
		{
			try
			{
				$topic->modAction( 'unlock', $member );
			}
			catch ( OutOfRangeException | InvalidArgumentException $e )
			{
				Log::log( $e, 'markassold' );
				return FALSE;
			}
		}
		elseif ( $topic->isLargeTopic() )
		{
			/* IPS never lets oversized topics be unlocked (Topic::canUnlock); respect that */
			return FALSE;
		}
		else
		{
			$topic->state            = 'open';
			$topic->topic_close_time = 0;
			$topic->save();
			Event::fire( 'onStatusChange', $topic, array( 'unlock' ) );
			Session::i()->modLog( 'modlog__markassold_unlock', array( Topic::$title => TRUE, (string) $topic->url() => FALSE, (string) $topic->mapped( 'title' ) => FALSE ), $topic );
		}

		Application::clearAppLock( $topic );
		return TRUE;
	}

	/**
	 * Update the search index after a tag change. Taggable::setTags() does not do this for
	 * topics; tags live on the first post's index row, so re-indexing that row is enough
	 * (same as \IPS\Content\Controller::editTags()).
	 *
	 * @param	Topic	$topic
	 * @return	void
	 */
	protected function _reindex( Topic $topic ): void
	{
		if ( SearchContent::isSearchable( $topic ) )
		{
			Index::i()->index( $topic->firstComment() ?: $topic );
		}
	}
}
