# The original Funeral Quest and this PHP port

The original game is **Funeral Quest by Seth A. Robinson / Robinson Technologies**, with artwork by Akiko Robinson and additional design by Greg Smith.

- [Original source repository](https://github.com/SethRobinson/FuneralQuest)
- [Original README and author notes](https://github.com/SethRobinson/FuneralQuest/blob/f128dc2a15747f8a455485a74211ca6a0669c5fc/README.md)
- [Original source license](https://github.com/SethRobinson/FuneralQuest/blob/f128dc2a15747f8a455485a74211ca6a0669c5fc/license.txt)
- [Reference revision used by this port](https://github.com/SethRobinson/FuneralQuest/tree/f128dc2a15747f8a455485a74211ca6a0669c5fc)

This repository makes that game hostable through PHP on Apache/cPanel. It is a separate server implementation that uses the original compiled client and game content. The original Windows server does not run behind it.

## What comes from the original

The build obtains these from the author's repository:

- Compiled `.swf` client, scenes, characters, artwork, and the game's embedded audio.
- Help and informational HTML pages.
- Game tables, fortunes, sales options, swear list, and scripted events.
- C-like `.c` scripts, including lifecycle hooks such as `init.c`, `maint.c`, and `newday_player.c`.

The PHP rules and protocol handlers were written to follow the C++ implementation. For example, customer sales, daily limits, tag exchange, store purchases, battles, news, and town maintenance are implemented on the server. Familiar screens are connected to PHP gameplay rather than simply displaying the old artwork.

## How the implementations differ

| Area | Original repository | This repository's PHP version |
| --- | --- | --- |
| Server runtime | Windows C++ application with MFC operator interface and a custom HTTP listener. | PHP 8.1+ request handlers behind Apache; no Windows executable. |
| Hosting | Build/run `fq.exe`; the upstream README describes Visual Studio 2019 and Windows SDK requirements. | Upload prepared `www/` files to a PHP host; use cPanel for PHP, HTTPS, and cron. |
| Browser client | Original Flash client, originally requiring a Flash-capable browser. | The same compiled client runs in Ruffle. No browser Flash plugin is installed. |
| Client changes | Published SWFs and original layout. | Packaging patches the cafe chat display to scroll under Ruffle and adjusts some help-page layouts. This is not a recompiled replacement for all Flash source. |
| Communication | Original `request.xp` commands and polling. | Compatible HTTP command responses from `index.php`, with Apache rewrites. Multiplayer still uses HTTP polling. |
| Persistence | Original server's player structures and files. | JSON town state in `private/data/players.json`, with an exclusive request lock and atomic saves. Original player files are not importable as-is. |
| Player passwords | Stored as readable passwords in the original server, as its README explains. | New PHP accounts use `password_hash` / `password_verify`; older Node scrypt hashes can also be verified. Legacy plaintext fields are hashed when the PHP store loads them. |
| Operator controls | Native server dialogs. | Browser operator page at `/admin.php`, with login, expiring operator sessions, CSRF-protected forms, editing, kicks, bans, logs, and maintenance actions. |
| Configuration | Original server dialogs and files. | Shared defaults in `private/settings.php`, with private overrides in `private/settings.local.php`. |
| Daily rollover | Maintenance in the original server process. | Date checks during PHP game requests plus an optional CLI cron job for quiet towns. |
| Script engine | Original embedded scripting engine and native host functions. | A PHP parser/runtime for the game's C-like script language, with host functions implemented in `GameRules.php`. Waiting scripts are persisted between HTTP requests. |
| Asset preparation | Authoring files and already-compiled assets are included upstream. | A local Node build copies upstream assets and Ruffle into the upload folder. Node is not needed on the PHP host. |

PHP support includes the parlor sales loop, town locations, tag banking, store purchases, Yang's, classes, hospital recovery, break-ins and combat, mail and cafe chat, announcements, rankings, cleanup, tournaments, and lifecycle scripts. The self-test exercises these behaviors and several protocol details.

## Compatibility boundaries

This is a port under development, rather than a byte-for-byte replacement for the Windows application. Test coverage does not establish identical behavior for every random outcome, third-party script, Flash feature, or browser. Custom scripts are interpreted by the functions this PHP runtime implements; they are not compiled C programs or arbitrary PHP.

The original server is the reference when investigating a discrepancy. A useful report includes the PHP revision, PHP version, screen/action, expected original behavior, and a redacted protocol example. Never include real cookies, passwords, or the town data file in a public issue.

The `server/` and additional `client/` files in this repository belong to an earlier Node prototype. They do not power the cPanel deployment and are not the original upstream server. Use `www/` when contributing to the PHP version.

## Attribution and assets

This product includes software written by Seth A. Robinson ( www.rtsoft.com ). Art by Akiko Robinson.

The upstream README says its source is released under the Proton SDK license, and that Akiko Robinson's artwork is released under that license with credit. It also states that the sounds and music are licensed only for Funeral Quest and are not included in that general license. Keep that audio within this game. Preserve the upstream notice and the Ruffle licenses when sharing a prepared upload folder; the build includes them in `www/licenses/`.

See [third-party notices](../THIRD_PARTY_NOTICES.md) for the retained license text and source links. This port's original contributions use the [MIT license](../LICENSE); the upstream material retains its existing terms.
