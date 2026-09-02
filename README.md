# Mark As Sold — IPS5 Application

Allows topic creators (and moderators) to mark forum topics as "Sold" by toggling a tag. Designed for buy/sell/trade forum categories in Invision Community 5.

## Features

- **Mark/Unmark** button in the topic action menu, one per configured tag
- Two independent tag slots (e.g. "Sold" and "Bought"), each with its own forums, colours and auto-lock
- Admin-configurable: choose which forums show each button
- Optional auto-lock when a topic is marked, using IPS's normal moderation action (logged, permission-checked)
- Customisable tag names (any language, e.g. "Såld")
- Custom badge styling with configurable colours
- Moderators with lock/unlock permission in the forum can mark any topic, not just their own

## Requirements

- Invision Community 5.0.18 or later (verified against 5.0.18, 5.0.19 and 5.0.20 Beta 2)
- PHP 8.1+

## Installation

The git repository is the development source. It cannot be installed by copying the folder: the language strings and theme resources are only generated when the application is built.

1. On a development install (`IN_DEV` enabled), place the repository at `applications/markassold/`, then go to **AdminCP > System > Site Features > Applications > Developer Center > Mark As Sold** and click **Build**. This produces a `.tar` package.
2. On the production site, go to **AdminCP > System > Site Features > Applications** and upload the `.tar` (use **Upload** on the existing entry when updating).

Always update through the AdminCP upload. Copying files over FTP skips the install routine, so new settings never get their database rows and the settings page silently fails to save them.

## Setup

1. **Create the tags:** go to **AdminCP > Community > Tags** and create a tag for each slot you want to use (e.g. "Sold" or "Såld", and optionally "Bought" / "Köpt"). The tags must be enabled.
2. **Configure the application:** go to **AdminCP > Mark As Sold > Settings**:
   - Select the forums that should show each button. Sub-forums are not included automatically.
   - Enter the tag name for each slot. It must match an existing enabled tag. Tag 2 must differ from tag 1. Leave a tag name empty to disable that slot.
   - Toggle auto-lock per slot.
   - Choose the badge colours.
3. **Auto-lock needs no member permissions.** When a topic author marks their topic, the application locks it on their behalf and records that it did so (table `markassold_locks`, plus a moderator-log entry). Unmarking releases only that recorded lock. A topic locked by a moderator, or re-locked by a moderator after the sale, stays locked and the member is told so. Members who already hold lock permissions (moderators, or groups with "Can lock and unlock own content?") go through IPS's normal lock action instead, so nothing changes for them.

## Usage

- In an enabled forum, the topic creator sees a **"Mark as Sold"** option in the topic's action menu.
- Clicking it adds the configured tag and, if auto-lock is enabled, locks the topic.
- Clicking **"Unmark as Sold"** removes the tag and unlocks the topic, unless another auto-lock tag is still applied or the lock was applied by a moderator.
- Moderators with lock/unlock permission in the forum can mark or unmark any topic there.
- The button is not shown for hidden, pending, moved or merged topics, in forums where tagging is disabled, or to members who are restricted from posting or have an unacknowledged warning.
- On a locked topic the author only sees the button if the lock is the application's own (or IPS would let them unlock it themselves). Moderator locks are never undone by authors.

## Styling

The badge colours are injected into the page head on pages where the topic row or topic menu is rendered. The rule matches the tag by name, so the badge is styled wherever that tag appears on the site, not only in the configured forums.

## Swedish / Internationalisation

The application ships with English strings. To add Swedish (or any other language):

1. Go to **AdminCP > System > Languages**.
2. Select the language pack and click **Translate**, then search for keys starting with `markassold_`.
3. Add your translations. Reference translations are in the comment block at the bottom of `dev/lang.php`.

Keep the `%s` placeholder in `markassold_mark` and `markassold_unmark`. It is replaced by the tag name, so both tag buttons get the right label.

## Development Notes

This is an IPS5 application (not a plugin) because IPS5 removed the code hooks that IPS4 plugins relied on. It uses:

- a `UIItem` extension (`extensions/core/UIItem/MarkAsSold.php`) for the menu button and badge CSS,
- a front controller (`modules/front/markassold/toggle.php`) for the toggle action,
- IPS's native tag system and `modAction('lock'/'unlock')` for locking,
- pure decision logic in `sources/TagLogic/TagLogic.php`.

### Tests

The decision logic has plain-PHP tests that run without an IPS installation:

```
php dev/tests/run.php
```

On a CLI without a `php.ini` (for example Laragon's bundled PHP) enable mbstring explicitly:

```
php -d extension_dir=<php-dir>/ext -d extension=mbstring dev/tests/run.php
```

### Deploying

`dev/tools/deploy.ps1` does the whole release from a Windows machine with a local `IN_DEV` install (Laragon):

```
pwsh dev/tools/deploy.ps1                   # build, rehearse the upgrade locally, deploy over SSH
pwsh dev/tools/deploy.ps1 -BuildOnly        # only produce the .tar for manual upload via AdminCP
pwsh dev/tools/deploy.ps1 -NoPhpFpmRestart  # skip the php-fpm restart at the end
```

It runs the unit tests, syncs the repository into the local dev install, builds the package the same way Developer Center > Build does (`dev/tools/build.php`) and checks its contents, then rehearses the upgrade routine against the local dev database before touching the server. On the server it backs up the live app, removes files that are no longer in the package, extracts the package, normalises ownership and permissions, runs the AdminCP upgrade routine as the web server user (`dev/tools/remote-upgrade.php`) and restarts php-fpm so opcache serves the new files. The upgrade routine is what a plain file copy misses: it creates new tables (from `setup/upg_<version>/queries.json`), settings rows, language strings and templates, records the version and clears caches.

Requirements: MySQL running locally (the build and the rehearsal use the dev database), `ssh`/`scp` on this machine, a `php` CLI on the server, and sudo rights there. Server, paths and PHP location are parameters with defaults at the top of the script. Bump `data/versions.json` for every release so the AdminCP shows which build is installed, and put any schema change for that version in `setup/upg_<long version>/queries.json`. The script refuses to deploy an uncommitted working tree unless `-AllowDirty` is given, and refuses packages that contain custom upgrade routines (`upgrade.php`); those must go through the AdminCP upgrader.

Rollback: the script prints the backup path (`~/markassold-backup-<stamp>.tgz` on the server). Restore it with `sudo tar -xzf <backup> -C <web root>/applications`, then re-upload the previous package in the AdminCP so its settings and language strings are reinstalled. Tables added by a newer version can stay.

If you deploy manually instead, upload the `.tar` through **AdminCP > System > Site Features > Applications > Upload**, never by FTP, and confirm afterwards that all settings rows exist:

```sql
SELECT conf_key, conf_value FROM core_sys_conf_settings WHERE conf_app = 'markassold';
```

  Ten rows are expected.

## License

MIT
