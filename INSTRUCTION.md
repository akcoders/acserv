# Codex Build Instructions — AC Servicing ERP Platform

> **Project Codename:** `acserv-erp`
> **Document Version:** 1.0
> **Purpose:** Step-by-step build instructions for an AI coding agent (Codex) to scaffold, implement, test, and ship a multi-tenant ERP platform for an AC servicing company.

---

## 0. How Codex Should Use This File

1. **Read this entire file first** before writing any code.
2. Follow the **build order** in Section 6 (Phases). Do **not** skip ahead.
3. After each phase, run the **Definition of Done (DoD)** checklist for that phase.
4. Commit at the end of every phase using the convention in Section 13.
5. When a requirement is ambiguous, **stop and ask** — do not guess.
6. Prefer **small, reviewable PRs** over large commits.
7. Never hardcode secrets, API keys, or credentials.

---

## 1. Product Summary

Build a cloud-based, multi-tenant ERP with **four frontends** sharing a single backend:

| Frontend | Audience | Tech Hint |
|---|---|---|
| **Admin Web ERP + CMS** | Owners, Managers, Admins | Next.js (App Router) |
| **Labour PWA** | Field technicians | Next.js PWA |
| **Customer PWA** | End customers | Next.js PWA |
| **Public Website (CMS-driven)** | Visitors | Next.js (ISR/SSG) |

All frontends consume the **same REST/GraphQL API** with tenant isolation.

---

## 2. Tech Stack (Mandatory)

| Layer | Choice |
|---|---|
| Language | TypeScript (strict mode) |
| Backend | NestJS |
| Database | PostgreSQL 16 |
| ORM | Prisma |
| Cache / Queue | Redis + BullMQ |
| Object Storage | S3-compatible (MinIO locally, AWS S3 in prod) |
| Frontend | Next.js 14+ (App Router), React, TailwindCSS, shadcn/ui |
| State | TanStack Query + Zustand |
| Auth | WebAuthn (passkeys) + OTP + JWT sessions |
| Push | Web Push (VAPID) + FCM fallback |
| Realtime | Socket.IO (or native WS) |
| Testing | Vitest (unit), Playwright (e2e), Supertest (API) |
| Monorepo | pnpm workspaces + Turborepo |
| Container | Docker + docker-compose |
| CI/CD | GitHub Actions |

**Do not substitute stack without explicit approval.**

---

## 3. Monorepo Structure

```
acserv-erp/
├── apps/
│   ├── api/                  # NestJS backend
│   ├── admin-web/            # Admin ERP + CMS
│   ├── labour-pwa/           # Technician PWA
│   ├── customer-pwa/         # Customer PWA
│   └── website/              # Public marketing site
├── packages/
│   ├── ui/                   # Shared React components
│   ├── types/                # Shared TS types (DTOs, enums)
│   ├── config/               # ESLint, TS, Tailwind presets
│   ├── sdk/                  # Typed API client
│   └── utils/                # Shared helpers
├── infra/
│   ├── docker/
│   ├── terraform/            # (optional)
│   └── scripts/
├── docs/
│   ├── adr/                  # Architecture Decision Records
│   ├── api/                  # OpenAPI specs
│   └── runbooks/
├── .env.example
├── docker-compose.yml
├── turbo.json
├── pnpm-workspace.yaml
└── CODEX_INSTRUCTIONS.md     # this file
```

---

## 4. Domain Model (High Level)

### 4.1 Core Entities

```
Tenant
 ├── User (role: OWNER|ADMIN|MANAGER|DISPATCHER|TECHNICIAN|CUSTOMER|ACCOUNTANT)
 ├── Branch
 ├── Customer
 │    └── Asset (AC unit: brand, model, serial, installDate)
 ├── Booking
 │    └── Job
 │         ├── JobAssignment (technician, status, timestamps)
 │         ├── JobEvidence (photos, videos, geo, timestamps)
 │         ├── JobChecklistItem
 │         └── JobPartConsumption
 ├── InventoryItem
 │    ├── Warehouse / Van / TechnicianKit
 │    └── StockMovement
 ├── Invoice
 │    └── Payment
 ├── Warranty
 │    └── WarrantyClaim
 ├── AmcContract
 ├── LeaveRequest
 ├── AttendanceRecord
 ├── PayoutCycle
 │    └── PayoutLine
 ├── NotificationTemplate
 └── NotificationLog
```

### 4.2 Key Enums

```ts
enum Role { OWNER, ADMIN, MANAGER, DISPATCHER, TECHNICIAN, CUSTOMER, ACCOUNTANT }

enum JobStatus {
  CREATED, ASSIGNED, ACCEPTED, EN_ROUTE,
  IN_PROGRESS, COMPLETED, VERIFIED, CLOSED, CANCELLED
}

enum BookingChannel { WEBSITE, PHONE, WHATSAPP, CUSTOMER_PWA, WALKIN }

enum WarrantyType { MANUFACTURER, EXTENDED, AMC, NONE }

enum PaymentMode { CASH, UPI, CARD, NETBANKING, WALLET, CREDIT }

enum NotificationChannel { PUSH, EMAIL, WHATSAPP, SMS }

enum PayoutCycleType { DAILY, WEEKLY, MONTHLY }
```

Full schema must be written in `apps/api/prisma/schema.prisma` with:
- `tenantId` on every tenant-scoped table
- Soft-delete (`deletedAt`) on all business entities
- `createdAt`, `updatedAt`, `createdBy`, `updatedBy`
- Indexes on `(tenantId, ...)` for hot paths

---

## 5. Non-Functional Requirements

- **Multi-tenancy:** Row-level isolation via `tenantId`; enforce in middleware + Prisma extension.
- **Auth:** OTP + Passkey (WebAuthn) + optional biometric unlock. JWT access (15m) + refresh (30d, rotating).
- **RBAC:** Permission matrix per role; guard on every endpoint.
- **Audit:** Every mutating action logs `{ actorId, tenantId, entity, entityId, action, diff, ip, ua }`.
- **Geo-tagging:** Mandatory for job start/end + attendance check-in/out. Reject if GPS off or spoof detected.
- **Media:** Upload via pre-signed S3 URLs; store metadata + hash; virus scan hook.
- **Notifications:** Event-driven via BullMQ; retries with exponential backoff; per-user channel preferences & quiet hours.
- **Observability:** Structured logs (pino), OpenTelemetry traces, `/health` and `/metrics` endpoints.
- **Rate Limiting:** Per-tenant + per-IP on auth and public endpoints.
- **i18n:** All UI strings externalized (default `en-IN`; scaffold `hi-IN`).
- **Accessibility:** WCAG 2.1 AA on all customer-facing surfaces.

---

## 6. Build Phases (Follow In Order)

### Phase 1 — Foundation
**Deliverables:**
- Monorepo scaffold (Turborepo, pnpm workspaces)
- `apps/api` with NestJS, Prisma, Postgres, Redis wiring
- Auth: OTP (email/SMS stub) + Passkey (WebAuthn) + JWT
- Tenant middleware + RBAC guards
- Base User/Customer/Branch/Asset models + CRUD
- `docker-compose.yml` (api, postgres, redis, minio, mailhog)
- `.env.example` + README with local setup
- OpenAPI docs at `/docs`

**DoD:**
- [ ] `pnpm i && docker compose up` boots entire stack
- [ ] `/health` returns 200
- [ ] Can register a tenant, invite a user, login with OTP + passkey
- [ ] RBAC blocks unauthorized routes (tested)
- [ ] Unit + e2e smoke tests pass in CI

---

### Phase 2 — Jobs & Labour PWA
**Deliverables:**
- Booking + Job + JobAssignment + JobEvidence models
- Assignment engine (skill/zone/availability/manual)
- Geo-tagged photo/video capture (mobile-first)
- Job lifecycle state machine with guards
- `apps/labour-pwa`: auth (OTP + passkey + biometric unlock), today's jobs, job detail, evidence capture, offline queue
- Push notifications for new/changed jobs

**DoD:**
- [ ] Technician receives job push, accepts, goes en-route, uploads geo-tagged evidence, completes job with customer signature
- [ ] Offline: job actions queue and sync when online
- [ ] Passkey + biometric unlock works on iOS + Android
- [ ] Evidence files land in S3 with correct metadata

---

### Phase 3 — Inventory, Billing, Warranty
**Deliverables:**
- Inventory items, warehouses, van/kits, stock movements
- Job part consumption, returns, reorder alerts
- Quotation → Work Order → Invoice → Payment
- GST config, HSN, tax profiles
- Warranty registration + claim workflow
- PDF generation (invoice, job card, warranty certificate)
- Payment gateway integration (Razorpay sandbox)

**DoD:**
- [ ] End-to-end: booking → job → parts → invoice → paid
- [ ] Warranty job auto-flags chargeable/non-chargeable split
- [ ] PDFs downloadable from Admin + Customer PWA
- [ ] Stock decrements correctly on consumption; reorder alert fires

---

### Phase 4 — Customer PWA + Notifications
**Deliverables:**
- `apps/customer-pwa`: auth, asset registry, booking, live tracking, history, invoices, warranty, feedback, reminders
- Notification service: Push / Email / WhatsApp / SMS with templates
- WhatsApp Business Cloud API integration
- Email (SendGrid/SES) + SMS (MSG91/Twilio) providers
- User notification preferences + quiet hours
- Next-service-date engine + scheduler (BullMQ cron)

**DoD:**
- [ ] Customer books via PWA → receives WhatsApp + push confirmation
- [ ] Live status updates reflect technician actions in realtime
- [ ] Negative feedback auto-escalates to manager
- [ ] Next-service reminder fires X days before due

---

### Phase 5 — Payroll, Leave, Payouts
**Deliverables:**
- Attendance (geo-fenced, selfie, biometric)
- Leave apply/approve/balance
- Payout engine (per-job / hourly / incentive / penalty / deduction)
- Payout cycles, statements, disputes
- Payslip PDF
- Technician scorecard (rating, rework %, SLA)

**DoD:**
- [ ] Technician checks in within geo-fence; outside → rejected
- [ ] Applies leave → manager approves → balance updates
- [ ] Payout run generates statements + payslips for a cycle
- [ ] Scorecards update nightly

---

### Phase 6 — CMS + Public Website
**Deliverables:**
- Headless CMS (pages, blogs, services, offers, testimonials, media)
- SEO fields, sitemap, schema.org, OG images
- `apps/website`: SSG/ISR marketing site
- Lead capture forms → BookingEnquiry → admin queue
- Preview/publish workflow + versioning

**DoD:**
- [ ] Editor creates page → previews → publishes
- [ ] Lighthouse ≥ 90 on homepage (perf, SEO, a11y)
- [ ] Lead form creates enquiry visible in Admin
- [ ] Sitemap + robots served correctly

---

### Phase 7 — Analytics & Hardening
**Deliverables:**
- Dashboards: jobs, revenue, inventory, workforce, customer, marketing
- Scheduled reports (email/PDF)
- Load testing (k6), security review, pen-test fixes
- Backup + DR runbook
- Production deployment (Docker/K8s) + observability stack

**DoD:**
- [ ] All KPI dashboards render with real data
- [ ] k6 load test: p95 < 300ms at target RPS
- [ ] Backup restore drill passes
- [ ] Security checklist signed off

---

## 7. API Conventions

- Base path: `/api/v1`
- Versioning: URI-based
- Auth: `Authorization: Bearer <jwt>` (except `/auth/*`)
- Tenant: derived from JWT claim `tenantId`; never trust client
- Errors: RFC 7807 (`application/problem+json`)
- Pagination: `?page=1&limit=20` → `{ data, meta: { page, limit, total } }`
- Filtering: `?status=OPEN&assignedTo=...`
- Sorting: `?sort=-createdAt`
- Idempotency: `Idempotency-Key` header on POST for payments/bookings
- OpenAPI: auto-generated, committed to `docs/api/openapi.json`

---

## 8. Auth & Passkey Requirements

- **OTP:** 6-digit, 5-min TTL, max 5 attempts, rate-limited per phone/email.
- **Passkey (WebAuthn):**
  - Register: user authenticates via OTP first, then adds passkey.
  - Login: passkey primary; OTP fallback.
  - Store `credentialId`, `publicKey`, `signCount`, `transports`, `aaguid`.
  - Support multiple passkeys per user; allow revocation.
- **Biometric Unlock:**
  - After first passkey login, wrap session refresh token in device keychain via WebAuthn PRF or platform secure storage.
  - Subsequent opens require biometric/device-lock only.
- **Device Management:** list & revoke devices per user.
- **Session:** rotating refresh tokens; revoke on password/passkey change.

---

## 9. Notification Engine

- Event bus (BullMQ) with topics: `booking.created`, `job.assigned`, `job.completed`, `invoice.paid`, `warranty.expiring`, `leave.approved`, `payout.credited`, `feedback.received`, etc.
- Each event maps to one or more `NotificationTemplate` rows per channel.
- Worker picks job → resolves recipients (role/user/preference) → renders template → sends via provider → logs result.
- Providers pluggable behind `NotificationProvider` interface.
- Retry: 3 attempts, exponential backoff; dead-letter queue.
- Logs: `NotificationLog { id, event, channel, recipient, status, providerId, error, sentAt }`.

---

## 10. Geo-Tagged Evidence Rules

- On job start and end: require `{ lat, lng, accuracy, capturedAt, deviceId }` + photo/video.
- Reject if:
  - Permission denied
  - Accuracy > 100m
  - Mock location detected (Android `isFromMockProvider`)
  - Timestamp drift > 2 min from server
- Store: file in S3, metadata in DB, sha256 hash for integrity.
- Serve via signed URLs (5-min TTL).

---

## 11. Testing Requirements

- **Unit:** ≥ 80% coverage on `apps/api` core modules.
- **Integration:** Every endpoint has at least one happy-path + one auth-failure test.
- **E2E (Playwright):** Critical flows per frontend:
  - Admin: create booking → assign → invoice
  - Labour: receive job → complete with evidence
  - Customer: book → track → pay → feedback
- **Contract:** OpenAPI schema validated in CI.
- **Load:** k6 script per critical endpoint.

---

## 12. Environment Variables (`.env.example`)

```
NODE_ENV=development
PORT=4000
DATABASE_URL=postgresql://user:pass@localhost:5432/acserv
REDIS_URL=redis://localhost:6379
JWT_ACCESS_SECRET=change_me
JWT_REFRESH_SECRET=change_me
WEBAUTHN_RP_ID=localhost
WEBAUTHN_RP_NAME=ACServ ERP
WEBAUTHN_ORIGIN=http://localhost:3000
S3_ENDPOINT=http://localhost:9000
S3_BUCKET=acserv-media
S3_ACCESS_KEY=minio
S3_SECRET_KEY=minio123
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
FCM_SERVER_KEY=
WHATSAPP_PHONE_ID=
WHATSAPP_TOKEN=
SENDGRID_API_KEY=
MSG91_AUTH_KEY=
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
GOOGLE_MAPS_API_KEY=
```

**Never commit real `.env`.**

---

## 13. Git & Commit Conventions

- Branch: `feat/<scope>`, `fix/<scope>`, `chore/<scope>`
- Commits: Conventional Commits (`feat(api): add passkey registration`)
- PRs: link to phase + checklist
- Every PR must pass: lint, typecheck, unit, e2e smoke, OpenAPI diff

---

## 14. Guardrails for Codex

**Do:**
- Write tests alongside code.
- Keep functions small and typed.
- Use dependency injection; no globals.
- Document non-obvious decisions in `docs/adr/`.
- Ask before adding a new dependency.

**Don't:**
- Don't skip validation (zod/class-validator on every input).
- Don't trust client-supplied `tenantId`, `userId`, or `role`.
- Don't store PII in logs.
- Don't generate secrets or commit credentials.
- Don't break API contracts without versioning.
- Don't merge with failing CI.

---

## 15. Definition of Done (Global)

A feature is **done** only when:
- [ ] Code implemented, typed, linted
- [ ] Unit + integration tests written and passing
- [ ] API documented in OpenAPI
- [ ] Frontend wired + responsive + a11y-checked
- [ ] Notifications (if applicable) wired & logged
- [ ] Audit log entry added
- [ ] RBAC enforced
- [ ] README/ADR updated
- [ ] Reviewed and merged

---

## 16. First Task for Codex

**Start here:**

1. Scaffold the monorepo per Section 3.
2. Set up `docker-compose.yml` with Postgres, Redis, MinIO, Mailhog.
3. Bootstrap `apps/api` with NestJS + Prisma + health check.
4. Implement Prisma schema for `Tenant`, `User`, `Branch`, `Customer`, `Asset`.
5. Implement OTP auth + JWT + RBAC guards.
6. Implement Passkey (WebAuthn) registration + login.
7. Write tests for auth flows.
8. Open a PR titled: `feat: phase-1 foundation — auth, tenancy, core models`.

**Stop after Phase 1 DoD is met and report back with:**
- Summary of what was built
- Test results
- Open questions
- Proposed plan for Phase 2

---

*End of instructions. Codex: begin with Section 16.*