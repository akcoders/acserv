# ACServ ERP

ACServ is a multi-tenant air-conditioning service ERP built as one Laravel application. It provides an admin ERP, technician PWA, customer PWA, and CMS-driven public website.

The ERP includes the job pipeline, customer-signed PDF invoices and job cards, booking calendar, inventory and purchase orders, vendor payments, operational accounts/P&L, employee/pay-grade and salary-slip management, attendance/leave, feedback follow-up, and OneSignal-ready push notifications.

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

The full, step-by-step guide is [SERVER_INSTALLATION.md](SERVER_INSTALLATION.md). It covers the one-time browser installer, secure access token, demo-data option, command-line fallback, updates, and troubleshooting.

In brief: select PHP 8.3+ and MySQL in Hostinger, enable HTTPS and SMTP, then keep the Laravel application **outside** `public_html`. Copy only the contents of `public/` into `public_html`. In the copied `index.php`, adjust **all three** paths for `storage/framework/maintenance.php`, `vendor/autoload.php`, and `bootstrap/app.php` to the private application directory. For a non-sibling layout, also set `$customApplicationPath` in the copied `install.php`. The web installer at `/install.php` is intended for the initial setup; follow the guide's token and removal steps. It sends a synchronous SMTP test before creating database tables; SMTP acceptance does not guarantee inbox delivery. Do not expose `.env`, `vendor/`, or `storage/` as the web root.

After installation, add one hPanel **Custom** cron job scheduled every minute. Put only the PHP command and absolute Artisan path in the Command to Run field; do not include the schedule expression or shell redirection there:

```text
/usr/bin/php /home/ACCOUNT/domains/DOMAIN/acserv/artisan schedule:run
```

The installer links public CMS media when symlinks are supported. Private UPI QR images and job evidence are served through authenticated routes and do not depend on that public link. Configure optional SMS, WhatsApp, OneSignal push, and Razorpay credentials as needed. Upload the workspace UPI QR in Admin → Billing before using UPI collection, and verify an OTP reaches the real owner inbox.

For OneSignal, create a Web Push app whose site URL exactly matches `APP_URL`'s HTTPS origin. Set `ONESIGNAL_APP_ID` and `ONESIGNAL_REST_API_KEY` in server `.env`, then run `php artisan config:cache --no-interaction`. Ensure `https://your-domain.example/onesignal/OneSignalSDKWorker.js` serves JavaScript; if using `public_html`, copy the `public/onesignal` directory there too. The OneSignal worker uses `/onesignal/` scope and coexists with the main PWA `/sw.js`. Keep OneSignal Identity Verification disabled for the current web SDK. Technician/customer users enable notifications from the bell in their portals; Admin → Notifications shows integration status and delivery logs. [OneSignal's worker setup](https://documentation.onesignal.com/docs/en/onesignal-service-worker) and [message API](https://documentation.onesignal.com/reference/create-message) document the required setup.

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
- Generic bearer-token SMS, WhatsApp, and legacy web-push provider endpoints
- OneSignal Web SDK v16 and REST API for targeted technician/customer push
- VAPID-compatible public push key for browser subscription
- Razorpay order creation and signed webhook settlement

Queued notification delivery retries three times with backoff and records masked delivery logs.

Accounts → P&L is an operational accrual statement, not a double-entry general ledger or GST return. It counts invoiced sales, net parts used on jobs, operating expenses, and generated payroll; buying inventory is not immediately expensed.
