# Hosting Funeral Quest on cPanel

This guide installs the PHP version. The original Windows executable and the Node prototype are not required on the host.

## 1. Prepare the files

Use the [README build instructions](../README.md#build-the-upload-folder) on your computer. Uploading a GitHub source ZIP alone is insufficient: the generated Flash, Ruffle, help pages, and game scripts are intentionally not committed.

After `npm run build:cpanel`, the upload folder contains:

```text
www/
  .htaccess
  index.html
  index.php
  admin.php
  cron.php
  client/
  flash/                 compiled client and artwork
  ruffle/                emulator JavaScript and WebAssembly
  pages/                 original help pages
  licenses/              original and Ruffle notices
  private/
    .htaccess
    settings.php         shared defaults
    settings.local.php   your overrides; create this
    src/                 PHP server
    dist/                scripts and game tables
    data/                created when the game first needs it
```

`router.php` is only for local PHP preview; `private/selftest.php` is the developer test suite. They are not required on cPanel. Keep the example configuration available as a reference.

## 2. Create a domain or subdomain

In cPanel's domain manager, create a domain or subdomain with its own document root, for example `/home/ACCOUNT/fq.example.com/`. Alternatively, use `public_html/` if this game is the main site. Install at the domain root: a URL such as `example.com/games/fq/` needs client path changes and is not supported by these instructions.

Select PHP 8.1 or newer for that domain. The PHP runtime must provide `json`, `iconv`, `password_hash`, and `flock`. There is no database or Composer installation. The host must allow Apache rewrite rules and the access restrictions in `.htaccess`.

Enable AutoSSL or another valid HTTPS certificate. If a proxy sits in front of PHP, configure it to pass the original HTTPS scheme. The application uses `HTTPS` or `X-Forwarded-Proto` when deciding whether cookies need the `Secure` flag.

## 3. Configure your town

Copy `private/settings.local.example.php` to `private/settings.local.php` inside your upload folder, and edit the copy. On Linux/macOS:

```sh
cp www/private/settings.local.example.php www/private/settings.local.php
```

On Windows PowerShell:

```powershell
Copy-Item www/private/settings.local.example.php www/private/settings.local.php
```

The file returns a PHP array. Its values override `private/settings.php`; omit settings you want to keep at their shared defaults. Changes apply on the next PHP request. Do not replace the defaults file with passwords or site-specific values.

```php
<?php
return [
    'timezone' => 'America/Chicago',
    'admin_password' => 'replace-with-your-own-long-password',
    'admin_email' => '',
    'server_news' => 'Welcome to our town!',
];
```

Choose a unique operator password, rather than using the example string. The operator page is disabled while `admin_password` is blank or shorter than 10 characters. `admin_email` is shown publicly on the Server Settings page; use a public contact address or leave it blank. The configured operator password is stored in this PHP configuration file, so keep its backup private.

The full list and comments are in [settings.php](../www/private/settings.php). Common settings are:

| Setting | Default | Purpose |
| --- | --- | --- |
| `timezone` | host timezone | Town dates and the midnight boundary; set it explicitly. |
| `max_players` | 800 | Maximum active parlors. |
| `max_online` | 500 | Maximum logged-on sessions; a configured limit, not a capacity benchmark. |
| `tournament_days` | 0 | No-reset mode; a positive value enables tournament cycles and automatic resets. |
| `ip_check` | true | One player per IP per game day. Shared households and proxies may need this disabled. |
| `idle_minutes` | 4 | Inactivity before a player is kicked. |
| `inactive_days` | 15 | Inactive-parlor cleanup threshold in game days. |
| `delete_unsold` | true | Closes offline parlors with no sales during maintenance. |
| `swear_filter` | true | Filters names and chat using the shipped word list. |
| `log_to_disk` | false | Also retains the full server log on disk. |
| `turns_per_day` | 15 | Base customer turns, before hearse bonuses. |
| `yangs_per_day` / `visits_per_day` | 1 / 2 | Daily delivery and parlor-visit limits. |

Daily bonuses, starting stats, tag transfers, name-change costs, and event probabilities are also configurable. Tournament resets clear the town on schedule: back up before enabling tournament mode on an existing town. A trusted reverse proxy must preserve meaningful client addresses if you use the IP rule; the game reads `REMOTE_ADDR` and does not accept a client-supplied forwarding header as an address.

## 4. Upload and check access

Upload the **contents** of `www/`, not the outer folder, into the document root. In cPanel File Manager, enable **Show Hidden Files** so you can see and transfer `.htaccess`. Do not upload the repository, `node_modules/`, `.git/`, `upstream/`, `.tools/`, or development screenshots into the web root.

The top-level `.htaccess` routes `request.xp` calls and the dynamic settings/rankings pages to PHP. `private/.htaccess` denies public access to configuration, code, and data. Both files matter.

The PHP process needs write access to `private/data/`. It creates that directory when absent, provided its parent permits it. If needed, create it using cPanel. Typical shared-host permissions are 755 for directories and 644 for files when PHP runs as your account; use your provider's ownership guidance rather than making everything writable with 777.

Check these URLs before inviting players:

| URL | Expected result |
| --- | --- |
| `/` | Original client loads through Ruffle. |
| `/pages/intro.htm` | Original help/introduction page. |
| `/pages/server.htm` | Generated server settings. |
| `/pages/rankings.htm` | Generated rankings. |
| `/admin.php` | Operator login, or the disabled-page message. |
| `/private/` | **403 Forbidden**. |
| `/private/settings.php` | **403 Forbidden**. |
| `/cron.php` in a browser | **404**; cron is CLI-only. |

If private paths are reachable, stop setup and correct the host's `.htaccess` support before creating accounts. A blank PHP response is not proof that the directory is protected: check the HTTP status.

Create your first player through the game, finish a sale, and confirm the account can log on again. Each installation starts its own town; it does not share accounts with [the demo](https://fq.usefulinternetco.com/).

## 5. Schedule daily maintenance

In cPanel **Cron Jobs**, run every 15 minutes:

```cron
*/15 * * * * /usr/local/bin/php /home/ACCOUNT/fq.example.com/cron.php
```

Replace both paths with the host's actual PHP CLI executable and document root. Ask the provider for the executable corresponding to PHP 8.1+; its default `php` may use a different version than the website. Keep cron output enabled initially so errors reach you.

The script compares the town's saved date to its configured local date. Repeated runs on the same date do not repeatedly advance the game. Player requests also trigger maintenance; cron ensures quiet towns receive it. With a 15-minute schedule, rollover happens on the first run after midnight, rather than necessarily at exactly 00:00. After multiple days offline, the implementation advances one game day when it detects a changed date; it does not replay every missed calendar day.

## Admin login and server settings

### Enable the operator login

1. Sign into your hosting account's **cPanel** and open **File Manager**.
2. Open the game's document root, then `private/settings.local.php`. This is the deployed file, rather than the `www/` path used in a source checkout. If it is missing, copy `private/settings.local.example.php` to `private/settings.local.php` first.
3. Set `admin_password` to a unique password of at least **10 characters**, and save. Keep the surrounding PHP quotes and comma intact. There is no default operator password; a blank or shorter value keeps the page disabled.
4. In your browser, open `https://YOUR-DOMAIN/admin.php`, replacing `YOUR-DOMAIN` with the domain where you installed the game. For local preview, use `http://127.0.0.1:8091/admin.php`.
5. Enter the configured password in **Operator password**, then click **Log on**. The form has **no username**. It uses a separate operator login, not a player account or your cPanel password.

Use **Log off** when finished. If you forget the operator password, edit `admin_password` in the deployed local configuration through cPanel File Manager, save, and log in with the replacement. Changing it invalidates existing operator sessions. After five wrong passwords from one IP, the login waits up to ten minutes; changing the password does not clear that temporary limit.

### Edit server-wide settings

The operator page currently has **no browser editor for server settings**. To change them, open the deployed `private/settings.local.php` in cPanel File Manager, add or edit the relevant entries in its returned array, then save. The defaults and descriptions are in `private/settings.php`; only put your overrides in the local file.

For example, these entries set the town's base daily turns, maximum parlors, and login news:

```php
    'turns_per_day' => 20,
    'max_players' => 200,
    'server_news' => 'Welcome to our town!',
```

Add them inside the existing `return [ ... ];` array, preserving your timezone, operator password, and other overrides. Do not paste a second `return` array into the file. PHP loads saved settings on the next request; some game rules, such as daily turn allocation, apply when the relevant game action or daily refresh occurs rather than immediately rewriting every player's current stats.

The **Server Settings** link above the game login opens `/pages/server.htm`. It is a public, read-only display of selected settings, not an administrator login or edit form. After saving, you can check that page for the displayed rules, or refresh the game login to check server news. Keep `private/settings.local.php` out of Git and retain it during updates.

### Use the operator controls

After logging into `/admin.php`, the page can edit parlors, inspect online players, kick players, manage bans, read news and logs, force a new day, and reset the game. Force-new-day and reset change live game state; use them intentionally. A player marked **Always allowed on** bypasses the shared-IP rule.

Custom bans are saved in `private/data/ban.txt`; shipped bans live in `private/dist/ban.txt`. Lines have the form `ban|address|comment`. Matching is by substring, so a partial address can block several players.

The operator log shows the last 80 lines. `private/data/stats.txt` records maintenance statistics; errors go to `errorlog.txt`. Enabling `log_to_disk` retains `log.txt` as well. Monitor disk space if you retain logs for a long-running town.

## Updates and backups

Back up the **whole `private/data/` directory** and `private/settings.local.php`, outside the public document root. Player records include email addresses, IP information, password hashes, and session data. Do not publish backups or attach them to public bug reports.

For a consistent backup or restore, temporarily stop incoming game requests and cron, then copy the data. The game holds a file lock while processing requests and saves player data through an atomic rename, but an administrative file copy should still happen while the town is quiet.

To update:

1. Back up the town and configuration, and pause web requests and cron.
2. Build the new code on your computer using the documented upstream revision and `npm ci`.
3. Upload the new application and generated assets. **Preserve the host's `private/data/` and `private/settings.local.php`.** Do not use a synchronization option that deletes these server-only files.
4. Review new defaults in `private/settings.php`, then restore access and cron.
5. Check the home page, operator page, login, and private-path restrictions.

To restore, pause web requests and cron, restore the matching backup and local settings, then reopen the site. Do not restore old data while PHP is writing to it. Keep the prior application version too if an update changes the data format. There is no automatic migration from the original Windows server's player files.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Blank stage or missing artwork | Build first; upload all of `flash/`, `ruffle/`, and `private/dist/`. Check browser network errors. |
| WebAssembly download fails | Serve `.wasm` as `application/wasm`; check MIME settings, HTTPS, and missing files. |
| Login requests return 404 or HTML | Confirm `mod_rewrite`, the document-root `.htaccess`, and installation at `/`. |
| PHP 500 error | Use the cPanel PHP error log; verify PHP version, iconv, syntax of local settings, and writable data directory. |
| Cannot save or lock town | Check directory ownership, free disk space, and filesystem support for `flock` and atomic rename. |
| Damaged town file | Preserve it for diagnosis and restore a known-good backup; do not replace it with an empty town. |
| Second account blocked | The default `ip_check` rule permits one player per address per day. |
| Time or daily rollover is wrong | Set a PHP timezone in local settings; verify the CLI PHP version and cron path. |
| Operator page is disabled | Set a password of at least 10 characters in local settings. |
| Local preview starts the wrong server | `npm run dev:php` starts PHP; `npm start` starts the earlier Node prototype. |

The JSON store serializes PHP game requests through one town lock. The configured online limit is not a promise of shared-host performance. Monitor latency and resource use as a town grows.
