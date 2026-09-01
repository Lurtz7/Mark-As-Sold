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

	// Menu items (content menu button labels), %s is the tag name
	'markassold_mark'				=> "Mark as %s",
	'markassold_unmark'				=> "Unmark as %s",

	// Confirmation dialog sub-message when the slot auto-locks
	'markassold_confirm_lock'		=> "The topic will also be locked.",
	'markassold_confirm_unlock'		=> "The topic may also be unlocked.",

	// Flash messages after toggle
	'markassold_marked_msg'				=> "This topic has been marked.",
	'markassold_unmarked_msg'			=> "This topic has been unmarked.",
	'markassold_marked_not_locked'		=> "This topic has been marked, but you do not have permission to lock it.",
	'markassold_unmarked_not_unlocked'	=> "This topic has been unmarked, but you do not have permission to unlock it.",

	// Error messages
	'markassold_no_permission'		=> "You do not have permission to perform this action.",
	'markassold_invalid_topic'		=> "The topic could not be found.",
	'markassold_invalid_tag'		=> "The requested tag is not configured for this forum.",
	'markassold_tag_missing'		=> "The configured tag does not exist (or is disabled) under AdminCP > Community > Tags. Ask an administrator to check the Mark As Sold settings.",
	'markassold_tag_failed'			=> "The tag could not be applied. Please try again or contact an administrator.",

	// Admin settings page
	'markassold_settings_title'		=> "Mark As Sold Settings",
	'markassold_forums'				=> "Enabled Forums",
	'markassold_forums_desc'		=> "Select which forums show the button. Sub-forums are not included automatically, select each forum explicitly. Leave empty to disable this tag.",
	'markassold_tag'				=> "Tag Name",
	'markassold_tag_desc'			=> "The tag to apply. Must match an existing, enabled tag under AdminCP > Community > Tags. Supports any language (e.g. \"Såld\" for Swedish). Leave empty to disable this tag.",
	'markassold_autolock'			=> "Auto-lock Topic",
	'markassold_autolock_desc'		=> "Lock the topic when this tag is applied and unlock it when removed, using the normal moderation action. Topic authors can only lock and unlock their own topics if their member group has \"Can lock and unlock own content?\" enabled; moderators use their forum permissions.",
	'markassold_bg_color'			=> "Tag Background Color",
	'markassold_bg_color_desc'		=> "Background color for the tag badge. The style applies wherever this tag is displayed on the site.",
	'markassold_text_color'			=> "Tag Text Color",
	'markassold_text_color_desc'	=> "Text color for the tag badge.",

	// Tag 2 settings
	'markassold_tag2_header'		=> "Second Tag",
	'markassold_forums2'			=> "Enabled Forums (Tag 2)",
	'markassold_forums2_desc'		=> "Select which forums show the second tag button. Sub-forums are not included automatically. Leave empty to disable.",
	'markassold_tag2'				=> "Tag Name (Tag 2)",
	'markassold_tag2_desc'			=> "The second tag (e.g. \"Bought\" / \"Köpt\"). Must match an existing, enabled tag and must differ from Tag 1. Leave empty to disable.",
	'markassold_autolock2'			=> "Auto-lock Topic (Tag 2)",
	'markassold_autolock2_desc'		=> "Lock the topic when this tag is applied and unlock it when removed. Requires the same lock permissions as for Tag 1 (\"Can lock and unlock own content?\" for topic authors). If both tags auto-lock, the topic stays locked while either tag remains.",
	'markassold_bg_color2'			=> "Tag Background Color (Tag 2)",
	'markassold_bg_color2_desc'		=> "Background color for the second tag badge.",
	'markassold_text_color2'		=> "Tag Text Color (Tag 2)",
	'markassold_text_color2_desc'	=> "Text color for the second tag badge.",

	// Admin settings validation and errors
	'markassold_tag_not_found'		=> "No enabled tag with this name exists. Create it under AdminCP > Community > Tags first.",
	'markassold_tag_duplicate'		=> "Tag 2 must be different from Tag 1.",
	'markassold_settings_missing'	=> "Some Mark As Sold settings are missing from the database. Upload the application package again through AdminCP > System > Applications so the settings are installed, then try again.",

	// AdminCP restrictions and log
	'r__markassold_settings_manage'	=> "Can manage Mark As Sold settings?",
	'acplog__markassold_settings'	=> "Updated Mark As Sold settings",

	// AdminCP menu
	'menu__markassold_settings'				=> "Mark As Sold",
	'menu__markassold_settings_settings'	=> "Settings",
	'menutab__markassold'					=> "Mark As Sold",
	'menutab__markassold_icon'				=> "tag",

	/*
	 * Swedish reference translations. Add them via AdminCP > System > Languages > [Svenska] > Translate.
	 * Keep the %s placeholder in markassold_mark / markassold_unmark: it is replaced by the tag name,
	 * so both tag buttons get the right label.
	 *
	 * markassold_mark              => "Markera som %s"
	 * markassold_unmark            => "Avmarkera som %s"
	 * markassold_confirm_lock      => "Ämnet kommer också att låsas."
	 * markassold_confirm_unlock    => "Ämnet kan också komma att låsas upp."
	 * markassold_marked_msg        => "Ämnet har markerats."
	 * markassold_unmarked_msg      => "Ämnet har avmarkerats."
	 * markassold_marked_not_locked => "Ämnet har markerats, men du har inte behörighet att låsa det."
	 * markassold_unmarked_not_unlocked => "Ämnet har avmarkerats, men du har inte behörighet att låsa upp det."
	 * acplog__markassold_settings  => "Uppdaterade inställningar för Mark As Sold"
	 * markassold_no_permission     => "Du har inte behörighet att utföra denna åtgärd."
	 * markassold_invalid_topic     => "Ämnet kunde inte hittas."
	 * markassold_invalid_tag       => "Den begärda taggen är inte konfigurerad för detta forum."
	 * markassold_tag_missing       => "Den konfigurerade taggen finns inte (eller är inaktiverad) under AdminCP > Community > Tags. Be en administratör kontrollera inställningarna för Mark As Sold."
	 * markassold_tag_failed        => "Taggen kunde inte läggas till. Försök igen eller kontakta en administratör."
	 * markassold_settings_title    => "Inställningar för Mark As Sold"
	 * markassold_forums            => "Aktiverade forum"
	 * markassold_forums_desc       => "Välj vilka forum som visar knappen. Underforum ingår inte automatiskt. Lämna tomt för att stänga av taggen."
	 * markassold_tag               => "Taggnamn"
	 * markassold_tag_desc          => "Taggen som används. Måste matcha en befintlig, aktiverad tagg under AdminCP > Community > Tags. Lämna tomt för att stänga av taggen."
	 * markassold_autolock          => "Lås ämne automatiskt"
	 * markassold_autolock_desc     => "Lås ämnet när taggen sätts och lås upp när den tas bort, via den vanliga moderatoråtgärden. Ämnesskapare kan bara låsa och låsa upp egna ämnen om deras medlemsgrupp har \"Can lock and unlock own content?\" aktiverat."
	 * markassold_bg_color          => "Bakgrundsfärg för tagg"
	 * markassold_bg_color_desc     => "Bakgrundsfärg för taggen. Stilen gäller överallt där taggen visas på sajten."
	 * markassold_text_color        => "Textfärg för tagg"
	 * markassold_text_color_desc   => "Textfärg för taggen."
	 * markassold_tag2_header       => "Andra taggen"
	 * markassold_forums2           => "Aktiverade forum (tagg 2)"
	 * markassold_forums2_desc      => "Välj vilka forum som visar knappen för den andra taggen. Underforum ingår inte automatiskt. Lämna tomt för att stänga av."
	 * markassold_tag2              => "Taggnamn (tagg 2)"
	 * markassold_tag2_desc         => "Den andra taggen (t.ex. \"Köpt\"). Måste matcha en befintlig, aktiverad tagg och skilja sig från tagg 1. Lämna tomt för att stänga av."
	 * markassold_autolock2         => "Lås ämne automatiskt (tagg 2)"
	 * markassold_autolock2_desc    => "Lås ämnet när taggen sätts och lås upp när den tas bort. Om båda taggarna låser förblir ämnet låst så länge någon av dem finns kvar."
	 * markassold_bg_color2         => "Bakgrundsfärg för tagg (tagg 2)"
	 * markassold_bg_color2_desc    => "Bakgrundsfärg för den andra taggen."
	 * markassold_text_color2       => "Textfärg för tagg (tagg 2)"
	 * markassold_text_color2_desc  => "Textfärg för den andra taggen."
	 * markassold_tag_not_found     => "Det finns ingen aktiverad tagg med det namnet. Skapa den först under AdminCP > Community > Tags."
	 * markassold_tag_duplicate     => "Tagg 2 måste skilja sig från tagg 1."
	 * markassold_settings_missing  => "Vissa inställningar för Mark As Sold saknas i databasen. Ladda upp applikationspaketet igen via AdminCP > System > Applications så att inställningarna installeras, och försök sedan igen."
	 */
);
