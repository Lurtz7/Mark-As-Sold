# Mark As Sold — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an IPS5 mini-application that lets topic creators (and moderators) toggle a "Sold" tag on forum topics, with admin-configurable forums, auto-lock, and custom tag styling.

**Architecture:** IPS5 mini-application (not a plugin — IPS5 removed code hooks that plugins relied on, so ContentMenu extensions require the application framework). The app uses IPS5's ContentMenu extension to add a toggle button, a front controller to handle the action, and the native tag system for the "Sold" indicator. Admin settings control which forums are enabled, auto-lock behavior, and tag colors.

**Tech Stack:** PHP 8.1+, IPS5 framework, IPS5 tag system, IPS5 ContentMenu extension API

**Important Note:** The IPS5 developer documentation is behind authentication, so some API calls are based on IPS conventions and publicly available examples. Comments in the code mark areas that may need adjustment against the actual IPS5 source. The user should verify these against their IPS5 installation's source code (particularly `system/Content/Item.php`, `applications/forums/sources/Topic/Topic.php`, and `system/Content/Tags.php`).

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `Application.php` | Create | Application class, icon |
| `data/settings.json` | Create | Setting key definitions |
| `data/furl.json` | Create | Friendly URL mappings |
| `data/versions.json` | Create | Version history |
| `dev/lang.php` | Create | English + Swedish language strings |
| `dev/css/front/markassold.css` | Create | Custom tag styling |
| `extensions/core/ContentMenu/MarkAsSold.php` | Create | Adds button to topic menu |
| `modules/front/markassold/toggle.php` | Create | Handles mark/unmark toggle |
| `modules/admin/settings/settings.php` | Create | Admin settings form |
| `dev/setup/install.php` | Create | Installation defaults |

---

### Task 1: Application Skeleton

**Files:**
- Create: `Application.php`
- Create: `data/settings.json`
- Create: `data/furl.json`
- Create: `data/versions.json`

This task creates the bare minimum for IPS5 to recognize the application.

- [ ] **Step 1: Create `Application.php`**

This is the main application class. IPS autoloads it from the application directory.

```php
<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold;

class _Application extends \IPS\Application
{
	/**
	 * Icon for the application in AdminCP
	 *
	 * @return	string	Font Awesome icon name
	 */
	protected function get__icon(): string
	{
		return 'tag';
	}
}
```

- [ ] **Step 2: Create `data/settings.json`**

Defines the setting keys and their defaults. IPS reads this during installation.

```json
[
	{
		"key": "markassold_forums",
		"default": ""
	},
	{
		"key": "markassold_tag",
		"default": "Sold"
	},
	{
		"key": "markassold_autolock",
		"default": "1"
	},
	{
		"key": "markassold_bg_color",
		"default": "#e74c3c"
	},
	{
		"key": "markassold_text_color",
		"default": "#ffffff"
	}
]
```

- [ ] **Step 3: Create `data/furl.json`**

Friendly URL mapping for the toggle controller.

```json
{
	"topLevel": {},
	"pages": {
		"markassold_toggle": {
			"friendly": "markassold/toggle/{id}",
			"real": "app=markassold&module=markassold&controller=toggle"
		}
	}
}
```

- [ ] **Step 4: Create `data/versions.json`**

Version history for the application.

```json
{
	"10000": "1.0.0"
}
```

- [ ] **Step 5: Commit**

```bash
git add Application.php data/
git commit -m "feat: add application skeleton with settings and URL definitions"
```

---

### Task 2: Language Strings

**Files:**
- Create: `dev/lang.php`

All user-facing and admin-facing text. English is the primary language. Swedish translations are included as comments — the admin adds them via the IPS Language system in AdminCP (Languages > Translate), since IPS handles multi-language via the language pack system, not duplicate lang.php entries.

- [ ] **Step 1: Create `dev/lang.php`**

```php
<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

$lang = array(

	'__app_markassold'				=> "Mark As Sold",

	// Module names
	'module__markassold_markassold'	=> "Mark As Sold",
	'module__markassold_settings'	=> "Settings",

	// Menu items (content menu button labels)
	'markassold_mark'				=> "Mark as Sold",
	'markassold_unmark'				=> "Unmark as Sold",

	// Flash messages after toggle
	'markassold_marked_msg'			=> "This topic has been marked as sold.",
	'markassold_unmarked_msg'		=> "This topic has been unmarked as sold.",

	// Error messages
	'markassold_no_permission'		=> "You do not have permission to perform this action.",
	'markassold_invalid_topic'		=> "The topic could not be found.",

	// Admin settings page
	'markassold_settings_title'				=> "Mark As Sold Settings",
	'markassold_forums_setting'				=> "Enabled Forums",
	'markassold_forums_setting_desc'		=> "Select which forums show the Mark as Sold button. Leave empty to disable.",
	'markassold_tag_setting'				=> "Tag Name",
	'markassold_tag_setting_desc'			=> "The tag to apply when a topic is marked as sold. Must match an existing tag created in AdminCP > Community > Tags. Supports any language (e.g. \"Såld\" for Swedish).",
	'markassold_autolock_setting'			=> "Auto-lock Topic",
	'markassold_autolock_setting_desc'		=> "Automatically lock the topic when marked as sold, and unlock when unmarked.",
	'markassold_bg_color_setting'			=> "Tag Background Color",
	'markassold_bg_color_setting_desc'		=> "Background color for the Sold tag badge.",
	'markassold_text_color_setting'			=> "Tag Text Color",
	'markassold_text_color_setting_desc'	=> "Text color for the Sold tag badge.",

	// AdminCP menu
	'menu__markassold_settings'				=> "Settings",
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
	 * markassold_forums_setting    => "Aktiverade forum"
	 * markassold_tag_setting       => "Taggnamn"
	 * markassold_autolock_setting  => "Lås ämne automatiskt"
	 * markassold_bg_color_setting  => "Bakgrundsfärg för tagg"
	 * markassold_text_color_setting => "Textfärg för tagg"
	 */
);
```

- [ ] **Step 2: Commit**

```bash
git add dev/lang.php
git commit -m "feat: add English language strings with Swedish translation reference"
```

---

### Task 3: Admin Settings Page

**Files:**
- Create: `modules/admin/settings/settings.php`

The admin settings controller renders a form where the admin picks which forums are enabled, the tag name, auto-lock toggle, and colors.

- [ ] **Step 1: Create `modules/admin/settings/settings.php`**

```php
<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\modules\admin\settings;

use IPS\Dispatcher;
use IPS\Output;
use IPS\Member;
use IPS\Settings;
use IPS\Helpers\Form;

class _settings extends \IPS\Dispatcher\Controller
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
			Settings::i()->markassold_forums ? explode( ',', Settings::i()->markassold_forums ) : array(),
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
			Settings::i()->markassold_tag ?: 'Sold',
			TRUE
		) );

		/* Auto-lock toggle */
		$form->add( new Form\YesNo(
			'markassold_autolock',
			Settings::i()->markassold_autolock,
			FALSE
		) );

		/* Tag background color */
		$form->add( new Form\Color(
			'markassold_bg_color',
			Settings::i()->markassold_bg_color ?: '#e74c3c',
			FALSE
		) );

		/* Tag text color */
		$form->add( new Form\Color(
			'markassold_text_color',
			Settings::i()->markassold_text_color ?: '#ffffff',
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
```

- [ ] **Step 2: Commit**

```bash
git add modules/admin/settings/settings.php
git commit -m "feat: add admin settings page with forum selector, tag name, auto-lock, and colors"
```

---

### Task 4: ContentMenu Extension

**Files:**
- Create: `extensions/core/ContentMenu/MarkAsSold.php`

This is the IPS5 way to add items to content menus. The extension checks permissions and the forum configuration, then adds a "Mark as Sold" or "Unmark as Sold" link.

**Note:** The exact ContentMenu extension API may vary. If `\IPS\Content\Menu\Link` does not exist in your IPS5 version, check `system/Content/Menu/` for the available classes. The pattern below follows IPS5 conventions from the dev blog.

- [ ] **Step 1: Create `extensions/core/ContentMenu/MarkAsSold.php`**

```php
<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\extensions\core\ContentMenu;

use IPS\Member;
use IPS\Settings;

class _MarkAsSold
{
	/**
	 * Modify the content item menu
	 *
	 * @param	\IPS\Content\Item	$item	The content item
	 * @param	array				$items	Existing menu items (passed by reference or returned)
	 * @return	array				Menu items to add
	 */
	public function items( \IPS\Content\Item $item ): array
	{
		$menuItems = array();

		/* Only for forum topics */
		if ( !( $item instanceof \IPS\forums\Topic ) )
		{
			return $menuItems;
		}

		/* Check if this forum is enabled */
		$enabledForums = Settings::i()->markassold_forums;
		if ( empty( $enabledForums ) )
		{
			return $menuItems;
		}

		$enabledForumIds = explode( ',', $enabledForums );
		if ( !\in_array( $item->forum_id, $enabledForumIds ) )
		{
			return $menuItems;
		}

		/* Check permissions: topic author or moderator */
		$member = Member::loggedIn();
		if ( !$member->member_id )
		{
			return $menuItems;
		}

		$isAuthor    = ( (int) $item->starter_id === (int) $member->member_id );
		$isModerator = $member->modPermission( 'can_close_open' ) || $member->isAdmin();

		if ( !$isAuthor && !$isModerator )
		{
			return $menuItems;
		}

		/* Determine current sold state */
		$tagName = Settings::i()->markassold_tag ?: 'Sold';
		$currentTags = $item->tags() ?: array();
		$isSold = \in_array( $tagName, $currentTags );

		/* Build the toggle URL with CSRF token */
		$url = \IPS\Http\Url::internal(
			"app=markassold&module=markassold&controller=toggle&id={$item->tid}",
			'front'
		)->csrf();

		/*
		 * Add menu item.
		 * IPS5 ContentMenu API — if \IPS\Content\Menu\Link does not exist,
		 * return an array with 'url', 'title', 'icon' keys instead, or check
		 * your IPS5 source at system/Content/Menu/ for the correct class.
		 */
		$menuItems['markassold'] = array(
			'url'   => $url,
			'title' => Member::loggedIn()->language()->addToStack(
				$isSold ? 'markassold_unmark' : 'markassold_mark'
			),
			'icon'  => $isSold ? 'times' : 'tag',
		);

		return $menuItems;
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add extensions/core/ContentMenu/MarkAsSold.php
git commit -m "feat: add ContentMenu extension for mark/unmark sold button"
```

---

### Task 5: Toggle Controller

**Files:**
- Create: `modules/front/markassold/toggle.php`

This controller handles the actual mark/unmark action. It loads the topic, verifies permissions, toggles the tag, optionally locks/unlocks, and redirects back.

- [ ] **Step 1: Create `modules/front/markassold/toggle.php`**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add modules/front/markassold/toggle.php
git commit -m "feat: add toggle controller for mark/unmark sold with tag manipulation and auto-lock"
```

---

### Task 6: Custom CSS for Tag Styling

**Files:**
- Create: `dev/css/front/markassold.css`

Since the tag name and colors are dynamic (admin-configurable), we use a CSS class approach. The CSS styles a tag element with a specific data attribute or class. IPS5 renders tags as elements we can target.

The dynamic colors are applied via a small inline style block. The CSS file provides the base shape/styling, and the admin-configured colors are injected as CSS custom properties via a theme hook or global output.

- [ ] **Step 1: Create `dev/css/front/markassold.css`**

```css
/**
 * Mark As Sold — Tag Styling
 *
 * Targets the sold tag in IPS5's tag display.
 * The --markassold-bg and --markassold-text custom properties
 * are set dynamically from plugin settings.
 *
 * IPS5 renders tags as <a> elements within a tag list.
 * We target by the data-tag attribute or tag text content.
 * Adjust the selector below if your IPS5 theme uses different markup.
 */

:root {
	--markassold-bg: #e74c3c;
	--markassold-text: #ffffff;
}

/* Target the sold tag — IPS5 typically renders tags with a data-tag attribute */
.ipsTag[data-tag="Sold"],
.ipsTag[data-tag="Såld"],
.ipsTag.ipsTag--sold {
	background-color: var(--markassold-bg) !important;
	color: var(--markassold-text) !important;
	font-weight: 700;
	border: none !important;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
```

- [ ] **Step 2: Update `Application.php` to inject dynamic CSS variables**

Add a method to output the dynamic CSS custom properties based on admin settings. Add this method to the existing `Application.php`:

```php
	/**
	 * Output CSS custom properties for tag colors
	 *
	 * Called from IPS output. Adds an inline style block with the
	 * admin-configured colors as CSS custom properties.
	 *
	 * @return void
	 */
	public function outputCss(): void
	{
		$bgColor   = \IPS\Settings::i()->markassold_bg_color ?: '#e74c3c';
		$textColor = \IPS\Settings::i()->markassold_text_color ?: '#ffffff';
		$tagName   = \IPS\Settings::i()->markassold_tag ?: 'Sold';

		\IPS\Output::i()->headCss .= <<<CSS
<style>
:root {
	--markassold-bg: {$bgColor};
	--markassold-text: {$textColor};
}
.ipsTag[data-tag="{$tagName}"] {
	background-color: var(--markassold-bg) !important;
	color: var(--markassold-text) !important;
	font-weight: 700;
	border: none !important;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
</style>
CSS;
	}
```

**Note:** The exact mechanism for injecting CSS may differ in IPS5. If `headCss` is not available, use `\IPS\Output::i()->cssFiles` or add CSS via the `outputHead` event listener. Check your IPS5 source at `system/Output/Output.php` for available properties.

- [ ] **Step 3: Commit**

```bash
git add dev/css/front/markassold.css Application.php
git commit -m "feat: add custom CSS for sold tag with dynamic color variables"
```

---

### Task 7: Installation Script

**Files:**
- Create: `dev/setup/install.php`

Minimal installation — just ensures default settings are in place. The `data/settings.json` handles most of this, but the install script can run any first-time setup.

- [ ] **Step 1: Create `dev/setup/install.php`**

```php
<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold\setup;

/**
 * Installation
 */
function step1(): bool
{
	/*
	 * Settings are automatically created from data/settings.json.
	 * This install step is a placeholder for any future database
	 * changes or initial setup logic.
	 *
	 * No custom tables needed — we use IPS5's native tag system.
	 */
	return TRUE;
}
```

- [ ] **Step 2: Commit**

```bash
git add dev/setup/install.php
git commit -m "feat: add installation script"
```

---

### Task 8: Final Review and Documentation

**Files:**
- Create: `README.md`

- [ ] **Step 1: Create `README.md`**

```markdown
# Mark As Sold — IPS5 Application

Allows topic creators (and moderators) to mark forum topics as "Sold" by toggling a tag. Designed for buy/sell/trade forum categories in Invision Community 5.

## Features

- **Mark/Unmark as Sold** button in the topic action menu
- Admin-configurable: choose which forums show the button
- Optional auto-lock when a topic is marked as sold
- Customizable tag name (supports any language, e.g. "Såld")
- Custom tag styling with configurable colors
- Moderator/admin override: can mark any topic, not just their own

## Requirements

- Invision Community 5.x
- PHP 8.1+

## Installation

1. Copy the `markassold` folder to your IPS installation's `applications/` directory.
2. In AdminCP, go to **System > Site Features > Applications**.
3. Click **Install** and select the Mark As Sold application.

## Setup

1. **Create the tag:** Go to **AdminCP > Community > Tags** and create a tag matching your desired label (e.g. "Sold" or "Såld").
2. **Configure the plugin:** Go to **AdminCP > Mark As Sold > Settings**:
   - Select which forums should show the Mark as Sold button.
   - Set the tag name to match the tag you created.
   - Toggle auto-lock on/off.
   - Choose your preferred tag colors.

## Usage

- In an enabled forum, the topic creator will see a **"Mark as Sold"** option in the topic's action menu.
- Clicking it adds the configured tag and optionally locks the topic.
- Clicking **"Unmark as Sold"** reverses the action.
- Moderators and admins can mark/unmark any topic in enabled forums.

## Swedish / Internationalization

The application ships with English strings. To add Swedish (or any other language):

1. Go to **AdminCP > System > Languages**.
2. Select your Swedish language pack.
3. Click **Translate** and search for keys starting with `markassold_`.
4. Add your translations. Reference translations are in the comments at the bottom of `dev/lang.php`.

## Development Notes

This is built as an IPS5 mini-application (not a plugin) because IPS5 removed code hooks (monkey patching) that the old IPS4 plugins relied on. The application framework provides ContentMenu extensions and proper controller routing.

### Verification Points

Some IPS5 API calls may need adjustment depending on your exact IPS5 version. Check these files in your IPS5 installation if something doesn't work:

- `system/Content/Tags.php` — tag manipulation methods (`setTags`, `tags`)
- `system/Content/Item.php` — content item base class
- `applications/forums/sources/Topic/Topic.php` — topic model (`starter_id`, `forum_id`, `state`, `tid`)
- `system/Content/Menu/` — ContentMenu extension API
- `system/Output/Output.php` — CSS injection methods

## License

MIT
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add README with installation, setup, and development notes"
```

---

## Verification Checklist

After deploying to an IPS5 instance, manually verify:

1. **Installation**: App installs without errors from AdminCP.
2. **Settings page**: All 5 settings render correctly and save.
3. **Button visibility**: "Mark as Sold" appears only in enabled forums, only for the topic creator and moderators.
4. **Mark as Sold**: Clicking adds the tag, locks topic (if enabled), shows flash message.
5. **Unmark as Sold**: Clicking removes the tag, unlocks topic (if enabled), shows flash message.
6. **Permission denied**: Regular members cannot see the button on other users' topics.
7. **Disabled forum**: Button does not appear in non-enabled forums.
8. **Tag styling**: The sold tag renders with the configured colors.
9. **Custom tag name**: Changing the tag name setting (e.g. to "Såld") works correctly.
10. **Auto-lock off**: When disabled, marking only adds the tag without locking.
