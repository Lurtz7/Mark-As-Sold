<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\modules\admin\settings;

use DomainException;
use IPS\Db;
use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Helpers\Form;
use IPS\Http\Url;
use IPS\markassold\Application;
use IPS\markassold\TagLogic;
use IPS\Member;
use IPS\Node\Model;
use IPS\Output;
use IPS\Request;
use IPS\Session;

/* Note: no "use IPS\Settings" here. PHP class names are case-insensitive, so that alias
   would collide with this controller class, which IPS requires to be named "settings". */

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class settings extends Controller
{
	/**
	 * @var bool	Controller handles CSRF itself (the Form helper validates csrfKey on submit)
	 */
	public static bool $csrfProtected = TRUE;

	/**
	 * Execute
	 *
	 * @return void
	 */
	public function execute(): void
	{
		Dispatcher::i()->checkAcpPermission( 'markassold_settings_manage' );
		parent::execute();
	}

	/**
	 * Manage settings
	 *
	 * @return void
	 */
	protected function manage(): void
	{
		$form = new Form;

		/* A configured tag must exist (and be enabled) in AdminCP > Community > Tags */
		$tagExists = function( $val )
		{
			$val = trim( (string) $val );
			if ( $val !== '' and Application::resolveTag( $val ) === NULL )
			{
				throw new DomainException( 'markassold_tag_not_found' );
			}
		};

		/* Tag 2 must differ from tag 1, otherwise the slots collide on the same tag */
		$tag2Valid = function( $val ) use ( $tagExists )
		{
			$tagExists( $val );
			$val = trim( (string) $val );
			if ( $val !== '' and TagLogic::normalise( $val ) === TagLogic::normalise( (string) Request::i()->markassold_tag ) )
			{
				throw new DomainException( 'markassold_tag_duplicate' );
			}
		};

		foreach ( Application::SLOTS as $slot => $meta )
		{
			$s = $meta['suffix'];

			$form->addHeader( $slot === 1 ? 'markassold_settings_title' : 'markassold_tag2_header' );

			$form->add( new Form\Node(
				"markassold_forums{$s}",
				\IPS\Settings::i()->{"markassold_forums{$s}"} ? explode( ',', \IPS\Settings::i()->{"markassold_forums{$s}"} ) : array(),
				FALSE,
				array(
					'class'           => 'IPS\forums\Forum',
					'multiple'        => TRUE,
					'permissionCheck' => NULL,
				)
			) );

			/* Empty tag name = slot disabled (data/settings.json defaults to "" so IPS does not substitute a default) */
			$form->add( new Form\Text(
				"markassold_tag{$s}",
				(string) ( \IPS\Settings::i()->{"markassold_tag{$s}"} ?? '' ),
				FALSE,
				array(),
				$slot === 1 ? $tagExists : $tag2Valid
			) );

			$form->add( new Form\YesNo(
				"markassold_autolock{$s}",
				\IPS\Settings::i()->{"markassold_autolock{$s}"},
				FALSE
			) );

			$form->add( new Form\Color(
				"markassold_bg_color{$s}",
				\IPS\Settings::i()->{"markassold_bg_color{$s}"} ?: $meta['bg_color'],
				FALSE
			) );

			$form->add( new Form\Color(
				"markassold_text_color{$s}",
				\IPS\Settings::i()->{"markassold_text_color{$s}"} ?: '#ffffff',
				FALSE
			) );
		}

		if ( $values = $form->values() )
		{
			/* Convert forum node objects to comma-separated IDs */
			foreach ( Application::SLOTS as $meta )
			{
				$key = "markassold_forums{$meta['suffix']}";
				if ( isset( $values[ $key ] ) and is_array( $values[ $key ] ) )
				{
					$forumIds = array();
					foreach ( $values[ $key ] as $forum )
					{
						$forumIds[] = ( $forum instanceof Model ) ? $forum->_id : $forum;
					}
					$values[ $key ] = implode( ',', $forumIds );
				}
			}

			/*
			 * Settings::changeValues() silently ignores keys that have no row in
			 * core_sys_conf_settings (it only throws under IN_DEV). That happens when the
			 * app was updated by copying files instead of uploading through the AdminCP,
			 * so fail loudly instead of showing a false "Saved".
			 */
			$existing = iterator_to_array( Db::i()->select( 'conf_key', 'core_sys_conf_settings', array( 'conf_app=?', 'markassold' ) ) );
			$missing  = array_diff( array_keys( $values ), $existing );
			if ( count( $missing ) )
			{
				Output::i()->error( 'markassold_settings_missing', '3MAS02/1', 500, '' );
				return;
			}

			$form->saveAsSettings( $values );
			Session::i()->log( 'acplog__markassold_settings' );

			Output::i()->redirect( Url::internal( 'app=markassold&module=settings&controller=settings' ), 'saved' );
		}

		Output::i()->title  = Member::loggedIn()->language()->addToStack( 'markassold_settings_title' );
		Output::i()->output = (string) $form;
	}
}
