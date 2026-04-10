# Mark As Sold — Plugin Design Spec

**Platform:** Invision Community 5
**Type:** Plugin
**Date:** 2026-04-10

## Purpose

Replicate the "Mark as Sold" functionality that existed in IPS4 but stopped working in IPS5. Allows topic creators (and moderators/admins) to mark forum topics as sold by toggling a tag. Designed for buy/sell/trade forum categories.

## How It Works

### Admin Setup

1. Admin creates a "Sold" tag in IPS5's Admin CP (standard tag management).
2. In the plugin settings, admin configures:
   - **Enabled forums**: multi-select of forum nodes where the button appears.
   - **Tag name**: the tag to apply when marking as sold (default: "Sold").
   - **Auto-lock**: toggle — automatically lock the topic when marked as sold and unlock when unmarked.
   - **Tag background color**: color picker (default: `#e74c3c` red).
   - **Tag text color**: color picker (default: `#ffffff` white).

### User Experience

1. In an enabled forum, the topic creator sees a **"Mark as Sold"** button in the topic's content menu (the action/moderation dropdown).
2. Clicking it:
   - Adds the configured tag (e.g. "Sold") to the topic.
   - Locks the topic if auto-lock is enabled.
   - The button label changes to **"Unmark as Sold"**.
3. Clicking "Unmark as Sold":
   - Removes the tag from the topic.
   - Unlocks the topic if auto-lock is enabled.
4. Moderators and admins see the button on **any** topic in enabled forums (not just their own).

### Visibility Rules

- The button only appears in admin-configured forums.
- The topic creator can toggle their own topics.
- Members with moderator/admin permissions can toggle any topic in enabled forums.
- The button does not appear for regular members viewing someone else's topic.

## Architecture

### Plugin File Structure

```
Mark-As-Sold/
├── dev/
│   ├── lang.php                     # Language strings
│   ├── settings.php                 # Plugin settings definitions
│   ├── css/
│   │   └── markassold.css           # Custom tag styling
│   └── setup/
│       └── install.php              # Installation code
├── extensions/
│   └── core/
│       └── ContentMenu/
│           └── MarkAsSold.php       # Adds button to topic content menu
├── modules/
│   └── front/
│       └── markassold/
│           └── markassold.php       # Controller handling the toggle action
└── plugin.json                      # Plugin metadata
```

### Components

#### 1. ContentMenu Extension (`extensions/core/ContentMenu/MarkAsSold.php`)

Registers the "Mark as Sold" / "Unmark as Sold" button in the topic action menu.

**Logic:**
- Check if the current content item is an `\IPS\forums\Topic`.
- Check if the topic's forum is in the list of enabled forums (from plugin settings).
- Check permissions: is the viewer the topic author, or do they have moderator permissions?
- If the topic already has the configured tag, show "Unmark as Sold". Otherwise show "Mark as Sold".
- The button links to the front-end controller action.

#### 2. Front Controller (`modules/front/markassold/markassold.php`)

Handles the actual toggle action when the button is clicked.

**Logic:**
- Validate CSRF token.
- Load the topic by ID.
- Verify permissions (same checks as the ContentMenu extension).
- Toggle:
  - If not sold: add the configured tag to the topic's tags. If auto-lock is enabled, lock the topic.
  - If sold: remove the configured tag from the topic's tags. If auto-lock is enabled, unlock the topic.
- Redirect back to the topic with a flash message ("Topic marked as sold" / "Topic unmarked as sold").

#### 3. Plugin Settings (`dev/settings.php`)

| Setting Key | Type | Default | Description |
|---|---|---|---|
| `markassold_forums` | Multi-select (forum nodes) | Empty | Forums where the button appears |
| `markassold_tag` | Text | `Sold` | Tag name to apply (supports any language, e.g. "Såld") |
| `markassold_autolock` | Toggle | On | Lock topic when marked as sold |
| `markassold_bg_color` | Color | `#e74c3c` | Tag background color |
| `markassold_text_color` | Color | `#ffffff` | Tag text color |

#### 4. Language Strings (`dev/lang.php`)

The plugin supports IPS5's language system. The admin-facing tag name setting accepts any UTF-8 string (e.g. "Såld" for Swedish). The UI strings below are the English defaults — additional languages (e.g. Swedish) are added via IPS5's standard language pack system.

**English:**

| Key | Value |
|---|---|
| `markassold_mark` | Mark as Sold |
| `markassold_unmark` | Unmark as Sold |
| `markassold_marked_msg` | This topic has been marked as sold. |
| `markassold_unmarked_msg` | This topic has been unmarked as sold. |
| `markassold_forums_setting` | Enabled Forums |
| `markassold_forums_setting_desc` | Select which forums show the Mark as Sold button. |
| `markassold_tag_setting` | Tag Name |
| `markassold_tag_setting_desc` | The tag to apply when a topic is marked as sold. Must match an existing tag in Admin CP. Supports any language (e.g. "Såld"). |
| `markassold_autolock_setting` | Auto-lock Topic |
| `markassold_autolock_setting_desc` | Automatically lock the topic when marked as sold, and unlock when unmarked. |
| `markassold_bg_color_setting` | Tag Background Color |
| `markassold_text_color_setting` | Tag Text Color |

**Swedish (included by default):**

| Key | Value |
|---|---|
| `markassold_mark` | Markera som Såld |
| `markassold_unmark` | Avmarkera som Såld |
| `markassold_marked_msg` | Detta ämne har markerats som sålt. |
| `markassold_unmarked_msg` | Detta ämne har avmarkerats som sålt. |
| `markassold_forums_setting` | Aktiverade forum |
| `markassold_forums_setting_desc` | Välj vilka forum som visar knappen Markera som Såld. |
| `markassold_tag_setting` | Taggnamn |
| `markassold_tag_setting_desc` | Taggen som används när ett ämne markeras som sålt. Måste matcha en befintlig tagg i Admin CP. |
| `markassold_autolock_setting` | Lås ämne automatiskt |
| `markassold_autolock_setting_desc` | Lås automatiskt ämnet när det markeras som sålt, och lås upp vid avmarkering. |
| `markassold_bg_color_setting` | Bakgrundsfärg för tagg |
| `markassold_text_color_setting` | Textfärg för tagg |

#### 5. Custom CSS (`dev/css/markassold.css`)

A small CSS snippet that targets the "Sold" tag element by matching the configured tag name, applying the admin-configured background and text colors. This makes the "Sold" tag visually distinct from regular tags (e.g. a bold red badge).

The CSS is injected through IPS5's standard plugin CSS loading mechanism. The color values from settings are applied inline or via a small dynamic style block.

#### 6. Installation (`dev/setup/install.php`)

Minimal — sets default values for plugin settings. No custom database tables required since we use the native tagging system.

## What This Plugin Does NOT Do

- No auto-move to another forum.
- No custom database tables — relies entirely on IPS5's native tag system.
- No per-group permission configuration beyond the creator + moderator distinction.
- No notification system when a topic is marked/unmarked.
- No bulk operations (mass mark/unmark).

## Dependencies

- IPS5's global tag system — the configured tag must exist in Admin CP.
- IPS5's ContentMenu extension point — for adding the button.
- IPS5's `\IPS\forums\Topic` class — for tag manipulation and lock/unlock.

## Testing Approach

1. Install plugin on a dev IPS5 instance.
2. Create the "Sold" tag in Admin CP.
3. Configure the plugin: enable specific forums, set auto-lock on.
4. As a topic creator: mark a topic as sold — verify tag appears and topic locks.
5. As the same creator: unmark — verify tag removed and topic unlocks.
6. As a different regular member: verify the button is not visible.
7. As a moderator: verify the button is visible and functional on other users' topics.
8. In a non-enabled forum: verify the button does not appear.
9. Toggle auto-lock off: verify marking only adds the tag without locking.
10. Verify the tag has custom styling (colored badge).
