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

			/* Unlock only when this slot auto-locks and no other auto-lock tag is still on the topic */
			$otherRemains = TagLogic::otherAutolockTagRemains( Application::getTagConfigsForForum( (int) $topic->forum_id ), $canonicalTag, $topic->tags(), $topic->prefix() );
			if ( TagLogic::shouldUnlockOnUnmark( $autolock, $topic->locked(), $otherRemains ) )
			{
				$lockChanged = $this->_changeLock( $topic, $member, FALSE );
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

			if ( TagLogic::shouldLockOnMark( $autolock, $topic->locked() ) )
			{
				$lockChanged = $this->_changeLock( $topic, $member, TRUE );
			}

			$flashMessage = TagLogic::flashKey( TRUE, $autolock, $lockChanged );
		}

		/* Redirect back to the topic */
		Output::i()->redirect( $topic->url(), $flashMessage );
	}

	/**
	 * Lock or unlock through IPS's own moderation path: permission check, moderator log,
	 * scheduled open/close reset, linked Pages records, events and webhooks.
	 *
	 * Returns FALSE when the member may not perform the action, e.g. a topic author whose
	 * group lacks "Can lock and unlock own content?", or a large topic IPS refuses to unlock.
	 *
	 * @param	Topic	$topic
	 * @param	Member	$member
	 * @param	bool	$lock	TRUE to lock, FALSE to unlock
	 * @return	bool	Whether the lock state was changed
	 */
	protected function _changeLock( Topic $topic, Member $member, bool $lock ): bool
	{
		$allowed = $lock ? $topic->canLock( $member ) : $topic->canUnlock( $member );
		if ( !$allowed )
		{
			return FALSE;
		}

		try
		{
			$topic->modAction( $lock ? 'lock' : 'unlock', $member );
			return TRUE;
		}
		catch ( OutOfRangeException | InvalidArgumentException $e )
		{
			Log::log( $e, 'markassold' );
			return FALSE;
		}
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
