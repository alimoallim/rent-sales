# Security & Production Readiness Audit Report

**Project:** Rent & Sales Management Platform (Laravel API + Vue 3 SPA + PostgreSQL)  
**Audit date:** 2026-07-06  
**Auditor role:** Pre-launch security & operational review (read-only; no fixes applied)  
**Deployment model today:** Single-tenant (one company per deployment)  
**Stated goal:** Multi-tenant SaaS for property companies in Somalia  

---

## 1. Executive Summary

### Verdict: **CONDITIONAL** (first single-tenant customer) · **NO** (multi-tenant SaaS without major rework)

This system is **not production-ready as a multi-tenant SaaS**. There is zero tenant/organization isolation in the schema, queries, policies, or middleware. Every authenticated rental user sees all rental data; every sales user sees all sales data. That is acceptable only for a **single property-management company** with a small, trusted staff on a dedicated deployment.

For a **first paying customer on a dedicated instance**, launch is **conditionally acceptable** if you accept known financial and operational risks and fix the highest-severity items first. The charge-posting pipeline is comparatively strong (PostgreSQL partial unique indexes, `DB::transaction`, `lockForUpdate` in batch posting). Authentication uses session-based Sanctum SPA cookies with throttled login and a reasonable password-reset flow. Store/update endpoints consistently use FormRequests.

However, **payment recording is dangerously thin**: no idempotency, no database transactions, no row locking, and no cross-validation that `tenant_id` matches `rental_building_id` (or `client_id` matches `sale_building_id`). A double-click or replayed request can create duplicate payments. Any rental staff member can void payments; any sales staff member can cancel payments—there is no segregation of duties despite an `is_manager` flag that is **never enforced** in live authorization paths. List and dashboard endpoints perform **full-table balance scans** that will degrade sharply as tenant/client counts grow.

Authorization is module-level (admin / rental / sales), not record-level. That is a design choice for single-tenant, but it is a **blocker** for SaaS. SQL injection risk is low (parameterized queries). File uploads are private and type-restricted, but document access is module-wide UUID access (any rental user with a document UUID can download any tenant document).

**Bottom line:** Ship to one trusted customer on an isolated deployment only after addressing CRITICAL payment integrity issues and hardening production configuration (`APP_DEBUG=false`, auth audit logging). Do **not** onboard multiple companies on one deployment without a full multi-tenancy design pass.

---

## 2. Findings Table

| ID | Severity | File:line | Description | Suggested fix |
|----|----------|-----------|-------------|---------------|
| C-01 | CRITICAL | Schema-wide (no `organization_id` / `company_id` in any migration or model) | **No multi-tenant isolation.** All data is global per deployment. Policies check module role only, never company scope. | Add `organization_id` to all domain tables; global scopes; middleware; policy checks; migration strategy before SaaS launch. |
| C-02 | CRITICAL | `backend/app/Http/Controllers/Api/V1/Rental/RentPaymentController.php:37-46` | **Payment double-submit:** `store()` is a bare `create()` with no idempotency key, no transaction, no `lockForUpdate`. Duplicate POSTs create duplicate payments. | Payment service with idempotency key (unique index), `DB::transaction`, optional `lockForUpdate` on tenant/client row. |
| C-03 | CRITICAL | `backend/app/Http/Requests/Rental/StoreRentPaymentRequest.php:25-26` | **FK integrity gap (rental):** `tenant_id` and `rental_building_id` validated independently; no rule that tenant belongs to building. Wrong pair can be stored. | Add custom rule: `tenant.rental_building_id === rental_building_id`. Same for sales client/building. |
| C-04 | CRITICAL | `backend/app/Http/Controllers/Api/V1/Sales/SalesPaymentController.php:38-47` | **Sales payments lack balance/overpayment checks.** Rental has `ValidatesRentPayment`; sales has none. Unlimited overpayment possible. | Mirror rental business-rule validation using `ClientBalanceCalculator`. |
| H-01 | HIGH | `backend/app/Policies/DocumentPolicy.php:22-34` | **Document IDOR (within module):** Policy checks `canAccessRental()` / `canAccessSales()` only—not parent ownership. Any rental user with document UUID accesses any tenant document. | Authorize against parent record or uploader; consider signed temporary URLs. |
| H-02 | HIGH | `backend/app/Policies/RentalModulePolicy.php:25-27` | **Any rental user can void payments** (`void` uses `authorize('update')`). No manager/admin gate. Same for sales `cancel`. | Separate `void`/`cancel` policy methods; require `is_manager` or admin. |
| H-03 | HIGH | `backend/app/Http/Controllers/Api/V1/Rental/TenantController.php:74-113` | **Full-table balance scan on every tenant list:** `tenantIndexSummary()` iterates **all** matching tenants and calls `balanceCalculator->calculate()` per row—before pagination. | Pre-aggregate balances in SQL/materialized view; compute summary only for current page or cache. |
| H-04 | HIGH | `backend/app/Models/RentPayment.php:22-35` | **Privileged fields in `$fillable`:** `status`, `voided_at`, `voided_by`, `created_by`, `updated_by`. Safe today via FormRequest discipline; fragile for mass-assignment. | `$guarded` for audit/status fields; set only in controller/service. |
| H-05 | HIGH | `backend/app/Services/Rental/TenantBalanceBreakdownService.php:91-120` | **Balance excludes `Adjustment` purpose charges** but `RentalReportService` sums all charges. UI balance and reports can disagree. | Include adjustment purpose in breakdown or document exclusion; align report queries. |
| H-06 | HIGH | `backend/app/Services/Rental/ChargeAdjustmentService.php:17-63` + `routes/api.php` | **Manager-only charge adjustments implemented but not exposed.** `is_manager` enforced here only; no API route. Dead code / false sense of security. | Wire to API with policy, or remove until needed. |
| H-07 | HIGH | `backend/app/Models/User.php:80-83` + all policies | **`is_manager` never used in policies** except orphaned `ChargeAdjustmentService`. Manager flag has no effect on live operations. | Enforce on charge batch approve, void, large discounts—or remove flag from UI. |
| H-08 | HIGH | `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php:24-44` | **No auth security audit trail:** login success/failure, logout, password change, reset not logged to `activity_logs` or `Log::`. | Structured security event logging with IP, user-agent, outcome. |
| H-09 | HIGH | `backend/database/migrations/2026_07_02_100000_enforce_rent_charge_financial_integrity.php:10-12` | **Billable charge uniqueness is PostgreSQL-only.** Migration skips on non-pgsql; dev/test on SQLite lacks DB-level duplicate protection. | Ensure production is PostgreSQL; add integration tests; document requirement. |
| H-10 | HIGH | `backend/routes/api.php:103-107` vs controllers | **`apiResource` registers `show` routes without `show()` methods** on `RentalExpenseController`, `EmployeeController`, `PayrollEntryController`, `ShareholderBillController`. Direct GET returns 500. | Add `show()` or `->except(['show'])` on routes. |
| H-11 | HIGH | `backend/database/migrations/0001_01_01_000000_create_users_table.php:14-15` + soft deletes | **Soft-deleted users block username/email reuse** (global UNIQUE, no `WHERE deleted_at IS NULL`). Re-hiring staff with same email fails. | Partial unique indexes on active users only. |
| H-12 | HIGH | `backend/app/Http/Controllers/Api/V1/Rental/TenantController.php:59-62` | **Per-row balance on paginated list:** each page item triggers `TenantBalanceBreakdownService` (~5 queries/tenant). Scales O(n) per page. | Batch balance query or denormalized `balance` column updated on payment/charge events. |
| M-01 | MEDIUM | `backend/routes/api.php:40-47` | Login throttled 6/min but **no persistent account lockout** after repeated failures. | Failed-login counter + temporary lock or exponential backoff. |
| M-02 | MEDIUM | `backend/routes/api.php:44-45` | `verify-reset-code` allows 10/min vs forgot-password 3/min—easier online guessing (mitigated by 5-attempt cap per code). | Align throttles; consider per-email rate limit. |
| M-03 | MEDIUM | `backend/app/Http/Controllers/Api/V1/Admin/SystemSettingsController.php:27-29` | Admin settings API returns SMTP host, port, username (not password). Information disclosure to any admin session. | Return masked username; never expose host details to browser. |
| M-04 | MEDIUM | `backend/app/Http/Controllers/Api/V1/Admin/SystemSettingsController.php:56-59,67-84` | Test-mail endpoint returns raw `$exception->getMessage()` and SMTP diagnostics to client. | Generic user message; log details server-side only. |
| M-05 | MEDIUM | `backend/.env.example:4` | `APP_DEBUG=true` in example env. Production misconfiguration leaks stack traces and SQL. | Default `false`; deployment checklist; health-check gate. |
| M-06 | MEDIUM | `backend/app/Support/Spa.php:12` | `env('SPA_INDEX_PATH')` called outside config cache. Breaks `config:cache` in production. | Move to `config/app.php` or dedicated config file. |
| M-07 | MEDIUM | Multiple `*Controller::index` methods | List filters (`status`, `from`, `to`) accepted without FormRequest validation. Low SQLi risk; inconsistent date/status values possible. | Shared `ListFilterRequest` with enum/date rules. |
| M-08 | MEDIUM | `backend/database/migrations/2026_06_30_000010_create_rental_domain_tables.php` | **No DB unique on `(rental_building_id, house_number)`** for units. App rules only (`UniqueUnitNumber`). | Composite unique index respecting soft deletes. |
| M-09 | MEDIUM | `backend/database/migrations/2026_06_30_000010_create_rental_domain_tables.php:149-163` | **Building electricity bills lack period unique** (water utilities have `building_water_utility_period_unique`). | Add matching unique constraint. |
| M-10 | MEDIUM | `backend/database/migrations/2026_06_30_000010_create_rental_domain_tables.php:189-191` | **No unique on payroll `(employee_id, billing_month, billing_year)`.** Double payroll possible. | DB unique constraint. |
| M-11 | MEDIUM | `TenantBalanceBreakdownService` vs `ArrearsAgingService` | **Two different payment allocation models** (category order vs FIFO by period). Aging report may not match balance badge. | Single allocation engine shared by balance, aging, reports. |
| M-12 | MEDIUM | `backend/app/Services/Legacy/LegacyImporter.php` (~798, 808) | Legacy import uses `(float)` for Kenya water aggregation before insert. Precision loss on large imports. | BCMath throughout import pipeline. |
| M-13 | MEDIUM | `frontend/src/components/rental/TenantHistoryModal.vue:282` | Print uses `${area.innerHTML}` (cloned DOM). Safe today (`{{ }}` only); becomes XSS if `v-html` added to print area. | Build print HTML from escaped data like `paymentReceipt.js`. |
| M-14 | MEDIUM | `backend/app/Models/Concerns/LogsActivity.php:49-51` | Activity logging **silently swallows all exceptions**. Payment audit trail loss is invisible. | Log failures to application log; alert on repeated failure. |
| M-15 | MEDIUM | `backend/database/migrations/2026_06_30_000001_create_documents_table.php` | Documents polymorphic—**no FK to parent**. Parent hard-delete orphans files in storage. | Cascade cleanup job; FK where possible; soft-delete parents only. |
| M-16 | MEDIUM | `backend/app/Services/Rental/ChargeGenerationService.php:47-56` | Dead code with TOCTOU race (`exists()` outside transaction). Not used in production flow but confusing. | Delete or fix if reintroduced. |
| M-17 | MEDIUM | `backend/app/Models/User.php:23-31` | `role`, `status`, `is_manager` in `$fillable`. Admin-only today; privilege escalation if future endpoint uses `$request->all()`. | Guard sensitive columns. |
| M-18 | MEDIUM | `frontend/src/router/index.js:224-228` | `/settings` has no `module` guard—any authenticated user reaches page (admin SMTP tab hidden in UI only). | Backend already protects admin settings API; add route meta for clarity. |
| L-01 | LOW | `frontend/src/utils/tenantRoutes.js` | Exported helpers never imported—dead code. | Remove or use. |
| L-02 | LOW | `backend/app/Http/Resources/RentChargeResource.php` | Unused import `TenantBalanceCalculator`. | Remove import. |
| L-03 | LOW | Payment models | Void/cancel logged as generic `updated` in activity log, not distinct `voided` action. | Extend `LogsActivity` for semantic actions. |
| L-04 | LOW | Migrations + payment requests | `invoice_reference` optional, no uniqueness—duplicate receipt numbers allowed. | Unique per building/tenant if business requires sequential receipts. |
| L-05 | LOW | `backend/app/Services/Rental/TenantService.php` | Move-out `refund_amount` recorded but does not create payment/adjustment—manual reconciliation required. | Document procedure or auto-post adjustment. |
| L-06 | LOW | `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php` | Authenticated endpoints have no per-route throttle. | Rate-limit password change and profile update. |
| L-07 | LOW | `backend/bootstrap/app.php:23-25` | Empty exception handler—no custom sanitization beyond Laravel defaults. | Production exception renderer review. |

---

## 3. CRITICAL & HIGH Findings — Problem & Fix Snippets

### C-02 — Payment double-submit (no idempotency)

**Problem:**

```37:46:backend/app/Http/Controllers/Api/V1/Rental/RentPaymentController.php
    public function store(StoreRentPaymentRequest $request): RentPaymentResource
    {
        $this->authorize('create', RentPayment::class);

        $payment = RentPayment::query()->create([
            ...$request->validated(),
            'discount' => $request->input('discount', 0),
            'status' => RentPaymentStatus::Active,
            'created_by' => $request->user()->id,
        ]);
```

**Suggested fix (pattern):**

```php
public function store(StoreRentPaymentRequest $request): RentPaymentResource
{
    $this->authorize('create', RentPayment::class);

    $payment = DB::transaction(function () use ($request) {
        $tenant = Tenant::query()->lockForUpdate()->findOrFail($request->integer('tenant_id'));

        return RentPayment::query()->firstOrCreate(
            ['idempotency_key' => $request->header('Idempotency-Key')],
            [
                ...$request->validated(),
                'discount' => $request->input('discount', 0),
                'status' => RentPaymentStatus::Active,
                'created_by' => $request->user()->id,
            ],
        );
    });

    return new RentPaymentResource($payment->load(['tenant', 'building']));
}
```

Requires migration: `idempotency_key` UUID nullable unique on `rent_payments` / `sales_payments`.

---

### C-03 — Tenant/building FK mismatch on payments

**Problem:**

```24:26:backend/app/Http/Requests/Rental/StoreRentPaymentRequest.php
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'rental_building_id' => ['required', 'integer', 'exists:rental_buildings,id,deleted_at,NULL'],
```

No cross-check that the tenant belongs to the submitted building.

**Suggested fix:**

```php
'tenant_id' => [
    'required', 'integer',
    Rule::exists('tenants', 'id')
        ->where('rental_building_id', $this->integer('rental_building_id')),
],
```

Apply the same pattern to `StoreSalesPaymentRequest` for `client_id` / `sale_building_id`.

---

### C-01 — No multi-tenant isolation (SaaS blocker)

**Problem:** Grep across entire backend finds **zero** `organization_id`, `company_id`, or tenant-scoping column. Example policy:

```10:18:backend/app/Policies/RentalModulePolicy.php
    public function viewAny(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function view(User $user): bool
    {
        return $user->canAccessRental();
    }
```

The `$model` argument is never used—any rental user accesses any payment by ID.

**Suggested fix (directional):**

```php
// Migration: organizations table; organization_id on users + all domain tables
// Middleware: SetOrganizationFromUser or subdomain resolver
// Policy base:
public function view(User $user, RentPayment $payment): bool
{
    return $user->canAccessRental()
        && $payment->organization_id === $user->organization_id;
}
```

This is a **program-level change**, not a patch.

---

### H-01 — Document policy module-wide access

**Problem:**

```22:34:backend/app/Policies/DocumentPolicy.php
    private function canAccessParent(User $user, Document $document): bool
    {
        $parent = $document->documentable;

        if ($parent instanceof Tenant) {
            return $user->canAccessRental();
        }

        if ($parent instanceof Client) {
            return $user->canAccessSales();
        }

        return false;
    }
```

**Suggested fix:**

```php
if ($parent instanceof Tenant) {
    return $user->canAccessRental()
        && $user->can('view', $parent); // TenantPolicy + future org scope
}
```

---

### H-02 — Any staff can void/cancel payments

**Problem:**

```72:74:backend/app/Http/Controllers/Api/V1/Rental/RentPaymentController.php
    public function void(Request $request, RentPayment $rentPayment): RentPaymentResource
    {
        $this->authorize('update', $rentPayment);
```

`RentalModulePolicy::update` returns true for **all** rental users.

**Suggested fix:**

```php
// RentalModulePolicy.php
public function void(User $user, RentPayment $payment): bool
{
    return $user->canAccessRental() && ($user->isManager() || $user->isAdmin());
}

// RentPaymentController.php
$this->authorize('void', $rentPayment);
```

---

### H-03 — Full-table balance scan on tenant list

**Problem:**

```107:113:backend/app/Http/Controllers/Api/V1/Rental/TenantController.php
        (clone $query)->orderBy('name')->each(function (Tenant $tenant) use (&$withBalance, &$totalOutstanding): void {
            $balance = $this->balanceCalculator->calculate($tenant);

            if (bccomp($balance, '0', 2) > 0) {
                $withBalance++;
                $totalOutstanding = bcadd($totalOutstanding, $balance, 2);
            }
```

Runs for **every** tenant matching filters on **every** list request, independent of pagination.

**Suggested fix:** Defer summary to a background job or SQL aggregate; at minimum compute summary only when `?include_summary=1` and cache for 60s.

---

### H-05 — Balance vs reports inconsistency

**Problem:** `TenantBalanceBreakdownService` sums only explicit purposes (`Water`, `Electricity`, `Rent + service`, `Rent + service + generator`). Charges with `purpose = 'Adjustment'` (created by `ChargeAdjustmentService`) are excluded from `chargedTotal` but appear in report `sum('total_amount')` without purpose filter.

**Suggested fix:** Add `PURPOSE_ADJUSTMENT` to breakdown sums, or exclude adjustments from reports explicitly—**one source of truth**.

---

### H-08 — No auth event logging

**Problem:**

```24:43:backend/app/Http/Controllers/Api/V1/Auth/AuthController.php
    public function login(LoginRequest $request): UserResource
    {
        $user = User::query()->where('username', $request->string('username'))->first();

        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['These credentials do not match our records.'],
            ]);
        }
        // ... no Log:: or ActivityLog entry for success or failure
```

**Suggested fix:**

```php
Log::warning('auth.login.failed', ['username' => $request->username, 'ip' => $request->ip()]);
// on success:
Log::info('auth.login.success', ['user_id' => $user->id, 'ip' => $request->ip()]);
```

---

## 4. Phase Summaries

### Phase 1: Authentication & Authorization

| Item | Result |
|------|--------|
| Roles | `admin`, `rental`, `sales` (`UserRole` enum) + `is_manager` boolean (unused in policies) |
| Route protection | All business routes behind `auth:sanctum` + `role:{module}`; documents behind `auth:sanctum` only (policy enforces module) |
| Controller authorization | **All** business controller actions call `authorize()` or inline `abort_unless(isAdmin())` |
| Missing authorization | None identified on controller actions |
| IDOR | Cross-module blocked by middleware + `DocumentPolicy`. Within-module: **all record IDs accessible** to any user in that module (by design for single-tenant) |
| Password reset | Custom 6-digit code, hashed, 15-min TTL, 5-attempt cap, enumeration-safe messaging |
| Sessions | Sanctum SPA session cookies; logout invalidates session + regenerates CSRF token |
| Rate limiting | Login 6/min; forgot 3/min; verify-code 10/min; reset 6/min; no lockout |
| Mass assignment | `User`, `RentPayment`, `SalesPayment` have sensitive fields in `$fillable`—mitigated by FormRequests today |

### Phase 2: Money & Business-Critical Code

| Item | Result |
|------|--------|
| Currency storage | `decimal(14,2)` columns + `decimal:2` casts—**good** |
| Float usage | Legacy import path only (medium risk) |
| Payment idempotency | **None** |
| Payment transactions/locks | **None** on payment controllers |
| Charge posting | **Strong**—`RentChargePostingGuard`, partial PG uniques, `lockForUpdate` |
| Invoice numbering | Manual `invoice_reference`; no auto-sequence; duplicates allowed |
| Voids/cancels | Any module user; audit via `LogsActivity` as `updated` |
| Balance logic | Central `TenantBalanceBreakdownService`; drift with reports/aging |

### Phase 3: Validation & Input Handling

| Item | Result |
|------|--------|
| FormRequests on store/update | **100% coverage** |
| File uploads | 5 MB, mimes: jpeg/jpg/png/webp/pdf; private disk; auth-gated download |
| SQL injection | Low risk; `ListQuery` uses bound parameters; columns whitelisted |
| XSS (Vue) | No `v-html` in `frontend/src`; print paths mostly escaped |

### Phase 4: Database & Migrations

| Item | Result |
|------|--------|
| Foreign keys | Present on financial tables; `restrictOnDelete` on money paths |
| Indexes | Good on payments/charges; gaps on search columns, charge batch status |
| Unique constraints | Strong on billing periods; gaps on unit numbers, electricity building periods, payroll periods |
| Soft deletes | 11 tables; conflicts with global UNIQUE on users.username/email |

### Phase 5: Code Quality & Operational Readiness

| Item | Result |
|------|--------|
| N+1 / query storms | **High** on tenant/client lists, dashboards, reports |
| Duplicated logic | Water/electricity bill services; balance vs aging allocation |
| Debug artifacts | No `dd()`/`dump()`/`console.log` in app source |
| Secrets | None hardcoded in source; `.env.example` has `APP_DEBUG=true` |
| Error leakage | Depends on `APP_DEBUG`; SMTP test leaks exception messages |
| Logging | Domain CRUD via `LogsActivity`; **no auth or payment-specific security log channel** |

---

## 5. Coverage List — Files & Directories Examined

### Backend — Routes & bootstrap
- `backend/routes/api.php`
- `backend/routes/web.php` (referenced)
- `backend/routes/console.php` (referenced)
- `backend/bootstrap/app.php`

### Backend — Middleware (2/2)
- `backend/app/Http/Middleware/EnsureSpaSession.php`
- `backend/app/Http/Middleware/EnsureUserRole.php`

### Backend — Controllers (34/34)
- `backend/app/Http/Controllers/Controller.php`
- `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php`
- `backend/app/Http/Controllers/Api/V1/DocumentController.php`
- `backend/app/Http/Controllers/Api/V1/Admin/ActivityLogController.php`
- `backend/app/Http/Controllers/Api/V1/Admin/RecycleBinController.php`
- `backend/app/Http/Controllers/Api/V1/Admin/SystemSettingsController.php`
- `backend/app/Http/Controllers/Api/V1/Admin/UserController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/RentalDashboardController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/RentalBuildingController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/RentalUnitController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/TenantController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/TenantDocumentController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/RentChargeController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/ChargeBatchController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/RentPaymentController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/TenantWaterBillController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/TenantElectricityBillController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/BulkMeterReadingController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/MeterReadingContextController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/BuildingUtilityController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/RentalExpenseController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/EmployeeController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/PayrollEntryController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/ShareholderController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/ShareholderBillController.php`
- `backend/app/Http/Controllers/Api/V1/Rental/RentalReportController.php`
- `backend/app/Http/Controllers/Api/V1/Sales/SalesDashboardController.php`
- `backend/app/Http/Controllers/Api/V1/Sales/SaleBuildingController.php`
- `backend/app/Http/Controllers/Api/V1/Sales/SaleUnitController.php`
- `backend/app/Http/Controllers/Api/V1/Sales/ClientController.php`
- `backend/app/Http/Controllers/Api/V1/Sales/ClientDocumentController.php`
- `backend/app/Http/Controllers/Api/V1/Sales/SalesPaymentController.php`
- `backend/app/Http/Controllers/Api/V1/Sales/SalesExpenseController.php`
- `backend/app/Http/Controllers/Api/V1/Sales/SalesReportController.php`

### Backend — Policies (11/11)
- `backend/app/Policies/UserPolicy.php`
- `backend/app/Policies/RentalBuildingPolicy.php`
- `backend/app/Policies/RentalUnitPolicy.php`
- `backend/app/Policies/TenantPolicy.php`
- `backend/app/Policies/ChargeBatchPolicy.php`
- `backend/app/Policies/RentalModulePolicy.php`
- `backend/app/Policies/SaleBuildingPolicy.php`
- `backend/app/Policies/SaleUnitPolicy.php`
- `backend/app/Policies/ClientPolicy.php`
- `backend/app/Policies/SalesModulePolicy.php`
- `backend/app/Policies/DocumentPolicy.php`

### Backend — Models (30/30)
- All files under `backend/app/Models/` including `Concerns/LogsActivity.php`, `Concerns/HasDocuments.php`, `Concerns/HasSalesCurrency.php`

### Backend — Form requests (56/56)
- All files under `backend/app/Http/Requests/` (Auth, Admin, Rental, Sales, Concerns)

### Backend — API resources (27/27)
- All files under `backend/app/Http/Resources/`

### Backend — Services (29/29)
- All files under `backend/app/Services/` (Rental, Sales, Legacy, Auth, DocumentService)

### Backend — Support, rules, enums, providers
- `backend/app/Support/ListQuery.php`
- `backend/app/Support/RecycleBinRegistry.php`
- `backend/app/Support/Spa.php`
- `backend/app/Support/MoneyConfig.php`
- `backend/app/Support/PasswordRules.php`
- `backend/app/Rules/UniqueBuildingName.php`
- `backend/app/Rules/UniqueUnitNumber.php`
- All 16 files under `backend/app/Enums/`
- `backend/app/Providers/AppServiceProvider.php`

### Backend — Config (14/14)
- All files under `backend/config/`

### Backend — Migrations (19/19)
- All files under `backend/database/migrations/`

### Backend — Tests (32/32, inventory)
- All files under `backend/tests/` (Feature + Unit suites catalogued; tests inform coverage gaps but were not re-run as part of this audit)

### Backend — Environment templates
- `backend/.env.example`
- `backend/phpunit.xml`

### Frontend — Source (116 files)
- All files under `frontend/src/` including:
  - `views/` (rental, sales, admin, auth)
  - `components/` (data, layout, rental, sales, ui, auth, dashboard)
  - `api/`, `composables/`, `stores/`, `router/`, `utils/`, `config/`
- Grep sweep across all `frontend/src/**/*.{vue,js}` for `v-html`, `innerHTML`, `console.log`, `dangerouslySetInnerHTML`

### Frontend — Build / config (referenced)
- `frontend/package.json`
- `frontend/vite.config.js`
- `frontend/src/router/index.js`
- `frontend/src/stores/auth.js`

### Intentionally excluded from line-by-line review
- `frontend/node_modules/` (third-party)
- `frontend/dist/` (build artifacts)
- `backend/vendor/` (third-party)
- `backend/storage/legacy/*.sql` (data dumps; noted for float columns only)

---

## 6. Recommended Pre-Launch Checklist (First Customer)

1. Fix **C-02, C-03, C-04** (payment integrity) before accepting real money.
2. Set **`APP_DEBUG=false`**, `SESSION_SECURE_COOKIE=true`, run `php artisan config:cache`.
3. Enforce **PostgreSQL** in production (partial unique indexes depend on it).
4. Add **auth event logging** (H-08).
5. Restrict **void/cancel** to managers (H-02) or document accepted risk in writing.
6. Load-test tenant list and dashboard with realistic tenant count (H-03, H-12).
7. Fix or exclude broken **`show` routes** (H-10).
8. Do **not** market as multi-tenant SaaS until **C-01** is designed and implemented.

---

*End of audit report. No code was modified during this review.*
