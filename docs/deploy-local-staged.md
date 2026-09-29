# Staged local release fallback

The GitHub Actions FTPS workflow remains the primary deploy path. This fallback
packages a tested release on a Windows build machine, uploads it to an inbox
outside the document root, and lets the existing private `deploy/finalize.php`
promote it. It creates **no** deploy URL, webhook, migration route, or HTTP
token. The existing maintenance trigger only wakes an already pending marker;
the cron remains its fallback.

## One-time server bootstrap

The currently deployed legacy finalizer does not understand `mode=staged`.
Before the first local staged release, install the updated
`deploy/finalize.php` and `deploy/staged-release.php` through the normal,
reviewed deployment path. Confirm PHP 8.3 and the Zip extension on the server.
Keep `deploy/` outside DocumentRoot and keep the existing cron and
`lemon-deploy.log`. The local client downloads the remote finalizer and refuses
to start maintenance unless staged support is present. This bootstrap is a
separate, controlled production operation; running `-Preflight` does not do it.

## Build machine

Use PHP 8.3.x, Composer, Node 22.x, a reachable dedicated MySQL/MariaDB test
database, and WinSCP.com. Copy `deploy/local-release.env.example` to
`deploy/local-release.env`. Fill the values without quotes. The real file and
`.deploy-logs/`/`.deploy-packages/` are gitignored. Use a SHA-256 TLS
certificate fingerprint copied from a verified WinSCP connection; wildcard
acceptance and plain FTP are rejected. The remote FTPS account must be scoped
to the intended account and `LEMON_APP_DIR` must equal `/lemon-app/`.

```powershell
powershell -NoProfile -File deploy/local-release.ps1 -Preflight
powershell -NoProfile -File deploy/local-release.ps1 -Package
powershell -NoProfile -File deploy/local-release.ps1 -DryRun
powershell -NoProfile -File deploy/local-release.ps1 -Deploy
```

Only `-Deploy` can write remotely. It requires a clean `main` checkout
whose HEAD equals `origin/main`; it runs Composer/platform checks, the JS
build and tests, Pint, PHPStan, the stay concurrency test, and the full Laravel
suite. It then installs production dependencies and rebuilds assets. Missing
PHP 8.3, test DB, WinSCP, or pinned certificate stops the run. No
`--ignore-platform-reqs` path exists.

`-Package` creates one `release.zip` plus an external copy of
`release-manifest.json` for transport verification. The manifest is also
inside the ZIP. Its `files[]` covers every other archive entry, with path,
size, and SHA-256. The package includes runtime app/config/database/lang,
public assets, Blade views, routes, production vendor, the Laravel bootstrap
files, artisan, and private deploy scripts. It excludes environment files,
storage, caches, tests, docs, CI metadata, source Node packages, and local
deploy secrets. The external manifest is never promoted into the app root.

## Upload and finalize

The local client checks that no marker or manual maintenance is present, then
uploads the ZIP and manifest under
`storage/app/deploy-inbox/<release-id>/`. It downloads both again and checks
their hashes **before** uploading `storage/framework/maintenance.php`,
`storage/framework/down`, and finally `storage/app/deploy-pending`.
Maintenance owner is `<run_id>-1` in both the marker and `down` file.
The client makes a normal HTTPS GET to wake the existing maintenance
trigger, then polls FTPS for marker removal and a matching
`storage/app/deploy-completed.json` receipt.

In staged mode the finalizer validates marker ID and archive SHA-256, checks
every ZIP path and manifest record, extracts into
`storage/app/deploy-stage/<release-id>/`, and verifies every file digest.
It never extracts the ZIP directly over the live root. It backs up existing
files it will replace or delete under
`storage/app/deploy-backups/<release-id>/`. Only paths listed in the prior
`storage/app/deploy-manifest.json` may be deleted as obsolete. Protected
paths such as `.env`, `storage/**`, `bootstrap/cache/**`, and
`public/storage` are always rejected. The first staged release has no
prior managed manifest, so it deletes no legacy files.

After promotion, the finalizer invalidates PHP opcache before Laravel
bootstrap, clears old cache, migrates, seeds `RolePermissionSeeder`, rebuilds
caches, and closes its own maintenance mode. It writes the managed manifest
and completion receipt atomically. On a filesystem promotion error before
migration it restores backed-up code. Once migration begins, it never
automatically rolls back the database.

The inbox and temporary stage are removed after success. The three most
recent local managed backups are retained; older `local-*` backup directories
under `storage/app/deploy-backups/` are pruned. No application or user storage
path participates in this cleanup.

`storage/app/deploy-status.json` records the current phase:
`uploaded`, `validating`, `promoting`, `migrating`, `seeding`,
`caching`, `completed`, or `failed`. A failed staged marker is deliberately
not auto-retried by cron. Inspect the phase and
`/home/derspros/lemon-deploy.log` before intervention. A timeout never
blindly removes maintenance: the client reports release ID, phase, and marker
state. For a pre-promotion `uploaded`/waiting release, an operator may
cancel its **matching** marker and maintenance files after checking that
finalize is not running. For later phases, preserve maintenance and inspect
code/database state. The existing legacy marker path remains unchanged.
