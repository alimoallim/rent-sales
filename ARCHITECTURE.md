# Architecture — Rent & Sales Management Platform

**Date:** 2026-06-30  
**Status:** Step 3 foundation in progress  
**Prerequisite:** `REQUIREMENTS.md` (Step 0)  
**Project directory:** `rent-sales-platform/` (sibling to legacy app)

---

## 0. Scale & simplicity constraints (confirmed)

This is a **lean startup app**, not enterprise software. Design decisions must reflect real scale:

| Factor | Reality | Implication |
|--------|---------|-------------|
| Users | 2–5 staff total | Simple role enum (`rental`, `sales`) — no permissions framework |
| Buildings / units | ~2–5 buildings, ~150 units | Single Postgres instance forever; no sharding, replicas, or Redis cache unless measurably slow |
| Concurrent usage | Handful at once | Standard Eloquent queries; optimize only when profiling shows need |
| Team maintaining | 1–few developers | Standard Laravel structure; thin controllers; services only for money logic |

**Deliberately omitted (unless business grows):** microservices, event sourcing, CQRS, Redis caching layer, elaborate CI/CD, multi-tenancy, plugin architecture, read replicas, queue workers for synchronous CRUD.

**Redis in docker-compose:** optional for future queues; v1 uses `database` queue driver and file cache.

---

## 1. Purpose

This document proposes the technology stack, system shape, and engineering conventions for the greenfield rebuild described in `REQUIREMENTS.md`. It does **not** implement anything — no `composer create-project`, no migrations, no Vue scaffold yet.

**Legacy app** (`/home/ali/legacy-app` or `/var/www/legacy-app`) remains **read-only reference**.

---

## 2. Stack decision

### 2.1 Summary

| Layer | Choice | Version target |
|-------|--------|----------------|
| Backend | **Laravel** | **12.x** (see §2.2) |
| Runtime | **PHP** | **8.3+** |
| API auth | **Laravel Sanctum** | SPA cookie + optional API tokens |
| ORM / DB access | **Eloquent** + query builder only | No raw concatenated SQL |
| Database | **PostgreSQL** | 16+ (see §2.3) |
| Frontend | **Vue 3** (Composition API) | 3.5+ |
| Build | **Vite** | Bundled with Laravel or standalone in `frontend/` |
| CSS | **Tailwind CSS** | 4.x or 3.x per scaffold defaults |
| HTTP client | **Axios** or native `fetch` | Via small API wrapper |
| Queue | **Laravel Queue** | Database driver (dev); Redis (prod) |
| Cache | **Redis** (prod) / file (dev) | Optional in v1 |
| File storage | **Laravel Filesystem** | `local` dev, `s3` prod |
| Testing | **Pest** (PHPUnit-compatible) | Feature + unit |
| CI | GitHub Actions (proposed) | Lint, test, build |

### 2.2 Laravel version rationale

Your brief asked for the **latest LTS-stable** Laravel. As of June 2026, Laravel **no longer publishes named LTS releases** — each major version receives ~18 months of bug fixes and **2 years of security fixes**.

| Version | Security fixes until | PHP | Recommendation |
|---------|---------------------|-----|----------------|
| Laravel 11 | March 2026 (ended) | 8.2–8.4 | Do not start new work |
| **Laravel 12** | **February 2027** | 8.2–8.5 | **Default choice** — mature, stable, widest package compatibility today |
| Laravel 13 | March 2028 | 8.3–8.5 | Alternative if you prefer longest security runway and PHP 8.3+ is guaranteed |

**Proposal: Laravel 12.x** unless you explicitly prefer Laravel 13. Both satisfy “stable production framework”; 12 minimizes ecosystem friction during initial build; 13 extends security support by one year.

No deviation from Laravel unless you reject both — there is no strong reason to choose Symfony, Slim, etc. for this CRUD-heavy financial domain app.

### 2.3 PostgreSQL vs MySQL

**Proposal: PostgreSQL 16+**

| Factor | PostgreSQL | MySQL 8 |
|--------|------------|---------|
| Foreign keys & constraints | Strong cultural fit; `CHECK`, deferred FKs | Supported but legacy schema had **zero** FKs |
| Money / decimal integrity | Excellent `NUMERIC` semantics | `DECIMAL` fine; historic float misuse in legacy |
| JSON / reporting | Good for future report metadata | Adequate |
| Legacy migration (Step 5) | Requires ETL from MySQL dump | Direct engine match |
| Your stated preference | Either acceptable | Either acceptable |

PostgreSQL is the better fit for a **greenfield redesign prioritizing data integrity** (FKs from day one, money fields, audit trails). Step 5 migration from `rasulmar_karama` (MySQL) is a one-time ETL regardless of engine — it does not justify building new schema on MySQL.

**If you require MySQL** for operational reasons (existing DBA, hosting), the architecture below is unchanged except driver and migration syntax.

### 2.4 Frontend: Vue 3 SPA + API (not Blade)

Per your preference:

- Laravel serves **JSON API only** (plus Sanctum cookie endpoints and file download routes).
- Vue 3 SPA handles all UI — no mixed server-rendered PHP templates.
- **Inertia.js is not proposed** — you asked for a clean API layer consumed by Vue, which keeps mobile/other clients possible later.

**Deployment shape:**

```
Browser → Vue SPA (static assets or Vite dev server)
        → HTTPS → Laravel /api/v1/...
        → PostgreSQL
        → S3/local disk (uploads)
        → Queue worker (reports, charge generation)
```

### 2.5 Tailwind CSS

Utility-first styling replaces legacy Bootstrap + inline overrides. Component library options (pick one at scaffold time):

| Option | Tradeoff |
|--------|----------|
| **Headless UI + Tailwind** | Maximum control, more markup |
| **shadcn-vue / Radix-vue** | Accessible primitives, modern look |
| **DaisyUI** | Faster scaffolding, more opinionated |

**Proposal:** Headless UI (or Radix-vue) + Tailwind for a professional admin-style UI without fighting legacy Bootstrap patterns.

---

## 3. Repository layout

Monorepo with two top-level apps (created when you approve scaffold):

```
rent-sales-platform/
├── README.md
├── REQUIREMENTS.md          # copied / moved from planning phase
├── ARCHITECTURE.md
├── DATA_MODEL.md            # Step 2
├── docker-compose.yml       # optional: postgres, redis, minio
├── backend/                 # Laravel 12 API
│   ├── app/
│   ├── database/migrations/
│   ├── routes/api.php
│   ├── tests/
│   └── ...
└── frontend/                # Vue 3 + Vite + Tailwind
    ├── src/
    │   ├── api/             # typed API client
    │   ├── views/
    │   │   ├── rental/
    │   │   └── sales/
    │   ├── components/
    │   ├── composables/
    │   ├── router/
    │   └── stores/          # Pinia
    └── ...
```

**Alternative (single Laravel tree):** `resources/js/` Vue app embedded in Laravel — simpler deploy, tighter coupling. **Not recommended** given your API-first requirement; keep `frontend/` separate.

### 3.1 Backend domain modules (`backend/app/`)

Organize by **business domain**, not legacy PHP filenames:

```
app/
├── Domain/
│   ├── Rental/
│   │   ├── Actions/           # e.g. GenerateMonthlyCharges, RecordMoveOut
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Services/          # e.g. TenantBalanceCalculator
│   │   └── Data/              # DTOs, value objects (Money, BillingPeriod)
│   ├── Sales/
│   │   ├── Actions/
│   │   ├── Models/
│   │   ├── Policies/
│   │   └── Services/          # e.g. ClientBalanceCalculator
│   └── Shared/
│       ├── Models/            # User, Audit columns concern
│       └── Enums/             # Role, PaymentStatus, UnitStatus
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Rental/
│   │   └── Sales/
│   ├── Requests/              # Form requests per endpoint
│   └── Resources/             # API resources (JSON shape)
├── Jobs/                      # Async report export, charge generation
└── Providers/
```

**Thin controllers:** validate (Form Request) → authorize (Policy) → delegate (Action/Service) → respond (API Resource).

### 3.2 Frontend modules (`frontend/src/`)

Mirror API domains in routing:

```
router/
  index.ts
  rental.ts      # /rental/*
  sales.ts       # /sales/*
  auth.ts

views/rental/
  DashboardView.vue
  buildings/
  units/
  tenants/
  charges/
  payments/
  water-bills/
  utilities/     # Nairobi water, electricity
  expenses/
  payroll/
  shareholders/
  reports/

views/sales/
  DashboardView.vue
  buildings/
  units/
  clients/
  payments/
  expenses/
  reports/
```

Role-based route guards: Rental users cannot navigate to `/sales/*` (and vice versa).

---

## 4. Authentication & authorization

### 4.1 Authentication — Laravel Sanctum (SPA mode)

| Concern | Approach |
|---------|----------|
| Login | `POST /api/v1/auth/login` — username + password (legacy uses `username`, not email) |
| Session | Sanctum SPA authentication: `httpOnly` cookie + CSRF (`/sanctum/csrf-cookie` then login) |
| Logout | `POST /api/v1/auth/logout` |
| Password change | `PUT /api/v1/auth/password` — authenticated |
| Rate limiting | `throttle:login` — 5/min per IP + username (configurable) |
| Password storage | `Hash::make()` / bcrypt (Laravel default) |
| Active users only | Reject login if `status !== active` |

**Why Sanctum over Passport:** First-party SPA on same top-level site — Sanctum is simpler; no OAuth complexity needed in v1.

**Optional later:** Personal access tokens for scripts/integrations.

### 4.2 Roles & authorization

| Role | Enum value | Access |
|------|------------|--------|
| Rental staff | `rental` | All `REQUIREMENTS.md` §2 endpoints |
| Sales staff | `sales` | All `REQUIREMENTS.md` §3 endpoints |

**Deferred roles** (`admin`, `all`, `construction`) — not in `users.role` enum until scope expands.

**Implementation:**

```php
// Example policy pattern — not implemented yet
class TenantPolicy {
    public function viewAny(User $user): bool {
        return $user->role === Role::Rental;
    }
}
```

- Register policies per model in `AuthServiceProvider`.
- Controllers call `$this->authorize('view', $tenant)` — no inline role strings.
- **Default assumption** (from `REQUIREMENTS.md` §1.4): no cross-module read access unless you override.

### 4.3 Users table (conceptual)

| Column | Notes |
|--------|-------|
| `id` | bigint PK |
| `name` | display name |
| `username` | unique login identifier (legacy parity) |
| `password` | hashed |
| `role` | enum: `rental`, `sales` |
| `status` | enum: `active`, `inactive` |
| timestamps | |

Email optional in v1 (legacy often blank).

---

## 5. API design

### 5.1 Principles

- **REST-ish**, resource-oriented, **versioned** from day one: `/api/v1/...`
- JSON request/response bodies
- **Laravel API Resources** control output shape (never return Eloquent models directly)
- **Form Requests** for all write endpoints
- Paginated index endpoints: `?page=` & `?per_page=` (max 100)
- Filters as query params: `?building_id=`, `?from=`, `?to=`, `?status=`
- Errors: RFC 7807-style JSON (`message`, `errors` bag for 422)
- IDs in URLs are integers (new schema uses consistent `id` PKs)

### 5.2 Route map (illustrative — full list in Step 2/3)

**Auth**

```
GET    /sanctum/csrf-cookie
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
PUT    /api/v1/auth/password
```

**Rental (`/api/v1/rental/...`)** — requires `role:rental`

```
/api/v1/rental/buildings
/api/v1/rental/buildings/{building}/units
/api/v1/rental/tenants
/api/v1/rental/tenants/{tenant}/move-out          POST
/api/v1/rental/charges
/api/v1/rental/charges/generate                   POST  (monthly batch)
/api/v1/rental/payments
/api/v1/rental/payments/{payment}/void            POST
/api/v1/rental/water-bills
/api/v1/rental/water-bills/{bill}/mark-paid       POST
/api/v1/rental/utilities/nairobi-water
/api/v1/rental/utilities/electricity
/api/v1/rental/expenses
/api/v1/rental/employees
/api/v1/rental/payroll
/api/v1/rental/shareholders
/api/v1/rental/shareholder-bills
/api/v1/rental/reports/income-statement           GET   ?building_id&month&year
/api/v1/rental/reports/tenant-balances
/api/v1/rental/reports/payment-history
```

**Sales (`/api/v1/sales/...`)** — requires `role:sales`

```
/api/v1/sales/buildings
/api/v1/sales/buildings/{building}/units
/api/v1/sales/clients
/api/v1/sales/clients/{client}/disable            POST
/api/v1/sales/payments
/api/v1/sales/payments/{payment}/cancel           POST
/api/v1/sales/expenses
/api/v1/sales/reports/balance
/api/v1/sales/reports/income
/api/v1/sales/reports/cancelled-payments
```

**Shared file serving**

```
GET    /api/v1/files/{uuid}                       authorized download
POST   /api/v1/sales/clients/{client}/documents   multipart upload
```

### 5.3 Middleware stack

```
api middleware group
  → EncryptCookies / Sanctum stateful
  → throttle:api
  → auth:sanctum
  → role:rental|sales (route groups)
```

---

## 6. Background jobs & scheduling

| Job | Trigger | Purpose |
|-----|---------|---------|
| `GenerateMonthlyRentCharges` | Scheduled: 1st of month 00:05 + manual API | Replaces legacy `charge.php` side-effect on page load |
| `ExportReportJob` | User clicks Export on large reports | CSV generation; notify when ready |
| `PurgeExpiredExports` | Daily | Clean temp export files |

**Scheduler:** Laravel `routes/console.php` + single cron entry `schedule:run`.

**Queue driver:**

| Environment | Driver |
|-------------|--------|
| Local dev | `database` (no Redis required) |
| Production | `redis` |

Income statement and balance calculations run **synchronously** for single-building/month queries unless profiling shows need to queue.

---

## 7. File storage

### 7.1 Upload types (from `REQUIREMENTS.md` §4.5)

| Entity | Files |
|--------|-------|
| Sales client | photo, signature |
| Rental tenant | ID document scan (optional) |

### 7.2 Implementation

```php
// config/filesystems.php — conceptual
'disks' => [
    'local' => [...],           // storage/app/private
    's3' => [...],              // AWS_* env vars
],

// Default: local in dev, s3 in production via FILESYSTEM_DISK env
```

- Store **metadata** in DB (`documents` table: polymorphic `documentable`, disk, path, mime, size, uploaded_by).
- Do **not** store blobs in PostgreSQL (legacy `longblob` pattern discarded).
- Downloads via authorized controller streaming from disk — never public ACL on bucket.
- Swap S3 → MinIO/compatible: change env vars only.

---

## 8. Money & financial logic

| Rule | Implementation |
|------|----------------|
| DB type | `decimal(14,2)` or `bigInteger` cents — **pick one project-wide in Step 2** |
| PHP type | `brick/money` or string-decimal via bcmath in Services — never `float` |
| Rounding | Half-up to 2 dp; document in `DATA_MODEL.md` |
| Balance calculations | Dedicated service classes with unit tests |
| Payment void/cancel | Status transition + `voided_at` + `voided_by` — no shadow tables |

**Proposal:** `decimal(14,2)` in PostgreSQL + validation rules `regex:/^\d+(\.\d{1,2})?$/` for API input — simpler for reports and legacy migration than integer cents.

**Currency:** Single currency **KES** assumed (legacy Nairobi Water references); store `currency` column default `KES` for future-proofing.

---

## 9. Environment & configuration

### 9.1 Environment files

```
backend/.env              # gitignored
backend/.env.example      # committed — no secrets
frontend/.env             # VITE_API_BASE_URL
frontend/.env.example
```

### 9.2 Key variables

```bash
# backend/.env.example (representative)
APP_NAME="Rent & Sales Platform"
APP_ENV=local
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rent_sales
DB_USERNAME=
DB_PASSWORD=

FILESYSTEM_DISK=local
# AWS_ACCESS_KEY_ID= ...
# AWS_SECRET_ACCESS_KEY= ...
# AWS_DEFAULT_REGION= ...
# AWS_BUCKET= ...

QUEUE_CONNECTION=database
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

### 9.3 Secrets

- Never commit `.env`, credentials, or `storage/` uploads.
- Production secrets via host environment or secret manager — not in repo.

### 9.4 CORS

`config/cors.php` allows `FRONTEND_URL` with credentials for Sanctum SPA.

---

## 10. Testing strategy

### 10.1 Priority — money & balances (highest coverage)

| Test target | Type | Examples |
|-------------|------|----------|
| `TenantBalanceCalculator` | Unit | charges − payments − discounts |
| `ClientBalanceCalculator` | Unit | sale price − payments − deposit − discounts |
| `GenerateMonthlyRentCharges` | Unit/Feature | idempotent per tenant per month |
| `IncomeStatementBuilder` | Unit | rent net, service, water, expense totals match legacy formula intent |
| Payment void/cancel | Feature | cancelled excluded from balance |
| Water bill duplicate guard | Feature | reject second bill same tenant/period |
| Move-out flow | Feature | tenant inactive, unit vacant, move-out record |

### 10.2 Standard coverage

| Layer | Tool | Focus |
|-------|------|-------|
| API endpoints | Pest feature tests | Auth, 403 for wrong role, 422 validation |
| Policies | Unit tests | Rental cannot hit sales routes |
| Migrations | Feature smoke | fresh migrate + seed runs |
| Frontend | Vitest + Vue Test Utils (optional v1) | Composables, form validation mirrors |

### 10.3 What not to over-test in v1

- Snapshot testing every Vue page
- End-to-end browser tests (unless you request Playwright later)

### 10.4 CI pipeline (proposed)

```yaml
# on pull request
- backend: composer install, pint --test, pest
- frontend: npm ci, lint, vitest (if present), build
```

---

## 11. Security baseline (from `REQUIREMENTS.md` §4 + Step 4 quality bar)

| Control | Implementation |
|---------|----------------|
| SQL injection | Eloquent / query builder bindings only |
| CSRF | Sanctum SPA CSRF |
| XSS | Vue text interpolation; sanitize rich text if added later |
| Auth brute force | Rate limiting |
| Authorization | Policies on every mutating endpoint |
| Mass assignment | `$fillable` / DTOs; never `extract($_POST)` |
| File uploads | Mime allowlist, size cap, store outside webroot |
| HTTPS | Required production |

---

## 12. Deployment sketch (informational)

Not implemented in Step 1 — for planning only:

```
┌─────────────┐     ┌──────────────┐     ┌────────────┐
│  Nginx/Caddy│────▶│  PHP-FPM     │────▶│ PostgreSQL │
│  static SPA │     │  Laravel API │     └────────────┘
│  + /api     │     └──────┬───────┘
└─────────────┘            │
                    ┌──────┴───────┐
                    │ Redis queue  │
                    │ S3 uploads   │
                    └──────────────┘
```

- `frontend/dist` served as static files.
- `/api` proxied to Laravel `public/index.php`.
- Queue worker: `php artisan queue:work`.
- Scheduler cron: `* * * * * php artisan schedule:run`.

---

## 13. Assumptions carried from Step 0 open questions

Until you override, architecture assumes:

| # | Assumption |
|---|------------|
| 1 | Project directory: `rent-sales-platform/` |
| 2 | Roles v1: **Rental + Sales only** (no Admin user mgmt) |
| 3 | Shareholders + income statement: **in Rental v1** |
| 4 | Other income/expense: **deferred** |
| 5 | Tenant retirement: **move-out + inactive**; no hard delete in API |
| 6 | Income statement service calc: **implement legacy formula**; flag service-per-payment quirk for UAT |
| 7 | Currency: **KES** |
| 8 | Billing periods: **store month as integer 1–12 + year int** (cleaner than legacy month names) |
| 9 | No cross-module access |
| 10 | Income statement in Rental nav: **yes** |

---

## 14. Build order alignment (Step 3 preview)

Architecture supports vertical slices in this order:

1. **Foundation** — Laravel + Sanctum + Vue shell + role routing + Tailwind layout
2. **Rental core** — buildings → units → tenants → move-out
3. **Rental financials** — charges → payments → water → utilities
4. **Rental reporting** — balances, income statement, exports
5. **Sales core** — buildings → units → clients
6. **Sales financials** — payments → cancel → expenses
7. **Sales reporting**
8. **Shared polish** — payroll/expenses already under rental; password change, audit columns

Each slice = migrations + models + policies + API + Vue views + tests for money logic.

---

## 15. Step 1 deliverables checklist

| Item | Status |
|------|--------|
| `ARCHITECTURE.md` (this file) | ✓ Drafted |
| `composer create-project` | ✗ Not run |
| `npm create vue` | ✗ Not run |
| `DATA_MODEL.md` | ✗ Step 2 — after this approval |
| Migrations | ✗ Step 2 |

---

## 16. Decisions needed from you

1. **Laravel 12 vs 13** — confirm choice (default: **12**).
2. **PostgreSQL vs MySQL** — confirm (default: **PostgreSQL**).
3. **Assumptions in §13** — any corrections before Step 2?
4. **UI component library** — Headless UI / Radix-vue / DaisyUI / no preference?
5. **Money storage** — `decimal(14,2)` vs integer cents?
6. **Approve scaffold** — may I create `rent-sales-platform/` with `backend/` + `frontend/` trees on next step?

---

*Next step after approval: **Step 2** — `DATA_MODEL.md` + Laravel migration files (present for review before `migrate`).*
