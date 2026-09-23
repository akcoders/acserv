# ACServ ERP

ACServ is a multi-tenant air-conditioning service ERP built as one Laravel application. It provides an admin ERP, technician PWA, customer PWA, and CMS-driven public website.

## Technology

- Laravel 13 and PHP 8.3+
- MySQL
- Blade and Bootstrap 5 only
- jQuery AJAX and SweetAlert2
- Database-backed cache, sessions, and queues
- Vite for compiling static frontend assets

The production design does not require Docker, Redis, Supervisor, WebSockets, or a persistent Node.js process, so it is suitable for Hostinger shared hosting.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

Create a MySQL database and configure `DB_*` in `.env`, then run:

```bash
php artisan acserv:install --demo
```

The local Herd URL is:

```text
http://aircon.test
```

## Demo logins

All demo accounts use tenant slug `acserv-demo` and OTP authentication:

| Portal | Email |
|---|---|
| Owner/admin | `owner@acserv.test` |
| Technician | `technician@acserv.test` |
| Customer | `customer@acserv.test` |

With `MAIL_MAILER=log` locally, request an email OTP; the code is shown on the login form. There are no demo passwords.

## Hostinger shared-hosting installation

1. Select PHP 8.3 or newer for both web and SSH/CLI in hPanel. Enable required PHP extensions, including `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `intl`, `zip`, and `curl`. Create a MySQL database and user with access to it. Enable SSL for the domain.
2. Clone this repository into a directory **outside** `public_html`, then run `composer install --no-dev --optimize-autoloader --no-interaction` from that directory. `public/build` is committed, so Hostinger needs no Node.js process or frontend build. Do not expose `.env`, `vendor`, `storage`, or `bootstrap` as the web root.
3. Point the domain document root to the repository's `public` directory where possible. If hPanel requires `public_html`, copy the contents of `public/` into `public_html`, edit only the two `vendor/autoload.php` and `bootstrap/app.php` paths in `public_html/index.php` to point to the repository, and repeat the public-file copy after every code update. Pass `--web-root=/home/ACCOUNT/domains/DOMAIN/public_html` to the installer so uploads use the actual web root.
4. Copy `.env.example` to a new `.env` on the server, then configure it there; never upload a local `.env` or commit credentials. At minimum set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
PUBLIC_TENANT_SLUG=your-tenant-slug
DB_CONNECTION=mysql
DB_HOST=your-hostinger-mysql-host
DB_PORT=3306
DB_DATABASE=your_database
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
MAIL_FROM_ADDRESS=your-reachable-address
```

5. For a **live, empty** workspace, set `PUBLIC_TENANT_SLUG` to the slug below and run one installer command over SSH:

```bash
php artisan acserv:install --workspace=your-tenant-slug --owner-email=you@your-domain.com --company="Your Company" --no-interaction
```

To install **demo data** instead, keep `PUBLIC_TENANT_SLUG=acserv-demo` and `DEMO_WORKSPACE=acserv-demo`. Set `DEMO_OWNER_EMAIL`, `DEMO_TECHNICIAN_EMAIL`, and `DEMO_CUSTOMER_EMAIL` to three real, distinct inboxes or working aliases, then run `php artisan acserv:install --demo --no-interaction`. The `.test` default addresses work only as local examples and cannot receive production OTP. For a `public_html` document root, add the `--web-root=...` option to either command.

The installer checks configuration and compiled assets, generates `APP_KEY` only if missing, runs **pending migrations only**, creates the first workspace in a transaction, links public uploads without overwriting an existing path, and caches the application. Re-running it preserves existing tenants and business data; it never runs `migrate:fresh`. Preserve the generated `APP_KEY` in a password manager because changing it makes encrypted backups and data unreadable. If you edit `.env` after installation, run `php artisan config:clear --no-interaction` before rerunning the installer. An empty database needs `--demo` or `--workspace`/`--owner-email` on its first run.

6. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP process. Keep `storage/app/private`, logs, backups, evidence, and the root `.env` outside the public web root. The `--web-root` option expects a copied Laravel `index.php` in that directory and creates only its missing `storage` symlink. If Hostinger disables symlinks, use `--no-storage-link` and serve public uploads through a supported protected route or enable symlinks before relying on QR images and uploads.
7. Add one Hostinger cron job every minute, replacing the PHP binary and application path with values shown in hPanel:

```cron
* * * * * /usr/bin/php /home/ACCOUNT/domains/DOMAIN/laravel/artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs reminders, scorecards, scheduled reports, encrypted backups, and a bounded database queue worker using `--stop-when-empty`. No persistent worker is required.

8. Configure any enabled SMS, WhatsApp, push, and Razorpay credentials in `.env`. Log in as owner, open Admin → Billing, and upload the real workspace UPI QR before using UPI collection; cash collection works without a QR. HTTPS is required for geolocation, camera capture, PWA installation, and push notifications. Test an OTP for the real owner inbox before handing over the site.

## Production checks

- `GET /up` is the lightweight health endpoint.
- `GET /admin/readiness` checks MySQL and the queue for authorized staff.
- Confirm the domain serves `public/build`, `manifest.webmanifest`, `sw.js`, `sitemap.xml`, and `robots.txt`.
- Verify that `APP_DEBUG=false`, directory listing is disabled, and storage/private files cannot be fetched directly.
- Run `php artisan optimize` again after changing production configuration or routes.

## Backup and disaster recovery

The scheduler writes tenant-scoped, compressed, application-key-encrypted backups to the configured private filesystem. Download backups from Admin → Analytics and retain off-site copies according to the business retention policy. The production `APP_KEY` is required to decrypt them and must be backed up separately in a password manager.

Validate a backup without changing MySQL:

```bash
php artisan acserv:restore-tenant-backup backups/TENANT/acserv-TIMESTAMP.json.gz.enc --tenant=TENANT
```

After taking a fresh database snapshot and putting the site in maintenance mode, merge the validated backup:

```bash
php artisan down
php artisan acserv:restore-tenant-backup backups/TENANT/acserv-TIMESTAMP.json.gz.enc --tenant=TENANT --force
php artisan optimize
php artisan up
```

The restore command is intentionally a merge/upsert operation. A full replacement drill should be performed first in a separate MySQL database during the testing phase.

## External integrations

Provider configuration is optional in local development. Production variables are documented in `.env.example`:

- SMTP through Laravel mail
- Generic bearer-token SMS, WhatsApp, and web-push provider endpoints
- VAPID-compatible public push key for browser subscription
- Razorpay order creation and signed webhook settlement

Queued notification delivery retries three times with backoff and records masked delivery logs.
