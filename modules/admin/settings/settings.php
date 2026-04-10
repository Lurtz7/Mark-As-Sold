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

		/* Forum selector — multi-select of forum nodes */
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

		/* Tag name */
		$form->add( new Form\Text(
			'markassold_tag',
			\IPS\Settings::i()->markassold_tag ?: 'Sold',
			TRUE
		) );

		/* Auto-lock toggle */
		$form->add( new Form\YesNo(
			'markassold_autolock',
			\IPS\Settings::i()->markassold_autolock,
			FALSE
		) );

		/* Tag background color */
		$form->add( new Form\Color(
			'markassold_bg_color',
			\IPS\Settings::i()->markassold_bg_color ?: '#e74c3c',
			FALSE
		) );

		/* Tag text color */
		$form->add( new Form\Color(
			'markassold_text_color',
			\IPS\Settings::i()->markassold_text_color ?: '#ffffff',
			FALSE
		) );

		if ( $values = $form->values() )
		{
			/* Convert forum node objects to comma-separated IDs */
			if ( isset( $values['markassold_forums'] ) && \is_array( $values['markassold_forums'] ) )
			{
				$forumIds = array();
				foreach ( $values['markassold_forums'] as $forum )
				{
					$forumIds[] = ( $forum instanceof \IPS\Node\Model ) ? $forum->_id : $forum;
				}
				$values['markassold_forums'] = implode( ',', $forumIds );
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
