<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

$lang = array(

	'__app_markassold'				=> "Mark As Sold",
	'__app_markassold_description'	=> "Allows topic creators and moderators to mark forum topics as sold by toggling a tag.",

	// Module names
	'module__markassold_markassold'	=> "Mark As Sold",
	'module__markassold_settings'	=> "Settings",

	// Menu items (content menu button labels) — dynamic, uses tag name
	'markassold_mark'				=> "Mark as %s",
	'markassold_unmark'				=> "Unmark as %s",

	// Flash messages after toggle
	'markassold_marked_msg'			=> "This topic has been marked.",
	'markassold_unmarked_msg'		=> "This topic has been unmarked.",

	// Error messages
	'markassold_no_permission'		=> "You do not have permission to perform this action.",
	'markassold_invalid_topic'		=> "The topic could not be found.",

	// Admin settings page
	'markassold_settings_title'		=> "Mark As Sold Settings",
	'markassold_forums'				=> "Enabled Forums",
	'markassold_forums_desc'		=> "Select which forums show the Mark as Sold button. Leave empty to disable.",
	'markassold_tag'				=> "Tag Name",
	'markassold_tag_desc'			=> "The tag to apply when a topic is marked as sold. Must match an existing tag created in AdminCP > Community > Tags. Supports any language (e.g. \"Såld\" for Swedish).",
	'markassold_autolock'			=> "Auto-lock Topic",
	'markassold_autolock_desc'		=> "Automatically lock the topic when marked as sold, and unlock when unmarked.",
	'markassold_bg_color'			=> "Tag Background Color",
	'markassold_bg_color_desc'		=> "Background color for the Sold tag badge.",
	'markassold_text_color'			=> "Tag Text Color",
	'markassold_text_color_desc'	=> "Text color for the tag badge.",

	// Tag 2 settings
	'markassold_tag2_header'		=> "Second Tag",
	'markassold_forums2'			=> "Enabled Forums (Tag 2)",
	'markassold_forums2_desc'		=> "Select which forums show the second tag button. Leave empty to disable.",
	'markassold_tag2'				=> "Tag Name (Tag 2)",
	'markassold_tag2_desc'			=> "The second tag (e.g. \"Bought\" / \"Köpt\"). Must match an existing tag in AdminCP. Leave empty to disable.",
	'markassold_autolock2'			=> "Auto-lock Topic (Tag 2)",
	'markassold_autolock2_desc'		=> "Automatically lock the topic when this tag is applied.",
	'markassold_bg_color2'			=> "Tag Background Color (Tag 2)",
	'markassold_bg_color2_desc'		=> "Background color for the second tag badge.",
	'markassold_text_color2'		=> "Tag Text Color (Tag 2)",
	'markassold_text_color2_desc'	=> "Text color for the second tag badge.",

	// AdminCP restrictions
	'r__markassold_settings_manage'	=> "Can manage Mark As Sold settings?",

	// AdminCP menu
	'menu__markassold_settings'				=> "Mark As Sold",
	'menu__markassold_settings_settings'	=> "Settings",
	'menutab__markassold'					=> "Mark As Sold",
	'menutab__markassold_icon'				=> "tag",

	/*
	 * Swedish translations — add these via AdminCP > System > Languages > [Swedish] > Translate
	 *
	 * markassold_mark              => "Markera som Såld"
	 * markassold_unmark            => "Avmarkera som Såld"
	 * markassold_marked_msg        => "Detta ämne har markerats som sålt."
	 * markassold_unmarked_msg      => "Detta ämne har avmarkerats som sålt."
	 * markassold_no_permission     => "Du har inte behörighet att utföra denna åtgärd."
	 * markassold_invalid_topic     => "Ämnet kunde inte hittas."
	 * markassold_settings_title    => "Inställningar för Markera som Såld"
	 * markassold_forums            => "Aktiverade forum"
	 * markassold_tag               => "Taggnamn"
	 * markassold_autolock          => "Lås ämne automatiskt"
	 * markassold_bg_color          => "Bakgrundsfärg för tagg"
	 * markassold_text_color        => "Textfärg för tagg"
	 */
);
