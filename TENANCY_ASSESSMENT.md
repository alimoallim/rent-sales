# Multi-Tenancy Readiness Assessment & Migration Plan

**Project:** Rent & Sales Management Platform (Laravel API, Vue 3 SPA, PostgreSQL)  
**Current state:** Single-tenant (one property-management company per deployment)  
**Target state:** Shared multi-tenant SaaS — many companies, one deployment, strict data isolation  
**Assessment date:** 2026-07-06  
**Scope:** Analysis only — no code modified  

> **Naming note:** This codebase uses the `tenants` table for **renters/occupants**. This document uses **company** for the SaaS tenant (the property-management firm) and **`company_id`** as the isolation column.

---

## Readiness Verdict (Honest)

| Dimension | Score | Notes |
|-----------|-------|-------|
| Multi-tenant architecture today | **0 / 10** | No `company_id`, no tenant resolver, no scoped policies |
| Refactorability of current code | **6 / 10** | Consistent Eloquent + FormRequests + module split help; policies and jobs fight you |
| Data model suitability | **5 / 10** | Clear rental/sales trees from buildings; shareholders/employees need direct company anchor |
| Auth model suitability | **4 / 10** | Global `username` uniqueness and deployment-wide `admin` role must be redesigned |
| **Overall readiness** | **Not ready** | Viable retrofit, not a greenfield — plan **30–45 developer-days** before onboarding customer #2 |

---

# STEP 1: INVENTORY THE BLAST RADIUS

## 1.1 Database Tables (35 tables)

### NEW (required for multi-tenancy)

| Table | Classification | Purpose |
|-------|----------------|---------|
| `companies` | **SHARED / PLATFORM** | SaaS tenant registry (name, slug, status, plan, onboarded_at) |
| `company_settings` | **SHARED / PER-COMPANY** | Key-value or JSON: currencies, notification emails, branding, feature flags |

Optional later: `subscriptions`, `plans`, `company_invitations`.

---

### TENANT-OWNED — needs `company_id` (directly or denormalized)

| Table | Root anchor today | `company_id` strategy |
|-------|-------------------|------------------------|
| `users` | None | **Direct** — every company user belongs to one company; platform super-admins are `company_id NULL` |
| `rental_buildings` | Root of rental tree | **Direct** |
| `rental_units` | → `rental_building_id` | Denormalize (performance + scope without join) |
| `tenants` (renters) | → `rental_building_id` | Denormalize |
| `tenant_move_outs` | → `tenant_id` | Denormalize |
| `rent_charges` | → `tenant_id` | Denormalize |
| `rent_payments` | → `tenant_id` | Denormalize |
| `tenant_water_bills` | → `tenant_id` | Denormalize |
| `tenant_electricity_bills` | → `tenant_id` | Denormalize |
| `building_water_utility_bills` | → `rental_building_id` | Denormalize |
| `building_electricity_bills` | → `rental_building_id` | Denormalize |
| `rental_expenses` | → `rental_building_id` | Denormalize |
| `employees` | Nullable `rental_building_id` | **Direct required** (building optional) |
| `payroll_entries` | → `employee_id` | Denormalize |
| `shareholders` | **No building FK** | **Direct required** |
| `shareholder_bills` | → `shareholder_id` | Denormalize |
| `charge_batches` | → `rental_building_id` | Denormalize |
| `charge_batch_items` | → `charge_batch_id` | Denormalize |
| `charge_adjustments` | → `tenant_id` | Denormalize |
| `sale_buildings` | Root of sales tree | **Direct** |
| `sale_units` | → `sale_building_id` | Denormalize |
| `clients` | → `sale_building_id` | Denormalize |
| `sales_payments` | → `client_id` | Denormalize |
| `sales_expenses` | → `sale_building_id` | Denormalize |
| `documents` | Polymorphic `documentable` | **Direct** (do not rely on morph join for scope) |
| `activity_logs` | None | **Direct** — admin audit is per-company; platform logs optional separate table |
| `password_reset_codes` | → `user_id` | Inherit via user or denormalize |

**Count: 27 existing tables + 1–2 new = ~29 company-scoped tables**

---

### SHARED / GLOBAL (platform-level, no `company_id`)

| Table | Notes |
|-------|-------|
| `companies` | Platform registry |
| `company_settings` | Per-company config rows keyed by `company_id` |
| `plans` / `features` | Do not exist yet; add when billing |

---

### SYSTEM (infrastructure, never company-scoped)

| Table | Notes |
|-------|-------|
| `cache`, `cache_locks` | Laravel cache |
| `jobs`, `job_batches`, `failed_jobs` | Queue — **must embed `company_id` in job payload**; scopes do not apply |
| `sessions` | Tied to `user_id`; company derived from user at login |
| `personal_access_tokens` | Sanctum tokens unused today (session auth only) |
| `password_reset_tokens` | Laravel default table; app uses `password_reset_codes` instead |
| `migrations` | Framework |

---

## 1.2 Eloquent Models & Relationship Chains

### Model inventory (30 files)

| Model | Relationships | Cross-company risk if scoped naively |
|-------|---------------|--------------------------------------|
| `User` | Referenced by `created_by`, `voided_by`, `generated_by`, etc. | User from company A referenced on company B record if IDs guessed and scopes skip FK checks |
| `RentalBuilding` | `units`, `tenants` | Root — scope here or on building |
| `RentalUnit` | `building`, `tenants` | OK if building scoped |
| `Tenant` (renter) | `building`, `unit`, `charges`, `payments`, `waterBills`, `electricityBills`, `moveOuts`, `documents` | Deep tree — child queries must inherit company |
| `RentCharge` | `tenant`, `unit`, `building`, `waterBill`, `electricityBill`, `batchItem` | `batchItem` → `ChargeBatch` chain crosses batch + tenant |
| `RentPayment` | `tenant`, `building`, `creator` | `creator` User must be same company |
| `ChargeBatch` | `building`, `items`, `generatedByUser`, `lockedByUser` | Scheduled job creates batches **without** user context |
| `ChargeBatchItem` | `batch`, `tenant`, `waterBill`, `electricityBill`, `adjustedByUser`, `approvedByUser` | Tenant from another company if batch/item ID manipulated |
| `ChargeAdjustment` | `tenant`, `building`, `rentCharge` | Orphaned service, no route |
| `Shareholder` | `bills` only | **No building on shareholder** — scope must be direct `company_id` |
| `Employee` | `building` (nullable), `payrollEntries` | Nullable building — scope must be direct |
| `SaleBuilding` | `units`, `clients`, `payments`, `expenses` | Sales root |
| `Client` | `building`, `unit`, `payments`, `documents` | Deep sales tree |
| `SalesPayment` | `client`, `building`, `cancelledBy` | Same as rental payments |
| `Document` | `documentable` (Tenant/Client), `uploader` | **Policy checks module only today** — UUID + IDOR across renters within module |
| `ActivityLog` | `user` | No company column — logs leak across companies in admin UI |

### Relationship chains that cross company boundaries (naive global scope pitfalls)

```
1. User ──created_by──► Tenant / RentPayment / Client
   Risk: exists:tenants,id passes for another company's tenant PK.

2. User ──generated_by──► ChargeBatch ──items──► Tenant
   Risk: Scheduled command picks User::orderBy('id')->first() globally.

3. Document ──morph──► Tenant (company A) + uploaded_by ──► User (company B)
   Risk: If user scoped but morph parent not validated together.

4. Shareholder ──bills──► RentalBuilding
   Risk: Shareholder has no building FK; scope only on bills misses shareholder list.

5. Employee (building_id NULL) ──payrollEntries──► RentalBuilding
   Risk: Company inferred from building fails when building_id is null.

6. ActivityLog ──user──► User
   Risk: Admin activity log shows all companies' events.

7. RentPayment ──tenant_id + rental_building_id (mismatched pair)
   Risk: Already possible today; worse if company A tenant_id used with company A building from wrong pair.
```

**Critical rule:** Denormalize `company_id` on **every** tenant-owned row. Do not rely solely on `JOIN rental_buildings ON company_id` in global scopes — too easy to miss in raw queries, `DB::table()`, and aggregates.

---

## 1.3 Unique Constraints & Indexes Needing `company_id`

### Must become composite with `company_id`

| Current constraint | Location | Why |
|--------------------|----------|-----|
| `users.username` UNIQUE | `0001_01_01_000000_create_users_table.php:14` | Two companies cannot both have `admin` user today |
| `users.email` UNIQUE (nullable) | Same:15 | Email reuse across companies blocked |
| `legacy_id` UNIQUE (per table, ~20 tables) | Rental + sales migrations | Legacy import: company A and B may share legacy IDs from different source systems |
| App rule: `UniqueBuildingName` | `app/Rules/UniqueBuildingName.php` | Checks **global** rental + sales name uniqueness — must scope to `company_id` |
| App rule: `UniqueUnitNumber` | `app/Rules/UniqueUnitNumber.php` | Per-building today — OK **if** `exists:buildings` is company-scoped; add DB unique `(company_id, rental_building_id, LOWER(house_number))` |

### Probably OK without `company_id` (parent PK is globally unique)

These include a surrogate `id` that is globally unique across all companies:

| Constraint | Reason |
|------------|--------|
| `(tenant_id, billing_month, billing_year)` on water/electricity bills | `tenant_id` is global PK |
| `(rental_building_id, billing_month, billing_year)` on `charge_batches` | Building PK is global |
| `(charge_batch_id, tenant_id, charge_type)` | All FKs are global PKs |
| `rent_charges_unique_billable_period` (partial, PostgreSQL) | `(tenant_id, month, year, purpose)` — safe per PK, but **must rebuild index** if you add `company_id` column for query performance |
| `rent_charges_unique_batch_item` | `charge_batch_item_id` global PK |

### Should add (missing today, per-company semantics)

| Proposed constraint | Reason |
|---------------------|--------|
| `(company_id, LOWER(name))` on `rental_buildings` | DB backup for `UniqueBuildingName` |
| `(company_id, LOWER(name))` on `sale_buildings` | Same |
| `(company_id, rental_building_id, LOWER(house_number))` on `rental_units` | Prevent duplicate units |
| `(company_id, sale_building_id, LOWER(house_number))` on `sale_units` | Same |
| `(company_id, employee_id, billing_month, billing_year)` on `payroll_entries` | Prevent double payroll |
| `(company_id, rental_building_id, billing_month, billing_year)` on `building_electricity_bills` | Water has this; electricity does not |
| Partial unique `(company_id, username) WHERE deleted_at IS NULL` | Soft-delete username reuse |

### Must remove or relax

| Constraint | Reason |
|------------|--------|
| `CHECK (currency_code = 'USD')` on sales tables | `2026_07_02_120000_add_currency_code_to_sales_tables.php` — companies in Somalia may use SOS, KES, USD per company settings |

---

## 1.4 Raw Queries, Aggregates & Reports Needing Tenant Scoping

### Raw SQL / `DB::table` (bypass Eloquent scopes)

| File | Pattern | Scoping need |
|------|---------|--------------|
| `app/Support/ListQuery.php` | `whereRaw` / `ilike` on list endpoints | Scope base query before `applySearch` |
| `app/Rules/UniqueUnitNumber.php` | `DB::table($table)->where(...)` | Add `company_id` where clause |
| `app/Rules/UniqueBuildingName.php` | `RentalBuilding::query()->whereRaw` | Filter by current company |
| `app/Services/Legacy/LegacyImporter.php` | Extensive `DB::table()->insert`, `User::query()->whereRaw` | Import must run inside `Company::withId($id)` context |
| `database/migrations/2026_07_02_100000_*.php` | `DB::statement` partial indexes | Rebuild for `company_id` if added to `rent_charges` |

### Services — full-table scans (high risk)

| Service / Controller | Unscoped queries |
|----------------------|------------------|
| `RentalDashboardService` | `RentalUnit::count()`, `Tenant::count()`, `RentalBuilding::count()`, `RentCharge::sum()`, per-tenant `breakdown()` loop |
| `SalesDashboardService` | Same pattern for sales entities |
| `TenantController::tenantIndexSummary` | `(clone $query)->each()` balance calc on **all** tenants |
| `ClientController::clientIndexSummary` | Same for clients |
| `SaleUnitController::index` | Balance per sold unit on page |
| `RentalReportService` | All 5 report methods |
| `SalesReportService` | All 5 report methods |
| `ArrearsAgingService` | Per-tenant FIFO aging |
| `ChargeBatchService::pendingBatchCount` | Global pending count |
| `RentalDashboardActionService` | `ChargeBatch::query()`, `Tenant::query()`, `RentalBuilding::query()` |
| `GenerateDraftChargeBatches` (command) | `RentalBuilding::each()`, `User::first()` globally |
| `ActivityLogController` | `ActivityLog::query()` — no company filter |
| `RecycleBinController` | Dynamic model `::onlyTrashed()` |
| `UserController` | `User::query()` — all users deployment-wide |
| `Admin/SystemSettingsController` | Global `config('mail.*')` — must become per-company or platform-only |

### FormRequest `exists:` rules (~40 occurrences)

Every `exists:tenants,id`, `exists:clients,id`, `exists:rental_buildings,id` validates **globally**. With global scopes on models, `exists` still hits unscoped DB unless you use custom `Rule::exists()->where('company_id', ...)`.

**Files affected:** All `Store*Request`, `Update*Request`, `ReportFilterRequest`, `BulkMeterReadingGridRequest`, etc. under `app/Http/Requests/`.

---

## 1.5 File Storage

### Current path (`DocumentService.php`)

```
documents/{morphClass}/{documentableId}/{random_filename}
```

Example: `documents/App\Models\Tenant/42/abc123.jpg` on `local` disk (private).

### Collision & cross-readability

| Issue | Severity |
|-------|----------|
| UUID primary key on `documents` | Collision unlikely |
| Path does not include `company_id` | Two companies' tenant id=42 → **same folder** if morph class identical |
| Access via `GET /api/v1/documents/{uuid}` | Policy checks module role only — **cross-company read** if UUID known |
| No tenant-scoped storage disk | Single bucket for all companies |

### Recommended path

```
companies/{company_id}/documents/{morphClass}/{documentableId}/{filename}
```

Migrate existing files during backfill. Use `Storage::disk()` per company only if you later need S3 prefix isolation.

---

## 1.6 Global Today → Per-Company Settings

| Setting | Current location | Multi-tenant change |
|---------|------------------|---------------------|
| Rental currency (KES) | `config/money.php` → `RENTAL_CURRENCY` env | `company_settings.rental_currency` |
| Sales currency (USD) | `config/money.php` → `SALES_CURRENCY` env | `company_settings.sales_currency` |
| Money locale | `RENTAL_MONEY_LOCALE`, `SALES_MONEY_LOCALE` | Per company |
| Sales `currency_code` CHECK = USD only | Migration `2026_07_02_120000` | Drop CHECK; validate against company allowed currencies |
| Admin notification emails | `config/notifications.php` → `ADMIN_NOTIFICATION_EMAILS` | Per-company `notification_emails` |
| Password reset TTL | `config/auth.php` | Platform-global OK, or per-company |
| Mail from name/address | `config/mail.php` | Per-company branding for transactional mail |
| App name / frontend URL | `config/app.php` | Platform vs per-company branding on emails/PDFs |
| Late-fee rules | **Not implemented** | Design per-company when built |
| Receipt / invoice numbering | Manual `invoice_reference` (optional, non-unique) | Per-company sequence table + unique `(company_id, invoice_reference)` if enforced |
| Charge batch schedule | `bootstrap/app.php` — global cron | Loop companies or dispatch per-company jobs |
| `MoneyConfig::rentalCurrency()` | Static config read | `MoneyConfig::forCompany($company)` |
| User roles (`admin`, `rental`, `sales`) | Global enum | Split: **platform super-admin** vs **company roles** |

---

# STEP 2: STRATEGY EVALUATION

## Strategy A: Shared DB + `company_id` + Global Scopes

**How it maps to this codebase:** Add `BelongsToCompany` trait on 27 models, `CompanyContext` middleware resolving from `auth()->user()->company_id`, update 11 policies to compare `$model->company_id`, fix ~34 controllers and ~29 services.

| Criterion | Assessment |
|-----------|------------|
| **Migration effort** | **28–40 developer-days** (solo dev, familiar with codebase) |
| **Risk** | **Medium–high** — one missed `::query()` in a report or job leaks data |
| **Ops @ 5 customers** | **Low** — one Postgres instance, one deploy, ~$50–150/mo infra |
| **Ops @ 20 customers** | **Low–medium** — same stack; index tuning, connection pool |
| **Ops @ 100 customers** | **Medium** — single DB size & query performance; still cheaper than 100 DBs |
| **Code that fights this** | `RentalModulePolicy` ignores `$model`; `UniqueBuildingName` global; `GenerateDraftChargeBatches` iterates all buildings; `UserController` deployment-wide; `exists:` rules unscoped; `LegacyImporter`; hardcoded USD CHECK; `MoneyConfig` static; admin `ActivityLog` global |

**Verdict:** Best fit for bootstrapped solo founder.

---

## Strategy B: `stancl/tenancy` or `spatie/laravel-multitenancy` (database-per-tenant)

**How it maps:** Each company gets own PostgreSQL database; run migrations 100×; connection switching on every request.

| Criterion | Assessment |
|-----------|------------|
| **Migration effort** | **45–70 developer-days** — package integration + refactor every raw query + job payload + testing |
| **Risk** | **High** — connection leaks, forgotten `tenant()` context in jobs, backup/restore complexity |
| **Ops @ 5 customers** | **Medium** — 5 DBs to backup/migrate/monitor |
| **Ops @ 20 customers** | **High** — 20 connection strings, schema drift risk |
| **Ops @ 100 customers** | **Very high** — 100 databases; solo founder cannot operate this |
| **Code that fights this** | Entire app assumes single default connection; `DB::table()` in rules/importer; partial PG indexes in shared migration style; `phpunit.xml` single test DB; cross-company platform admin dashboard impossible without third "landlord" DB |

**Verdict:** Overkill and operationally dangerous for a solo founder at target scale (5–20 companies).

---

## Strategy C: Separate Deployment Per Customer

| Criterion | Assessment |
|-----------|------------|
| **Migration effort** | **~0 code days**; **2–4 hours per customer** ops (env, migrate, DNS, SSL) |
| **Risk** | **Low** isolation; **high** drift (each deploy different code version unless disciplined) |
| **Ops @ 5 customers** | **Manageable** manually |
| **Ops @ 20 customers** | **Painful** — 20 servers, 20 deploys, 20 backups |
| **Ops @ 100 customers** | **Not viable** for solo founder |
| **Code that fights this** | Does not achieve stated goal of **one shared deployment** |

**Verdict:** Valid **only** as interim for customer #1 and #2 while building Strategy A. Not the SaaS end state.

---

## Recommendation: **Strategy A** (shared database + `company_id`)

**Justification for a bootstrapped solo founder:**

1. **Ops scale matches Somalia SMB SaaS reality** — you will have 5–20 companies long before 100; one Postgres is operable alone.
2. **Codebase is already a single-schema Eloquent app** — 30 models, deep FK trees; database-per-tenant multiplies migration pain by N.
3. **Strongest isolation ROI** — policies + global scopes + composite uniques give 95% of safety at 40% of effort vs package-based multi-DB.
4. **Platform admin remains possible** — one super-admin can see all companies from a landlord schema (optional `company_id IS NULL` users).
5. **Strategy C** can run in parallel for early paying customers while A is built, but is not the architectural target.

---

# STEP 3: MIGRATION PLAN (Strategy A)

## Phase 0 — Prerequisites (2–3 days)

- [ ] Fix payment integrity issues from security audit (idempotency, FK cross-check) — reduces financial risk before multi-tenant work.
- [ ] Document production PostgreSQL requirement (partial uniques).
- [ ] Add tenancy feature branch; freeze schema churn.

## Phase 1 — Foundation schema (3–4 days)

- [ ] Create `companies` table: `id`, `name`, `slug` (unique), `status`, `onboarded_at`, timestamps.
- [ ] Create `company_settings` table (or JSON column on `companies`).
- [ ] Add nullable `company_id` to all 27 tenant-owned tables + indexes.
- [ ] Add `company_id` to `users` (nullable for platform super-admin).
- [ ] Migration: insert **Company #1** from current deployment; backfill `company_id = 1` on every row; `SET NOT NULL` on domain tables.
- [ ] Rebuild uniques: drop global `username`/`email` unique; add `(company_id, username)` partial unique.

## Phase 2 — Runtime context (4–5 days)

- [ ] `CompanyContext` service + middleware: resolve `company_id` from authenticated `User`.
- [ ] `BelongsToCompany` trait: `company()` relation, `booted` global scope `where company_id = current`, auto-set on `creating`.
- [ ] `withoutCompanyScope()` for platform super-admin and data migration scripts only.
- [ ] Custom route model binding: `RentPayment`, `Tenant`, etc. scoped (404 instead of cross-tenant leak).
- [ ] Update `LoginRequest` / login UI: **company slug + username** OR email-based login unique per `(company_id, email)`.
- [ ] Split roles:
  - `platform_admin` — `company_id NULL`, landlord routes only.
  - `company_admin` — replaces today's deployment `admin` for one company.
  - `rental`, `sales` — unchanged but company-scoped.

## Phase 3 — Authorization & validation (5–6 days)

- [ ] Rewrite 11 policies: every `view/update/delete` checks `$user->company_id === $model->company_id` (or platform admin).
- [ ] `DocumentPolicy`: verify `document.company_id` matches user.
- [ ] Replace all `exists:` rules with `ScopedExists` rule filtering `company_id`.
- [ ] Update `UniqueBuildingName`, `UniqueUnitNumber` for company scope.
- [ ] `StoreUserRequest`: admin can only create users for their company.
- [ ] `ActivityLogController`, `RecycleBinController`: filter by `company_id`.

## Phase 4 — Services, reports, dashboards (8–10 days)

- [ ] `TenantBalanceBreakdownService`, `ClientBalanceCalculator` — accept optional company context (scopes handle most).
- [ ] `RentalDashboardService`, `SalesDashboardService` — verify all aggregates respect scope.
- [ ] `RentalReportService`, `SalesReportService`, `ArrearsAgingService` — audit every query.
- [ ] `ChargeBatchService`, `TenantController::tenantIndexSummary` — fix full-table scans (performance + correctness).
- [ ] `MoneyConfig` → read from `CompanyContext` / `company_settings`.
- [ ] Drop or relax USD-only CHECK constraints; validate per company settings.

## Phase 5 — Background & CLI (3–4 days)

- [ ] `rental:generate-charge-batches`: refactor to `Company::each()` → `CompanyContext::run($company, fn () => ...)` per company.
- [ ] Any future queued jobs: pass `company_id` in payload; call `CompanyContext::set($id)` at start of `handle()`.
- [ ] `LegacyImporter`: require `--company-id=`; scope all inserts.
- [ ] `LogsActivity` trait: write `company_id` on `activity_logs`.

## Phase 6 — File storage (2 days)

- [ ] New uploads: `companies/{company_id}/documents/...`.
- [ ] Migration script: move existing files + update `documents.path`.
- [ ] `DocumentController`: enforce company on stream.

## Phase 7 — Frontend (4–5 days)

- [ ] Login: company slug field (or subdomain routing later).
- [ ] `auth/me` returns `company_id`, `company_name`, settings snippet.
- [ ] Admin UI: platform admin vs company admin routes separated.
- [ ] Settings view: company-scoped currency/notifications (not global env).

## Phase 8 — Onboarding & platform admin (4–5 days)

### Self-registration flow (new company)

1. `POST /api/v1/platform/register` (or public route): company name, slug, admin name, email, password.
2. Create `companies` row + `company_settings` defaults + first `company_admin` user.
3. Email verification (recommended before go-live).
4. Guided setup wizard: rental currency, first building, invite users.
5. Rate-limit registration; manual approval toggle for Somalia launch (fraud control).

### Platform super-admin

- Separate route prefix `v1/platform/` — list companies, suspend company, impersonate (audit-logged), view cross-tenant metrics.

## Phase 9 — Migrate existing data (1–2 days)

```sql
-- Conceptual steps (run in maintenance window)
INSERT INTO companies (name, slug, status, created_at) VALUES ('Rasul Mart', 'rasulmart', 'active', NOW());
UPDATE users SET company_id = 1;
UPDATE rental_buildings SET company_id = 1;
-- ... all tenant-owned tables ...
UPDATE documents SET company_id = 1;
ALTER TABLE users ALTER COLUMN company_id SET NOT NULL; -- except platform admins
```

- Move files to `companies/1/documents/...`.
- Verify row counts match pre-migration.
- Keep `legacy_id` values unchanged (now unique per `(company_id, legacy_id)`).

## Phase 10 — Isolation test checklist (before customer #2)

### Automated (Feature tests)

- [ ] Company A user cannot `GET /rental/payments/{id}` for company B payment → **404**.
- [ ] Company A user cannot `POST` payment with company B `tenant_id` → **422**.
- [ ] Company A user cannot `GET /documents/{uuid}` for company B document → **403**.
- [ ] `exists:tenants,id` rejects company B tenant when authenticated as company A.
- [ ] List endpoints return zero rows from other companies.
- [ ] Reports/dashboards: seed known totals in A and B; assert no cross-contamination.
- [ ] `rental:generate-charge-batches` creates batches only for company being processed.
- [ ] `UniqueBuildingName`: same building name allowed in A and B, blocked twice in A.
- [ ] Username `ali` in company A and `ali` in company B both login with correct slug.
- [ ] Platform admin can see both companies; company admin cannot see other company users.

### Manual smoke tests

- [ ] Two browsers, two companies, simultaneous payment recording — no balance bleed.
- [ ] Charge batch approve in A does not affect B.
- [ ] Recycle bin restore in A does not restore B records.
- [ ] Activity log in A shows only A events.
- [ ] Search (`ListQuery`) in payments does not surface other company names.

---

# VERDICT SUMMARY

## Retrofit effort

| Phase | Days (estimate) |
|-------|-----------------|
| 0 Prerequisites | 2–3 |
| 1 Foundation schema | 3–4 |
| 2 Runtime context | 4–5 |
| 3 Auth & validation | 5–6 |
| 4 Services & reports | 8–10 |
| 5 Jobs & CLI | 3–4 |
| 6 File storage | 2 |
| 7 Frontend | 4–5 |
| 8 Onboarding | 4–5 |
| 9 Data migration | 1–2 |
| 10 Testing & hardening | 3–5 |
| **Total** | **30–45 developer-days** |

Add **20–30% buffer** for surprises in reports, legacy import, and policy edge cases → **realistic calendar: 8–10 weeks** part-time solo.

## Top 5 riskiest changes

| Rank | Change | Why it is dangerous |
|------|--------|---------------------|
| **1** | **Global scopes + route model binding + `exists:` validation** | One unscoped query or validation rule silently exposes another company's PKs. Hardest to test exhaustively. |
| **2** | **`rental:generate-charge-batches` scheduled command** | Runs outside HTTP context; today iterates **all** `RentalBuilding` and picks **global** `User::first()`. Will corrupt another company's batches if not rewritten. |
| **3** | **Auth redesign (company slug + username, role split)** | Login is the front door; mistakes lock out customers or merge accounts. Frontend + backend must ship together. |
| **4** | **Unique constraint migration (`username`, `legacy_id`, building names)** | Wrong migration order breaks production backfill or blocks onboarding. Requires maintenance window. |
| **5** | **Dashboard & report aggregates** | `RentalDashboardService` and `TenantController::tenantIndexSummary` scan entire tables. Scopes fix correctness but performance may force rewrites; easy to ship scope bugs in `ArrearsAgingService` vs `TenantBalanceBreakdownService` drift. |

---

## What you have going for you

- Clean **rental tree** (`rental_buildings` → units → renters) and **sales tree** (`sale_buildings` → units → clients) — natural company roots.
- **FormRequest** validation everywhere — single place to add scoped `exists` rules.
- **PostgreSQL** with strong charge-posting constraints — keep one shared DB.
- **Session-based auth** — company context lives on `User` model, not scattered JWT claims.
- **No Sanctum API tokens in production** — fewer token-tenancy edge cases.

## What is not ready today

- **Zero** `company_id` columns.
- Policies check **module role only**, never company ownership.
- **Admin** is deployment god-user, not company admin.
- **Scheduled jobs** and **legacy import** assume single global dataset.
- **Currency and notifications** are `.env` globals.
- **Document storage and policy** allow cross-renter access within a module — becomes cross-company with UUID guessing.

---

*End of tenancy assessment. No code was modified.*
