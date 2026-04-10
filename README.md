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
