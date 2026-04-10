<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\modules\admin\settings;

use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Output;
use IPS\Member;
use IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class settings extends Controller
{
	/**
	 * @var bool CSRF protection
	 */
	public static $csrfProtected = TRUE;

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

		/* === Tag 1 === */
		$form->addHeader( 'markassold_settings_title' );

		$form->add( new Form\Node(
			'markassold_forums',
			\IPS\Settings::i()->markassold_forums ? explode( ',', \IPS\Settings::i()->markassold_forums ) : array(),
			FALSE,
			array(
				'class'           => 'IPS\forums\Forum',
				'multiple'        => TRUE,
				'permissionCheck' => NULL,
			)
		) );

		$form->add( new Form\Text(
			'markassold_tag',
			\IPS\Settings::i()->markassold_tag ?: 'Sold',
			FALSE
		) );

		$form->add( new Form\YesNo(
			'markassold_autolock',
			\IPS\Settings::i()->markassold_autolock,
			FALSE
		) );

		$form->add( new Form\Color(
			'markassold_bg_color',
			\IPS\Settings::i()->markassold_bg_color ?: '#e74c3c',
			FALSE
		) );

		$form->add( new Form\Color(
			'markassold_text_color',
			\IPS\Settings::i()->markassold_text_color ?: '#ffffff',
			FALSE
		) );

		/* === Tag 2 === */
		$form->addHeader( 'markassold_tag2_header' );

		$form->add( new Form\Node(
			'markassold_forums2',
			\IPS\Settings::i()->markassold_forums2 ? explode( ',', \IPS\Settings::i()->markassold_forums2 ) : array(),
			FALSE,
			array(
				'class'           => 'IPS\forums\Forum',
				'multiple'        => TRUE,
				'permissionCheck' => NULL,
			)
		) );

		$form->add( new Form\Text(
			'markassold_tag2',
			\IPS\Settings::i()->markassold_tag2 ?: '',
			FALSE
		) );

		$form->add( new Form\YesNo(
			'markassold_autolock2',
			\IPS\Settings::i()->markassold_autolock2,
			FALSE
		) );

		$form->add( new Form\Color(
			'markassold_bg_color2',
			\IPS\Settings::i()->markassold_bg_color2 ?: '#27ae60',
			FALSE
		) );

		$form->add( new Form\Color(
			'markassold_text_color2',
			\IPS\Settings::i()->markassold_text_color2 ?: '#ffffff',
			FALSE
		) );

		if ( $values = $form->values() )
		{
			/* Convert forum node objects to comma-separated IDs for both tags */
			foreach ( array( 'markassold_forums', 'markassold_forums2' ) as $key )
			{
				if ( isset( $values[ $key ] ) && \is_array( $values[ $key ] ) )
				{
					$forumIds = array();
					foreach ( $values[ $key ] as $forum )
					{
						$forumIds[] = ( $forum instanceof \IPS\Node\Model ) ? $forum->_id : $forum;
					}
					$values[ $key ] = implode( ',', $forumIds );
				}
			}

			$form->saveAsSettings( $values );

			Output::i()->redirect(
				\IPS\Http\Url::internal( 'app=markassold&module=settings&controller=settings' ),
				'saved'
			);
		}

		Output::i()->title  = Member::loggedIn()->language()->addToStack( 'markassold_settings_title' );
		Output::i()->output = (string) $form;
	}
}
