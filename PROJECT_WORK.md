# ACServ ERP — Project Work

## Fixed technology stack

- Laravel 13 / PHP 8.3+
- MySQL
- Blade templates
- Bootstrap 5 only for UI
- jQuery and jQuery AJAX
- SweetAlert2
- Database cache, sessions, and queues
- Vite only for compiling static production assets

The application is one Laravel codebase designed for Hostinger shared hosting. It does not require Docker, Redis, Supervisor, a persistent queue worker, WebSockets, or a Node.js production server.

## Current instruction

Phase 2 through Phase 7 development is implemented. This release adds one-page customer-signed invoices, compact job cards, a booking calendar and quick customer creation, purchase/vendor and operational accounts/P&L, employee/pay-grade and salary-slip management, customer-feedback follow-up, richer customer/technician PWA screens, OneSignal web push, and expanded installer demo data. Hostinger remains the deployment target; live SMTP/UPI/OneSignal credentials and browser sign-off still require the real server.

## September 24 UI and update release

- [x] Replace the oversized Android install alert with a compact platform-specific sheet, valid 192/512px PWA icons, and native installation when Chrome offers it.
- [x] Redesign the responsive public website and improve technician/customer mobile presentation and admin dashboard/pipeline styling.
- [x] Add an owner-and-private-key-gated ZIP updater with manifest/hash/path validation, staged review, scheduler apply, protected `.env`/uploads/server entry files, pending migrations, and code backups.
- [x] Add a release-package builder command and update the Hostinger installation guide for non-reinstall updates.
- [x] Pass 77 local tests and 515 assertions after the UI/updater changes.
- [x] Push and deploy the first updater release to Hostinger after verified full MySQL and application-file backups; the public web root contains only public assets and the preserved entry configuration.
- [ ] Configure the one-minute Hostinger Custom cron job for Laravel `schedule:run`.
- [ ] Retest the live Android install prompt and responsive website after deployment.

## Guided field-job enhancement

- [x] Dispatch notifications and technician acceptance before any on-site work.
- [x] Ordered pipeline: assigned → accepted → reached → inspected → customer-authorized → in progress → awaiting payment → payment pending → completed → manager-verified → closed.
- [x] Mandatory before photo and fault remark, then customer signature on the technician phone before work begins.
- [x] Inventory part selection, unused-part returns, service charge entry, and a live bill estimate during work.
- [x] Mandatory after photo, completion remark, second customer signature, and required checklist completion before final submission.
- [x] Cash or UPI collection by the technician after customer-signed completion; UPI requires the workspace QR and a transaction screenshot.
- [x] Office verification or rejection of collection; only verification completes the job, records the payment, and generates the paid invoice.
- [x] Automatic, idempotent invoice generation with net consumed parts and tax after payment verification.
- [x] Detailed admin job page with work timeline, all field photos, customer signatures, parts, payment proof, and final manager verification.
- [x] Fixed one-page A4 invoice with one final customer signature; compact job-card pages retain work details, used parts, field photos and signatures without empty filler pages.
- [x] Manager transitions cannot skip the technician stages; dispatch is locked after acceptance.
- [x] Rich admin pipeline board, analytics cards, DataTables-enhanced registers, mobile technician worklist, and customer service timeline.
- [x] Bundled Bootstrap, jQuery DataTables, AJAX, and SweetAlert assets for deployment without a production Node server or a runtime CDN.

## Development status

### Phase 2 — Jobs and technician PWA

- [x] Booking, job, assignment, checklist, evidence, technician profile, and push-subscription data model.
- [x] Tenant-scoped models, ULIDs, foreign keys, indexes, soft deletes, actor fields, and mutation auditing.
- [x] Admin booking and job dispatch screens using Bootstrap, jQuery AJAX, and SweetAlert2.
- [x] Skill, service-zone, branch, availability, capacity, and workload-based assignment suggestions plus manual assignment.
- [x] Guarded job lifecycle: created, assigned, accepted, reached, inspected, customer-authorized, in progress, completed, verified, closed/cancelled.
- [x] Mobile technician dashboard, guided job detail, checklist, part usage/return, digital signatures, and status actions.
- [x] Geo-tagged photo/video evidence with accuracy, timestamp-drift, device, mock-location, MIME/size, and SHA-256 validation.
- [x] Customer-signature capture and private five-minute signed evidence downloads.
- [x] IndexedDB offline action queue and automatic replay when connectivity returns.
- [x] Installable PWA manifest, service worker, private-page cache clearing on logout, and push subscription UI.
- [x] Queued job assignment/status notifications with retries and provider idempotency headers.

### Phase 3 — Inventory, billing, warranty, and payments

- [x] Inventory items, GST/tax profiles, HSN/SAC fields, warehouses, branches, technician van/kit locations, and stock movements.
- [x] Opening, purchase, transfer, consumption, return, and adjustment workflows with locked balance checks.
- [x] Job part consumption and unused-part returns.
- [x] Reorder-level warnings in the inventory screen.
- [x] Quotation → accepted work order → generated invoice → payment workflow.
- [x] GST invoice calculations, discounts, tax totals, part/service lines, partial payments, and balance tracking.
- [x] Technician completion records after-photo, remark, and customer signature; admin-verified collection then creates a paid part-and-labor invoice for technician and customer.
- [x] Warranty registration, certificates, claims, review/approval decisions, and expiry reminders.
- [x] AMC contracts, visit allowance fields, contract terms, and expiry reminders.
- [x] Dependency-free PDF generation for invoices, job cards, warranty certificates, reports, and payslips.
- [x] Razorpay order API, signed customer checkout confirmation, and signed webhook settlement.
- [x] Admin-configurable UPI QR, mandatory UPI transaction screenshot, cash collection, rejection/resubmission, and one-time admin payment verification.
- [x] Inventory register displays job-consumed quantity and remaining stock; consumed parts create outgoing movements and returns restore stock.
- [x] Vendor directory, purchase orders and line receipts; receiving stock is idempotent and supplier payments are tracked against the outstanding balance.
- [x] Accounts cashbook and operational P&L for invoiced sales, net consumed-parts cost, operating expenses and processed payroll. This is not a double-entry general ledger or GST settlement engine.

### Phase 4 — Customer PWA and notification engine

- [x] Customer mobile dashboard with asset registry, booking, service history, polling-based live status, invoices, warranty, feedback, and reminders.
- [x] Customer Razorpay checkout and downloadable invoice/warranty PDFs.
- [x] Push, email, WhatsApp, and SMS templates, preferences, quiet hours, delivery logs, retries, and provider adapters.
- [x] Browser push subscription storage with encrypted endpoint/key fields.
- [x] Booking confirmation and job status notifications.
- [x] Negative-feedback manager escalation.
- [x] Feedback dashboard for admin/manager follow-up and resolution; customers see a submitted rating rather than a duplicate feedback form.
- [x] OneSignal Web SDK v16 with a dedicated service-worker scope and queued, user-targeted REST push; the existing generic push adapter remains a fallback.
- [x] Next-service, warranty-expiry, and AMC-expiry reminder generation plus scheduled delivery.
- [x] Shared-hosting-safe polling in place of a persistent real-time socket server.

### Phase 5 — Attendance, leave, payouts, and scorecards

- [x] Geo-fenced check-in/check-out with selfie, device ID, precise location, accuracy, and mock-location rejection.
- [x] Attendance hours and daily records.
- [x] Leave request, manager approval/rejection, configurable opening balance, usage deduction, and technician balance/history display.
- [x] Payout cycles with hourly rate, per-job rate, rating incentive, low-rating penalty, fixed deduction, and totals.
- [x] Technician payout statements, payslip PDF, dispute submission, and manager resolution.
- [x] Nightly technician scorecards for completed jobs, ratings, SLA, attendance, and weighted score.
- [x] Leave-review and payout-ready notifications.
- [x] Employee directory with login role, designation, pay grade, reporting manager, branch, monthly salary and technician per-job incentive.
- [x] Salary slips include prorated base salary plus job/hour earnings; duplicate payout periods are rejected so P&L cannot double-count payroll.

### Phase 6 — CMS and public website

- [x] Tenant CMS models for pages, posts, services, offers, testimonials, media, revisions, and booking enquiries.
- [x] Admin create/edit/delete, preview, publish/unpublish, media upload, and revision snapshots.
- [x] SEO title/description/keywords, canonical URLs, OG image support, blog categories/tags, and schema.org homepage data.
- [x] CMS-driven public homepage, pages, blog posts, services, offers, testimonials, and responsive Bootstrap layout.
- [x] Website lead form with UTM fields, throttling, admin lead queue, assignment, and status workflow.
- [x] Dynamic XML sitemap and Laravel-served robots response.

### Phase 7 — Analytics, security, backup, and operations

- [x] Real-data dashboards for jobs, revenue, inventory, workforce, customers, and marketing.
- [x] On-demand and scheduled PDF reports with email recipients.
- [x] Database readiness endpoint and pending-queue visibility.
- [x] Security headers, HTTPS HSTS, restricted browser permissions, tenant-first binding, role middleware, rate limits, encrypted sensitive fields, and redacted audit logs.
- [x] Login/OTP/logout security-event recording with hashed IP addresses.
- [x] Tenant-scoped compressed and application-key-encrypted backups.
- [x] Backup validation and tenant-safe merge restore command.
- [x] Hostinger scheduler configuration with bounded `queue:work --stop-when-empty` runs.
- [x] Hostinger deployment, cron, storage, external-provider, health, and disaster-recovery instructions in `README.md`.
- [x] Idempotent `acserv:install` command for Hostinger/MySQL, with safe demo or live workspace creation, runtime preflight, assets, storage, migrations, and caches.
- [x] Demo installer also seeds a vendor, received purchase/stock receipt, sample account entries and technician employment profile.

## Shared application architecture

| Surface | Path | Roles |
|---|---|---|
| Public website | `/` | Public |
| Admin ERP/CMS | `/admin` | Owner, admin, manager, dispatcher, accountant according to permission |
| Technician PWA | `/technician` | Technician |
| Customer PWA | `/customer` | Customer |

Tenant identity is always derived from the authenticated user or configured public tenant. Submitted tenant IDs are never trusted. Public CMS requests initialize the configured tenant explicitly, and protected route-model binding runs after tenant context initialization.

## Hostinger compatibility decisions

- MySQL is the only application database.
- Cache, sessions, queues, and scheduler locks use database/file-backed Laravel drivers.
- Private evidence, signatures, reports, and backups stay outside the web root.
- Public CMS uploads use Laravel's `public` disk and `storage:link`.
- Queue work is processed in bounded cron-triggered runs; Supervisor is not required.
- Production assets are compiled before upload if the Hostinger plan does not provide Node/npm.
- Push, camera, location, service workers, and secure payments require HTTPS.
- OneSignal needs an app ID, REST API key and a same-origin `/onesignal/OneSignalSDKWorker.js` on the production HTTPS domain. Keys stay in server `.env`.
- Realtime customer tracking uses shared-hosting-safe HTTP polling.

## Local URL and demo access

Test URL: `http://aircon.test`

Tenant slug for all demo accounts: `acserv-demo`

| Portal | Email | Authentication |
|---|---|---|
| Owner/admin | `owner@acserv.test` | Email OTP |
| Technician | `technician@acserv.test` | Email OTP |
| Customer | `customer@acserv.test` | Email OTP |

When `MAIL_MAILER=log`, the OTP is written to `storage/logs/laravel.log`. No test password is used.

## Verification status

- [x] Run route registration and compiled-view checks.
- [x] Apply the additive technician-workflow and collection/UPI migrations to local MySQL.
- [x] Run focused job workflow, invoice, public-page, and portal-rendering tests.
- [x] Build production frontend assets locally.
- [x] Apply Laravel Pint formatting to all PHP files changed for this enhancement.
- [x] Exercise the admin main pages, booking CRUD, job creation/dispatch/open, inventory CRUD/stock movements/consumption, payment verification, evidence access, and PDF image embedding with feature tests.
- [x] Run the expanded PHPUnit suite: 58 tests / 417 assertions passing, including PDF, booking, purchases/accounts, employee/payroll, feedback and OneSignal push flows.
- [x] Apply new purchase/account, employment and payroll-uniqueness migrations to local MySQL; seed representative new-module demo records.
- [x] Build and commit-ready production frontend assets for Hostinger (no server-side Node.js build).
- [ ] Run fresh MySQL migrations and all seeders in a separate disposable database.
- [ ] Verify all three OTP logins and role redirects.
- [ ] Write/complete model factories for isolated test data where the feature suite needs them.
- [ ] Add Phase 2–7 feature/unit coverage for happy paths, validation, permissions, cross-tenant 404s, CSRF, queues, storage, notifications, payments, and failure recovery.
- [ ] Execute the critical admin, technician, customer, and public-site browser flows.
- [ ] Run provider sandbox verification for Razorpay and configured notification providers.
- [ ] Run browser accessibility/performance checks on the deployed server.
- [ ] Run load tests and record p95 results.
- [ ] Perform a backup validation and restore drill against a separate MySQL database.
- [x] Run the complete PHPUnit suite after focused feature tests pass.

## Phase 1 dependency note

Phase 1 passkeys/biometric unlock, invitations, registration, and its remaining core CRUD/policy work were not pulled into this Phase 2–7-first request. The Phase 2–7 implementation retains the existing OTP/session foundation and is ready to be tested independently before returning to those Phase 1 items.

## Repository

The application is pushed to `akcoders/acserv` on `main`. Compiled `public/build` assets are included for Hostinger; `.env`, logs, `vendor`, and `node_modules` remain excluded. Live server values and a real UPI QR are configured after deployment, not committed.
