# Developing and sharing the PHP version

## Build and run

From the repository root, obtain the [pinned upstream revision](../README.md#build-the-upload-folder), run `npm ci`, then:

```sh
npm run build:cpanel
npm run test:php
npm run test:router
npm run dev:php
```

PHP must be on PATH. On Windows you can instead invoke a PHP executable by its full path. `test:router` accepts the `FQ_PHP` environment variable if PHP is installed elsewhere. The tests use temporary data. The preview server, however, uses `www/private/data/`, so its accounts persist between runs.

The local development server binds to `127.0.0.1:8091`. Stop it with Ctrl+C. Apache/cPanel uses `.htaccess`, not the development router.

| Command | Purpose |
| --- | --- |
| `npm run build:cpanel` | Copy upstream assets, patch cafe chat, and include Ruffle and license notices. |
| `npm run test:php` | PHP protocol, gameplay, persistence, and operator tests. Requires PHP CLI and `shell_exec` for the lock probe. |
| `npm run test:router` | Local-router private-path/traversal regression checks and local-settings override checks. |
| `npm run dev:php` | Preview the PHP version locally. |
| `npm start` / `npm test` | Run/test the earlier Node prototype. |

## Source layout

| Path | Responsibility |
| --- | --- |
| `www/index.php` | Original protocol entry point, bans, sessions, and login throttling. |
| `www/private/src/Game.php` | Game construction, account lifecycle, town limits, and public settings/rankings pages. |
| `www/private/src/GamePlay.php` | Daily rollover, mail presentation, scenes, and command dispatch. |
| `www/private/src/GameTown.php` | Sales, locations, shopping, combat, chat, and other town rules. |
| `www/private/src/GameRules.php` | Script loading, runtime persistence, and game host functions. |
| `www/private/src/Script.php` | Parser and interpreter for original C-like scripts. |
| `www/private/src/Store.php` | JSON state, password migration, locking, and atomic writes. |
| `www/private/src/Admin.php` | Operator authentication, CSRF forms, views, and actions. |
| `www/private/src/System.php` | Logs and statistics. |
| `www/private/src/boot.php` | Runtime loading, settings, roots, cookies, and supporting functions. |
| `www/private/selftest.php` | PHP regression suite using temporary towns. |
| `www/client/` | Ruffle stage wrapper. |
| `www/router.php` | Local PHP development routing. |
| `tools/pack-cpanel.mjs` / `tools/patch-welcome.mjs` | Asset preparation and cafe chat SWF compatibility patch. |
| `upstream/` | Ignored checkout of the original game. |
| `server/`, `client/` | Earlier Node prototype; not needed for PHP hosting. |

Player records, sessions, pending script state, and news share a town JSON document. `FqStore` holds an exclusive lock for a request and writes through a temporary file and rename. Avoid long-running work while that lock is held. `settings.local.php` returns only the installation's overrides, which are merged over defaults before the timezone is applied.

When changing rules, compare with the [original C++ source](original-source.md), add a behavioral regression case to the PHP suite where appropriate, and run both PHP tests. Protocol details such as field ordering, object names, and callback state matter to the existing client. Do not rewrite original SWFs or game tables unless the change requires it.

## Preparing a public repository

The repository tracks application source, documentation, build tools, configuration defaults/examples, and retained license notices. It intentionally ignores:

- `upstream/` and `.flash-src/`: downloaded original sources and authoring files.
- `node_modules/`, generated `www/flash/`, `www/ruffle/`, `www/pages/`, `www/licenses/`, and `www/private/dist/`.
- `www/private/data/`, `www/private/settings.local.php`, local environment files, and backups.
- `.tools/`, `.e2e/`, `release/`, and logs.

The upstream directory is a separate Git checkout. Keeping it ignored avoids accidentally publishing an embedded-repository pointer that leaves other users without the actual assets. The README documents a normal clone of the original at a specific revision instead.

Before the first public commit, review `git status --short` and `git diff --cached`. For new files, `git ls-files --others --exclude-standard` shows what Git will see before staging. Confirm installation passwords remain in the ignored local file, player data is excluded, and generated/downloaded binaries are absent. Git ignore rules do not remove files that were already committed; check history if you are publishing an existing repository.

This port's original contributions use the [MIT license](../LICENSE). Preserve the [third-party notices](../THIRD_PARTY_NOTICES.md) for the original game, artwork, audio restrictions, and Ruffle when sharing it.

## Sharing a ready-to-upload release

Build from the intended revision in a clean checkout. A source ZIP downloaded from GitHub does not contain generated assets; say clearly whether a download is source-only or a prepared cPanel upload.

For a prepared upload, archive the contents of `www/` with hidden files included. Exclude `private/data/`, `private/settings.local.php`, local backups, `router.php`, and `private/selftest.php`. Include `private/settings.php`, `private/settings.local.example.php`, both `.htaccess` files, generated assets/scripts, and `licenses/`. Do not archive a live installation's data directory. Check the archive contents before attaching it to a GitHub release.

Record the project revision, the upstream revision, Ruffle version from `package-lock.json`, PHP version tested, and passing test commands in the release notes. The standalone [HOSTING.txt](../www/HOSTING.txt) travels with the upload folder; the complete guide remains in this repository.
