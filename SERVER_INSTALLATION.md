# ACServ ERP — Hostinger shared-hosting installation

This guide installs the Laravel/MySQL application from [the ACServ repository](https://github.com/akcoders/acserv) on a Hostinger Web hosting plan. Replace every example account, domain, path, database value, and email with your own. Run shell commands over SSH from the application directory unless a step says otherwise.

## 1. Prepare Hostinger

1. Add the domain in hPanel, point its DNS to Hostinger, and enable SSL/HTTPS.
2. In **Websites → Dashboard → PHP Configuration**, select PHP **8.3 or newer**. Check that SSH/CLI uses a compatible PHP too (`php -v`). Enable/check `pdo_mysql`, `gd`, `mbstring`, `openssl`, `fileinfo`, `dom`, `intl`, `zip`, and `curl`. The installer explicitly checks `pdo_mysql` and `gd`; Composer checks the remaining package requirements.
3. In **Databases → Management**, create a MySQL database and user. Save the exact host, database name, username, and password. Do not assume the host is `localhost`.
4. Enable SSH access. Check that Git and Composer 2 are available (`git --version` and `composer2 --version`). Hostinger calls Composer 2 `composer2` on many Web plans; if your shell provides Composer 2 as `composer`, use that instead.
5. Find the actual absolute `public_html` path in hPanel **FTP Accounts**, or run `pwd` after entering it over SSH. Hostinger commonly uses either `/home/u12345678/domains/example.com/public_html` or `/home/u12345678/public_html`.

Hostinger's [PHP settings](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/), [website-root path](https://www.hostinger.com/support/1583494-what-is-the-path-to-your-website-s-root-home-directory-and-how-to-change-it-in-hostinger/), [SSH access](https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/), and [Composer 2](https://www.hostinger.com/support/5792078-how-to-use-composer-at-hostinger/) articles show the current hPanel controls. On Hostinger Web hosting, assume the web root is fixed at `public_html`; do **not** put the entire Laravel project there.

## 2. Place the application outside `public_html`

Example layout (your account/domain may differ):

```text
/home/u12345678/domains/example.com/
├── acserv/             ← private Laravel application, .env, vendor, storage
└── public_html/        ← only files copied from acserv/public
```

From the directory that contains `public_html`:

```bash
git clone https://github.com/akcoders/acserv.git acserv
cd acserv
php -v
composer2 install --no-dev --optimize-autoloader --no-interaction
composer2 check-platform-reqs --no-dev
```

If Git or Composer 2 is not available on your plan, enable it through Hostinger or prepare/upload a release with its `vendor/` directory and compiled `public/build/`; do not run `composer update` on the live server. The repository already includes `public/build/manifest.json`, so server-side Node/npm is not needed.

For a **new or backed-up** `public_html`, copy everything in `acserv/public/`, including hidden `.htaccess`, `install.php`, and the `build/`, `onesignal/`, PWA, and icon files:

```bash
cp -a public/. ../public_html/
```

Check the target path before copying; this command can overwrite an existing website's files. Never copy `.env`, `vendor/`, `storage/`, or the other Laravel root files into `public_html`.

Edit the **copied** `public_html/index.php` so all **three** paths point to the private `acserv` directory. For the sibling-directory layout above, the changed lines are:

```php
if (file_exists($maintenance = __DIR__.'/../acserv/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../acserv/vendor/autoload.php';
$app = require_once __DIR__.'/../acserv/bootstrap/app.php';
```

Keep the rest of `index.php` unchanged. If your private application is not a sibling of `public_html`, adjust those three paths to its real location **and** set `$customApplicationPath` at the top of the copied `public_html/install.php` to the absolute private application path (for example, `/home/u12345678/acserv`). For the sibling layout shown above, leave `$customApplicationPath = '';` unchanged. Confirm that `public_html/.htaccess` exists; Apache/LiteSpeed needs its rewrite rules for Laravel routes.

If your particular hosting plan **does** let you set the document root directly to `acserv/public`, use that instead; then no copied public directory, index-path edits, or `--web-root` option are needed.

## 3. Install through the browser (first installation)

Use this on a **new, empty MySQL database** after the files and HTTPS domain are ready. Do **not** create `.env` before opening the browser installer: it is a first-install-only flow and rejects an existing `.env` unless the same installer has an unfinished setup to resume. The form writes `.env` in the private `acserv/` directory. Make sure PHP can write to that directory for the initial `.env` creation, `acserv/storage/`, `acserv/bootstrap/cache/`, and the actual web root for the storage link. Use correct ownership/permissions, not world-writable (`777`) directories.

1. Open `https://example.com/install.php`. On its first visit, the installer creates a secret access token in `acserv/storage/app/private/install-access-token`. Read that private file with Hostinger File Manager or SSH; it is **not** printed in the web page. Keep it secret and do not paste it into a support ticket or screenshot.
2. Enter the token to unlock the setup screen. Fill in the HTTPS app URL and app name, Hostinger MySQL host/database/user/password, and real SMTP host/port/user/password/from address. Set the workspace slug (the name typed at login). For a live workspace, enter the company display name and owner login email. For a demo workspace, choose demo mode and enter **three different real, receiving** owner, technician, and customer inboxes or aliases. The demo workspace display name is `ACServ Demo`; the company field applies only to live mode. The default `.test` addresses cannot receive production OTP.
3. Check the entries on this form, then submit **Install** once; there is no separate review screen. The installer checks MySQL, writes the private production `.env`, then synchronously sends a **test email** to the live owner or demo owner **before** migrations and workspace creation. If SMTP rejects the message, correct the mail settings and retry with the same database; no new workspace was created. An accepted SMTP send does **not** prove inbox delivery, so you must still verify the message arrives and test a real login OTP afterward. If the SMTP test succeeds, the installer creates the app encryption key, applies pending migrations, creates the first workspace or demo data, sets up public media, and caches the application. Keep the browser open while setup runs. Demo data includes sample jobs, stock, vendors, purchases, accounts, and workforce/pay records; use a separate database/site if you do not want those records in production.
4. On success, the installer writes a private lock, removes the access-token file, and attempts to delete **both** the active web-root `install.php` and `acserv/public/install.php`. Check both exact paths (`public_html/install.php` and `acserv/public/install.php` in the example layout). If either remains, delete only that file in File Manager. Keep the private installation lock intact: it blocks setup even if a web file could not be removed. Opening `/install.php` afterward must not reopen setup. Back up the private `.env` and generated `APP_KEY` securely; losing the key can make encrypted data and backups unreadable.

If a validation or database error appears, correct the indicated detail and retry; pending migrations preserve existing data. Once setup has written `.env`, retries are pinned to the same MySQL host/database/user so a later attempt cannot accidentally migrate another database. If you truly need to switch databases, inspect any partial data first and restart from a clean deployment/empty database; do not casually remove the progress marker. If installation partly completed, do **not** create another workspace or run `migrate:fresh` on the same database. Inspect `acserv/storage/logs/laravel.log` if the error is unclear. The access token is an installation secret, not the owner login credential: the owner signs in at `/login` with the workspace slug and receives an email OTP.

## 4. Command-line fallback

Use this path if the browser installer cannot run on your hosting plan, if you already made a private `.env` before trying the web installer, or for later safe migration/update runs. In `acserv/`, copy `.env.example` **only if `.env` does not already exist**; otherwise edit the existing private `.env` without overwriting its `APP_KEY` or settings:

```bash
cp .env.example .env
```

Set at least these values. The `PUBLIC_TENANT_SLUG` is the workspace slug entered at login; it is **not** the company display name.

```dotenv
APP_NAME="ACServ ERP"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://example.com
PUBLIC_TENANT_SLUG=my-workspace

DB_CONNECTION=mysql
DB_HOST=your-hostinger-mysql-host
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

CACHE_STORE=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=otp@example.com
MAIL_FROM_NAME="ACServ ERP"
```

Use the port/security settings supplied by your mail provider. For passwords containing `#`, spaces, or other dotenv-special characters, quote the whole value. **Production login is email OTP, not a password:** `MAIL_MAILER=log` or a `.test` login email will not deliver an OTP. The CLI installer rejects non-delivering mailers in production. Leave `APP_KEY` empty only on the **first** installation; the installer generates it. Never change or lose that key after data exists because encrypted data and backups depend on it. Keep `.env` outside the web root and out of Git.

Ensure PHP can write to `acserv/storage/`, `acserv/bootstrap/cache/`, and the real `public_html/` (for the storage link). Use correct file ownership/permissions rather than making directories world-writable (`777`).

Run **one** of the following from `acserv/` on an empty application database. In the examples, `public_html` is a sibling of `acserv`; replace the absolute `--web-root` path with your real path.

**Live workspace, without demo records:** set `PUBLIC_TENANT_SLUG=my-workspace` in `.env`, then:

```bash
php artisan acserv:install --workspace=my-workspace --owner-email=owner@example.com --company="My Company" --web-root=/home/u12345678/domains/example.com/public_html --no-interaction
```

This creates the owner and main branch. Log in at `https://example.com/login` with workspace `my-workspace` and the exact owner email above; the code arrives by email. There is no password.

**Demo workspace, with sample ERP data:** first set these values in `.env`:

```dotenv
PUBLIC_TENANT_SLUG=acserv-demo
DEMO_WORKSPACE=acserv-demo
DEMO_OWNER_EMAIL=owner+demo@example.com
DEMO_TECHNICIAN_EMAIL=technician+demo@example.com
DEMO_CUSTOMER_EMAIL=customer+demo@example.com
```

The three addresses must be distinct **real, receiving** inboxes/aliases. Then run:

```bash
php artisan acserv:install --demo --web-root=/home/u12345678/domains/example.com/public_html --no-interaction
```

Demo login at `https://example.com/login` uses workspace `acserv-demo` and each role's configured email. It creates sample jobs, stock, vendors, purchases, account records, and workforce/pay data. Use demo mode on a separate database/site if you do not want sample business records in production.

The installer checks configuration, applies **pending migrations only**, creates the first workspace, links public storage, and caches configuration/routes/views. It does not wipe existing data. A database that already contains a workspace is **not re-seeded**, even if you rerun `--demo`; use a separate empty database for a fresh demo. Never run `migrate:fresh` on live data.

After a successful **CLI** install, remove `install.php` from both the actual web root and `acserv/public/` if they exist. The browser installer is no longer needed and will reject a site with an existing `.env`; do not leave its entry point public.

If Hostinger blocks symlinks, `--no-storage-link` lets the installer finish, but public CMS images will not be served until you arrange a supported public-file route or symlink. The private UPI QR and job evidence are served through authenticated routes and do not rely on this public symlink. If an unrelated `public_html/storage` path already exists, inspect it before proceeding; the installer will not overwrite it.

## 5. Configure the scheduler in hPanel

In **Websites → Dashboard → Cron Jobs**, create one **Custom** job. Set schedule to **every minute** (`* * * * *`) and Command to Run to:

```text
/usr/bin/php /home/u12345678/domains/example.com/acserv/artisan schedule:run
```

Replace `/usr/bin/php` with the actual PHP 8.3+ CLI path (`command -v php`) and the application path with your real absolute path. First test the same command over SSH. Do **not** paste `* * * * *` into the hPanel command field; it belongs in the schedule fields. Keep the command free of `>> /dev/null 2>&1` in hPanel; Hostinger documents that redirection/special characters require a separate shell script. You can inspect output in the Cron Jobs panel. Hostinger states cron schedules use UTC, so account for that when checking time-specific tasks. See [Hostinger cron setup](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/) and [special-character limitation](https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/).

This one scheduler entry runs reminders, scorecards, reports, backups, application updates, and a short-lived database queue worker. No persistent worker, Supervisor, Redis, or Node process is required.

## 6. Finish application setup and verify

1. Open `https://example.com/up` (health endpoint), then `https://example.com/login` and request an OTP for the real owner email. If mail fails, check SMTP settings and `acserv/storage/logs/laravel.log`; do not switch production to `MAIL_MAILER=log`.
2. Sign in as owner. Visit `/admin/readiness` to check database/queue readiness. Open `/admin/jobs`, `/admin/bookings`, and `/admin/billing`.
3. Check that `/build/manifest.json`, `/manifest.webmanifest`, `/sw.js`, and `/onesignal/OneSignalSDKWorker.js` load from the domain. Test a public page and one image upload. Verify private `.env` and `storage/app/private` are **not** publicly accessible.
4. In Admin → Billing, upload your actual UPI QR before technicians collect UPI payments. Cash collection does not require a QR.
5. Optional OneSignal push: create its Web Push app for the same HTTPS origin as `APP_URL`; set `ONESIGNAL_APP_ID` and `ONESIGNAL_REST_API_KEY` in `.env`, then run `php artisan config:clear --no-interaction` and `php artisan config:cache --no-interaction`. The OneSignal worker file above must be reachable. Other SMS, WhatsApp, and Razorpay credentials can be configured later if those features are used.

If you edit `.env` after installation, clear and rebuild its cache with `php artisan config:clear --no-interaction` and `php artisan config:cache --no-interaction`. Do not rerun the first-install flow for routine configuration changes. Keep `APP_DEBUG=false` on the live site.

## 7. Updating an existing installation

**Do not reinstall.** Keep the existing MySQL database, private `.env` and `APP_KEY`, and uploaded files. Back up the **entire** MySQL database with a tested restore path, plus the application and uploaded files, before every release. The tenant-scoped Analytics backup alone is not a full disaster-recovery backup for an application migration. A code rollback cannot reverse every database migration.

### Browser ZIP updater

The updater must first be deployed once using the manual Git/files procedure below. Thereafter, the designated public-workspace owner can open **Admin → Application updates**. The update key is created privately at `storage/app/private/system-updates/access-token`; read it through SSH/File Manager, keep it secret, and never put it in the release ZIP or a screenshot. This extra key is required even after owner OTP login because an application update changes code for every workspace.

On a build machine, prepare a directory containing only changed application files in their repository-relative locations. Include compiled `public/build/` assets when frontend code changes; include compatible `vendor/` files and Composer metadata when dependencies change. Do not include `.env`, `storage/`, `bootstrap/cache/`, `public/index.php`, `public/.htaccess`, `public/install.php`, `public/storage`, or user uploads. Package the directory with:

```bash
php artisan acserv:update:package /absolute/path/to/prepared-release 2026.09.24 /absolute/path/to/acserv-update.zip
```

The command creates `update.json` with SHA-256 hashes. Use `--remove=relative/allowed/path` for each obsolete application file that the release must delete; never target protected files or uploads. Upload that ZIP in Admin → Application updates, enter the private key, and inspect the staged version/file count. After verifying the full backup, enter its reference, confirm downtime, and queue the update. The one-minute Hostinger scheduler takes the site into maintenance, backs up replaced code, copies verified files, runs **pending migrations only**, rebuilds caches, and brings the site back online. Reload the updater page to check status. If a migration fails or the runner stops during an update, maintenance remains enabled: inspect logs and restore the **full MySQL backup** with application files before using `php artisan up`. Never run `migrate:fresh` or re-seed demo data on an existing site.

For a copied `public_html` layout, the updater normally detects the active web root from the request. If it cannot, set `ACSERV_WEB_ROOT=/absolute/path/to/public_html` in the private `.env`, then rebuild the config cache. Do not point this variable at the private Laravel root.

### Manual Git/files fallback

Use this for the first updater deployment or if the browser updater is unavailable. If hPanel Git auto-deploy still targets `public_html`, **disable or repoint it to the private application directory before pushing**; otherwise a Git push can expose `.env` and application code under the web root. Check `git status` and preserve all local changes, including a deleted tracked installer. During a planned maintenance window, update the private application, run `composer2 install --no-dev --optimize-autoloader --no-interaction` if dependencies changed, and copy changed public assets into the real web root. Never republish `install.php`, overwrite the live `index.php`'s three private paths, or overwrite `public_html/storage`. Then run:

```bash
php artisan migrate --force --no-interaction
php artisan optimize:clear --no-interaction
php artisan optimize --no-interaction
php artisan up --no-interaction
```

If the command fails, inspect `storage/logs/laravel.log` before bringing the site back up; restore from the full backup when migrations or files are inconsistent.

## Quick troubleshooting

| Symptom | Check first |
|---|---|
| 500 error | `storage/logs/laravel.log`, PHP version/extensions, `.env`, writable `storage/` and `bootstrap/cache/`, all three `index.php` paths |
| Web installer says setup is unavailable | The site is already installed/locked or `.env` existed before the first web setup; use the CLI fallback for an existing site |
| Web installer completed but `/install.php` still exists | The private lock blocks new setup; manually remove only the remaining exact `install.php` file(s) |
| 404 on every route | `public_html/.htaccess` copied; domain points to `public_html`; rewrite support enabled |
| CSS/JS missing | `public_html/build/manifest.json` and assets copied from the same Git release |
| OTP not received | Real email address, SMTP credentials/from address, spam folder, mail-provider delivery logs; do not use `.test` addresses on server |
| Public images missing | `public_html/storage` symlink points to `acserv/storage/app/public` |
| Background notifications/reports stalled | hPanel Custom cron path, PHP CLI version, schedule set every minute, Cron Jobs output |
| PWA/push fails | HTTPS, `APP_URL` origin, `/sw.js`, `/onesignal/OneSignalSDKWorker.js`, OneSignal keys |

For backup/restore behavior and application features, see [README.md](README.md). Do not place server credentials in a support ticket, screenshot, or Git commit.
