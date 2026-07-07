# TEST_REPORT.md

**Date:** 2026-07-06  
**Runner:** PHPUnit 11 (Laravel `php artisan test`)  
**Database:** PostgreSQL `rent_sales_test` @ `127.0.0.1:5433`  
**Scope:** Full suite + new hostile/critical-path tests

---

## Executive summary

| Metric | Value |
|--------|-------|
| Total tests | **209** |
| Passed | **205** |
| Failed | **4** (all new critical-path regressions — real bugs) |
| Skipped | 0 (LegacyInspect skipped only when dump file absent) |
| Duration | ~86s |

**Verdict:** Core happy-path flows are well covered. New hostile tests found **4 production gaps** in payment idempotency, receipt integrity, and cross-entity validation. No fixes applied (awaiting approval).

---

## STEP 1 — Test inventory (`backend/tests/`)

### Framework & infra

- **PHPUnit** (not Pest). `phpunit.xml` pins PostgreSQL on port **5433**.
- **Factories:** `UserFactory` existed; added `RentalBuildingFactory`, `RentalUnitFactory`, `TenantFactory` for hostile QA.
- **Helpers:** `tests/Concerns/InteractsWithRentalDomain.php` — shared rental fixture builder.
- Most rental/sales fixtures are still **inline `Model::create()`** in individual test classes (duplicated `activeTenant()` patterns).

### What exists (by area)

| Area | Files | Tests (approx.) | Quality |
|------|-------|-----------------|---------|
| **Auth** | `AuthenticationTest`, `PasswordResetTest` | 18 | **Strong** — login, role gates, password policy, reset flow |
| **Admin** | `UserManagementTest`, `ActivityLogTest`, `RecycleBinTest` | 15 | **Good** — CRUD, self-delete guard |
| **Rental financial** | `FinancialFlowTest`, `ChargeIntegrityTest`, `ChargeBatchTest` | 40+ | **Strong** — balances, batch idempotency, PG partial uniques |
| **Rental ops** | `TenantFlowTest`, `RentalOperationsTest`, `BulkMeterReadingTest` | 20+ | **Good** — lease lifecycle, occupied unit guard |
| **Sales** | `SalesFlowTest`, `SalesReportTest`, `SalesCurrencyEnforcementTest` | 11+ | **Adequate** — USD enforcement, client/payment flow |
| **Duplicate prevention** | `DuplicatePreventionTest`, `SoftDeleteTest` | 16 | **Good** — cross-module name uniqueness, soft delete |
| **Search** | `ListQueryTest` | 3 | **Good** — prefix search regression |
| **Legacy import** | 6 files under `Legacy/` + `Unit/Legacy/` | 9 | **Niche** — parser/importer; skipped without dump |
| **Documents** | `DocumentUploadTest` | 6 | **Adequate** |
| **Dashboard smoke** | 4 dashboard tests | 4 | **Thin** — single assertion each |
| **Security (NEW)** | `GuestAccessTest`, `RoleAuthorizationTest`, `IdorProtectionTest`, `MassAssignmentAttackTest` | 45 | **Hostile** — 1 failure (building mismatch) |
| **Critical path (NEW)** | `BusinessRulesTest`, `DataIntegrityTest` | 14 | **Hostile** — 3 failures |

### Meaningless / low-value tests

| Test | Why it adds little |
|------|-------------------|
| `Unit/ExampleTest::test_that_true_is_true` | Asserts `true === true`; never exercises app code |
| `Feature/ExampleTest::test_the_application_returns_a_successful_response` | Hits `/up` health check only |
| `RentalDashboardTest`, `SalesDashboardTest`, `RentalDashboardActionTest` | Single happy-path GET; no edge cases |
| `LegacyInspectTest` | Environment-dependent; skips without `/home/ali/legacy-app/rasulmar_db.sql` |

These are not harmful but inflate pass count without guarding business rules.

### Previously absent (now added)

| Gap | New coverage |
|-----|----------------|
| Guest → 401 on all protected routes | `GuestAccessTest` (25 endpoints) |
| Low-privilege → admin 403 | `RoleAuthorizationTest` |
| Cross-module IDOR | `IdorProtectionTest` |
| Mass-assignment attacks | `MassAssignmentAttackTest` |
| Double-submit payment | `BusinessRulesTest::test_double_submitting_same_payment_records_exactly_one_payment` **FAILS** |
| Duplicate `invoice_reference` | `DataIntegrityTest::test_invoice_reference_is_unique_under_rapid_duplicate_submission` **FAILS** |
| Sequential receipts | `DataIntegrityTest::test_concurrent_payment_creation_assigns_unique_sequential_receipt_numbers` **FAILS** |
| Payment tenant/building consistency | `IdorProtectionTest::test_payment_with_mismatched_building_id_is_rejected` **FAILS** |
| Balance cent-precision sequence | `DataIntegrityTest::test_charge_partial_payments_then_settlement_balance_is_exact` **PASSES** |

### Still absent (not in scope of this run)

- True HTTP concurrency (parallel requests / race threads)
- Per-record tenancy IDOR (system is single-tenant by design — all rental users see all rental records)
- Sales payment double-submit / overpayment parity with rental
- Rate-limit brute-force load tests
- Frontend/E2E tests

---

## STEP 2 — Critical-path suite added

New files:

```
backend/tests/Feature/Security/GuestAccessTest.php
backend/tests/Feature/Security/RoleAuthorizationTest.php
backend/tests/Feature/Security/IdorProtectionTest.php
backend/tests/Feature/Security/MassAssignmentAttackTest.php
backend/tests/Feature/CriticalPath/BusinessRulesTest.php
backend/tests/Feature/CriticalPath/DataIntegrityTest.php
backend/tests/Concerns/InteractsWithRentalDomain.php
backend/database/factories/RentalBuildingFactory.php
backend/database/factories/RentalUnitFactory.php
backend/database/factories/TenantFactory.php
```

Tests assert **secure intended behavior**. Failures indicate product bugs, not test mistakes (building-mismatch test was corrected to avoid false-positive from overpayment validation).

---

## STEP 3 — Results (failures only)

### FAIL 1 — Double-submit records two payments

| Field | Value |
|-------|-------|
| **Test** | `BusinessRulesTest::test_double_submitting_same_payment_records_exactly_one_payment` |
| **Expected** | 1 row in `rent_payments`; balance `7500.00` |
| **Actual** | 2 rows; balance `5000.00` (double credit) |
| **Root cause** | `RentPaymentController::store()` — bare `create()` with no idempotency key, transaction lock, or duplicate detection (`backend/app/Http/Controllers/Api/V1/Rental/RentPaymentController.php:41`) |
| **Severity** | **CRITICAL** — financial duplication under double-click / retry |

### FAIL 2 — Duplicate `invoice_reference` accepted

| Field | Value |
|-------|-------|
| **Test** | `DataIntegrityTest::test_invoice_reference_is_unique_under_rapid_duplicate_submission` |
| **Expected** | Second POST with same `invoice_reference` → 422; 1 DB row |
| **Actual** | Second POST → 201; 2 rows with same reference |
| **Root cause** | No unique validation in `StoreRentPaymentRequest` (`backend/app/Http/Requests/Rental/StoreRentPaymentRequest.php:29`) and no DB unique index on `rent_payments.invoice_reference` (`backend/database/migrations/2026_06_30_000010_create_rental_domain_tables.php:96`) |
| **Severity** | **HIGH** — receipt collisions, audit trail corruption |

### FAIL 3 — No sequential auto-generated receipts

| Field | Value |
|-------|-------|
| **Test** | `DataIntegrityTest::test_concurrent_payment_creation_assigns_unique_sequential_receipt_numbers` |
| **Expected** | Two payments receive non-null, unique, sequential `invoice_reference` values |
| **Actual** | Both `invoice_reference` fields are `null` |
| **Root cause** | No server-side receipt sequencer; field is optional manual input only (`RentPaymentController::store`, migration) |
| **Severity** | **MEDIUM** — operational/compliance gap (manual receipts only) |

### FAIL 4 — Payment accepts wrong `rental_building_id`

| Field | Value |
|-------|-------|
| **Test** | `IdorProtectionTest::test_payment_with_mismatched_building_id_is_rejected` |
| **Expected** | 422 on `rental_building_id` when building ≠ tenant's building |
| **Actual** | 201 Created — payment stored against wrong building |
| **Root cause** | `StoreRentPaymentRequest` validates `exists:rental_buildings` and `exists:tenants` independently; no cross-field rule (`backend/app/Http/Requests/Rental/StoreRentPaymentRequest.php:25-26`) |
| **Severity** | **HIGH** — data integrity / reporting corruption (payments attributed to wrong property)

---

## Passed hostile tests (highlights)

| Category | Result |
|----------|--------|
| Guest → 401 (25 endpoints) | ✅ All pass |
| Rental/Sales → admin 403 | ✅ All pass |
| Cross-module read (sales client / rental tenant) | ✅ 403 |
| Mass-assignment (`role`, `status`, `created_by` on payments) | ✅ Ignored/rejected |
| Overlapping lease on occupied unit | ✅ Rejected |
| Overpayment without acknowledgement | ✅ Rejected |
| Charge batch duplicate period | ✅ Rejected |
| Delete occupied unit / building with units | ✅ Blocked |
| Amount edges (0, negative, 3dp, string) | ✅ Rejected |
| Balance sequence to the cent | ✅ `0.00` after settlement |

---

## Ranked fix list (do not implement until approved)

### Tier 1 — CRITICAL (money)

1. **Payment idempotency** — Add idempotency key (client-generated or hash of tenant+amount+paid_at+user) or DB unique partial index; wrap `store()` in transaction with advisory lock per tenant.  
   _Files:_ `RentPaymentController.php`, new migration, `StoreRentPaymentRequest.php`

### Tier 2 — HIGH (integrity)

2. **Tenant/building consistency on payments** — `withValidator` rule: `rental_building_id` must equal `Tenant::find(tenant_id)->rental_building_id`.  
   _Files:_ `StoreRentPaymentRequest.php`, `UpdateRentPaymentRequest.php`

3. **Unique `invoice_reference`** — DB unique index (nullable, partial `WHERE invoice_reference IS NOT NULL`) + validation `Rule::unique`.  
   _Files:_ new migration, `StoreRentPaymentRequest.php`

### Tier 3 — MEDIUM (operations)

4. **Sequential receipt generation** — Server-side `ReceiptNumberService` with `SELECT … FOR UPDATE` on a sequence table; auto-assign when `invoice_reference` omitted.  
   _Files:_ new service, `RentPaymentController.php`, migration

5. **Expose `created_by` in `RentPaymentResource`** (optional) — API omits creator; not a security bug but hinders audit UI.

### Tier 4 — LOW (hygiene)

6. Remove or replace `ExampleTest` stubs.  
7. Deduplicate `activeTenant()` helpers into `InteractsWithRentalDomain` across legacy test files.  
8. Extend hostile tests to **sales payments** (double-submit, overpayment).

---

## How to re-run

```bash
# Requires Postgres on port 5433 (docker compose up postgres)
cd backend
php artisan test                              # full suite
php artisan test tests/Feature/Security tests/Feature/CriticalPath  # hostile only
```

---

## STEP 4 — Fixes

**Not started.** Awaiting your approval to fix Tier 1 first, then re-run and update this report.
