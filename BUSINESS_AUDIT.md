# Business Logic Audit

**System:** Rent & Sales Management Platform (Laravel + Vue 3 + PostgreSQL)  
**Context:** Somali property-management companies (rental + unit sales)  
**Date:** 2026-07-06  
**Scope:** Business rules correctness, completeness, enforcement — not code style  
**Mode:** Audit only — no code modified  

---

## Executive Summary

This system implements a **monthly charge-batch workflow** for rental (rent + service + utilities) and a **lump-sum sales ledger** (agreed price minus payments/deposit). Core occupancy and sold-unit rules are enforced in **service-layer transactions**, not as lease contracts with end dates. **There is no formal lease entity** — only `tenants` (renters) with `start_date`, `status`, and unit `occupied`/`vacant` flags.

**Strengths:** Double-occupancy blocked on normal registration path; charge duplicate prevention is strong (batch uniqueness + posting guard + PostgreSQL partial uniques); balances are **derived** from charges and payments (no stored balance column to drift); partial payments work by design.

**Gaps for Somali production:** No lease end dates or auto-expiry; no proration on mid-month move-out; no late fees; no mobile-money integration; manual payment entry without maker-checker; receipts are optional prints with non-sequential numbers; timezone is UTC not Mogadishu; sales has no installment schedule or reservation stage; SOS currency not supported; dashboard occupancy can disagree with active-tenant reality; arrears aging uses different math than tenant balance badges.

---

## Question-by-Question Analysis

### LEASE / RENTAL LIFECYCLE

#### 1. Can a unit be double-leased (two active leases, overlapping dates)?

| Aspect | Finding |
|--------|---------|
| **Implementation** | `TenantService::register()` locks unit and rejects if `status !== Vacant` (`backend/app/Services/Rental/TenantService.php:22-28`). Unit set to `Occupied` on register (`:42`). `TenantService::update()` requires new unit to be `Vacant` when moving (`:72-76`). Test: `TenantFlowTest::test_cannot_register_tenant_on_occupied_unit` (`backend/tests/Feature/Rental/TenantFlowTest.php:93-112`). |
| **DB level** | **No** unique constraint on “one active tenant per unit”. `tenants` table has no `end_date`; only `status` enum (`backend/database/migrations/2026_06_30_000010_create_rental_domain_tables.php:32-55`). `RentalUnit::activeTenant()` is `hasOne` where `status = active` (`backend/app/Models/RentalUnit.php:53-57`) — if two active rows exist, behavior is undefined. |
| **Verdict** | **Correct on happy path** via application rules. **Not correct at DB level** — data corruption or legacy import could create two active tenants on one unit. Unit `status` cannot be edited via API (`UpdateRentalUnitRequest` has no `status` field), reducing bypass risk, but **manual DB / import** can desync unit vacant + tenant active. |
| **Missing** | Partial unique index: `(rental_unit_id) WHERE status = 'active'`. Reconciliation job: unit `occupied` ⇔ exists active tenant. Formal lease record with start/end dates. |

#### 2. Lease end: automatic unit status update?

| Aspect | Finding |
|--------|---------|
| **Implementation** | **Manual only.** `TenantService::moveOut()` sets tenant `inactive` and unit `vacant` (`TenantService.php:94-120`). No `end_date` on tenant (grep: no `end_date` / `lease_end` in codebase). No scheduled job updates unit status on calendar date. |
| **Scheduled jobs** | Only `rental:generate-charge-batches` monthly (`backend/bootstrap/app.php:26-27`) — generates charge drafts, not lease lifecycle. |
| **If nobody updates** | Tenant remains **active** forever; unit stays **occupied**; monthly charge batches keep including them (`ChargeBatchService.php:92-99` filters `TenantStatus::Active` and `start_date <= period end`). |
| **Verdict** | **Incomplete** for real lease management. Acceptable only if business process is “move-out is always recorded manually.” |
| **Missing** | `lease_end_date`, reminders, auto-expire workflow, scheduled “lease ending soon” alerts. |

#### 3. Mid-cycle termination: final invoice / proration?

| Aspect | Finding |
|--------|---------|
| **Implementation** | `moveOut()` records `refund_amount`, `reason`, `moved_out_at` on `tenant_move_outs` (`TenantService.php:103-111`). **No** prorated rent charge, **no** final utility true-up, **no** automatic credit/debit `RentCharge` for partial month. |
| **Charges after move-out** | Inactive tenants excluded from new batch generation (`ChargeBatchService.php:95`). Existing posted charges remain; balance still computed from all charges minus payments (`TenantBalanceBreakdownService.php`). |
| **Verdict** | **Incorrect / incomplete** for standard property management. Refund is a memo field only — not posted to ledger. |
| **Missing** | Proration to `moved_out_at`, final meter reads enforcement on move-out, deposit settlement workflow, move-out statement PDF. |

#### 4. Can lease dates be edited after payments exist? What breaks?

| Aspect | Finding |
|--------|---------|
| **Implementation** | `UpdateTenantRequest` allows `start_date` on active tenants (`backend/app/Http/Requests/Rental/UpdateTenantRequest.php:35`). `TenantService::update()` does **not** check for existing payments or charges. |
| **Effect** | `start_date` affects charge-batch inclusion: tenants with `start_date > period end` are skipped (`ChargeBatchService.php:96-99`). Changing `start_date` retroactively changes **future** batch membership, not historical charges. |
| **Verdict** | **Allowed but unguarded.** Editing dates after money exists can confuse reporting and batch eligibility; no audit warning. |
| **Missing** | Block or warn when `rent_payments` or `rent_charges` exist; immutable contract dates with amendment log. |

---

### RENT BILLING & ARREARS

#### 5. Recurring rent invoices: how generated? Duplicate run?

| Aspect | Finding |
|--------|---------|
| **Mechanism** | **Charge batch workflow**, not classic “invoices.” (1) **Scheduled:** `rental:generate-charge-batches` 1st of month (`bootstrap/app.php:27`, `GenerateDraftChargeBatches.php:38-46`). (2) **On-demand:** `ChargeBatchController@generate` → `ChargeBatchService::generateDraft()`. (3) **Manual approval:** staff approve batch items → `ChargeBatchPostingService` posts `RentCharge` rows. |
| **Duplicate batch** | `generateDraft()` rejects if batch exists for building+month+year (`ChargeBatchService.php:68-78`). DB unique on `(rental_building_id, billing_month, billing_year)` (`2026_06_30_150100_create_charge_batches_tables.php:23`). |
| **Duplicate charges** | `RentChargePostingGuard::createOrFail()` locks and rejects duplicate purpose/period (`RentChargePostingGuard.php:27-43`). PostgreSQL partial unique `rent_charges_unique_billable_period` (`2026_07_02_100000_enforce_rent_charge_financial_integrity.php:16-20`). Test: `ChargeIntegrityTest::test_approve_all_twice_does_not_double_tenant_balance`. |
| **Verdict** | **Correct** for duplicate prevention on approve/post path. Scheduled + manual generate both safe for batch level; posting is idempotent-guarded. |
| **Missing** | Generator purpose (`Rent + service + generator`) **not** in partial unique index purposes list — legacy generator rows could duplicate if created outside batch guard. PDF invoices to tenants; email delivery. |

#### 6. Partial payments: supported? Balance stored or derived?

| Aspect | Finding |
|--------|---------|
| **Implementation** | Any positive `amount` + optional `discount` accepted (`StoreRentPaymentRequest.php:27-28`, `ValidatesRentPayment.php:24-39`). Balance **derived**: `TenantBalanceBreakdownService` sums charges by category, sums active payments, allocates water → electricity → services → rent (`TenantBalanceBreakdownService.php:45-72, 123-137`). |
| **Stored balance?** | **No** `balance` column on `tenants`. Computed on read in lists/reports. |
| **Drift risk** | **Low** if all charges/payments are in DB. **Medium** if adjustment-purpose charges exist — excluded from breakdown sums (`sumWaterCharges` etc. filter by purpose) but included in `RentalReportService` `sum('total_amount')` (`RentalReportService.php:51`) — report charged vs badge balance can disagree. |
| **Verdict** | **Correct** for partial payments as derived ledger. Category allocation is a **business policy** (utilities paid first) — document for staff. |
| **Missing** | Explicit “payment allocation” UI showing which charges were covered; stored running balance optional for performance. |

#### 7. Overdue logic and timezone

| Aspect | Finding |
|--------|---------|
| **Implementation** | `ArrearsAgingService::daysPastDue()` — due date = **last day of billing month** (`ArrearsAgingService.php:175`). Compare to `$asOf` (default `now()->startOfDay()`, `:29`). Buckets: 0–30, 31–60, 61–90, 90+ days (`:184-198`). |
| **“Overdue” elsewhere** | No generic “overdue” flag on tenant. Dashboard “outstanding” = positive `total_due` from breakdown (`RentalDashboardService.php:103-136`), not days past due. |
| **Timezone** | `config/app.php:70` → **`UTC`**. No `Africa/Mogadishu`. `Carbon::now()` and `paid_at` comparisons use app timezone. Month boundaries for collections use server local interpretation of `startOfMonth()` (`RentalDashboardService.php:34-36, 152-155`). |
| **Verdict** | **Partially correct** for “rent due end of calendar month” model. **Wrong** for Somalia if business expects due on day 5, or Mogadishu midnight boundaries, or payment date in EAT. |
| **Missing** | Configurable due day per building; `APP_TIMEZONE=Africa/Mogadishu`; explicit overdue notifications. |

#### 8. Late fees / penalties

| Aspect | Finding |
|--------|---------|
| **Implementation** | **None.** Grep across application code: no late fee, penalty, or interest logic. |
| **Verdict** | **Not implemented.** |
| **Missing** | Configurable late fee rules, auto-posting adjustment charges, grace period, Somali regulatory compliance review. |

#### 9. Currency

| Aspect | Finding |
|--------|---------|
| **Rental** | Global `RENTAL_CURRENCY` env, default **KES** (`backend/config/money.php:15-17`, `MoneyConfig::rentalCurrency()`). All rental amounts implicit KES — no per-row currency on rental payments/charges. |
| **Sales** | `currency_code` column on clients, payments, expenses, units (`2026_07_02_120000_add_currency_code_to_sales_tables.php`). Default from `SALES_CURRENCY` env (**USD**). PostgreSQL **CHECK** forces `currency_code = 'USD'` only (`:22-25`). `ProhibitsSalesCurrencyOverride` blocks client sending currency (`ProhibitsSalesCurrencyOverride.php:15-17`). |
| **Frontend** | Hardcoded `MODULE_MONEY`: rental KES, sales USD (`frontend/src/utils/money.js:1-4`) — not loaded from API settings. |
| **SOS (Somali Shilling)** | **Not supported** anywhere. |
| **Mixing bugs** | Rental and sales are separate modules with separate buildings — **no cross-module currency mix** in one ledger. Risk: sales CHECK prevents SOS/USD mix per company; rental cannot record SOS if operations use shillings. |
| **Verdict** | **Correct** as fixed dual-currency deployment (KES rental + USD sales). **Incorrect** for companies wanting SOS or per-building currency. |
| **Missing** | SOS support; per-company currency settings; remove hardcoded USD CHECK; frontend currency from API. |

---

### SALES MODULE

#### 10. Unit sale flow: reservation → installments → transfer? Sold unit leased?

| Aspect | Finding |
|--------|---------|
| **Flow** | **Register client** on **available** unit → unit `sold` (`ClientService.php:18-44`). States: `available` \| `sold` only (`SaleUnitStatus` enum, migration `sale_units.status`). **No** reservation, **no** title transfer stage, **no** installment schedule entity. |
| **Sold twice** | Blocked: `register()` requires `SaleUnitStatus::Available` (`:23-27`). `disable()` client sets unit back to `available` (`:98-101`). |
| **Sold unit leased (rental)** | Rental and sales use **separate** `rental_buildings` / `sale_buildings` — no shared unit registry. Same physical apartment could exist in both modules as duplicate data entry — **not prevented**. |
| **Verdict** | **Correct** within sales module for single buyer per unit. **Incomplete** for real estate sales pipeline. **Gap** between rental and sales physical asset identity. |
| **Missing** | Reservation/hold with expiry; installment plan; handover checklist; link or dedupe rental unit ↔ sale unit. |

#### 11. Installment schedules

| Aspect | Finding |
|--------|---------|
| **Implementation** | **None.** `agreed_sale_price`, `deposit`, and ad-hoc `sales_payments` (`ClientBalanceCalculator.php:17-30`). Balance = agreed − (payments + deposit + discounts). |
| **Missed installments** | **No** due dates, **no** overdue installments, **no** reminders. UI label “Installment payment” on receipt is cosmetic (`frontend/src/utils/paymentReceipt.js:249`). |
| **Verdict** | **Not implemented** — lump-sum contract with payment history only. |
| **Missing** | Schedule generator (e.g. monthly until handover), missed-payment alerts, penalty terms. |

---

### PAYMENTS (Somalia context)

#### 12. EVC Plus / eDahab / WaafiPay / cash — recording & controls

| Aspect | Finding |
|--------|---------|
| **Gateway integration** | **None.** No webhooks, no API clients, no signature verification, no idempotency keys on callbacks. |
| **Manual recording** | Rental: `amount`, `discount`, `invoice_reference`, `paid_at` (`StoreRentPaymentRequest.php`). Sales: adds `bank`, `remark` (`StoreSalesPaymentRequest.php:27-29`). Cash and mobile money are indistinguishable in data model. |
| **Fraud controls** | **None.** Any rental/sales user can create, edit, void/cancel payments (`RentalModulePolicy` — module role only). No maker-checker, no dual approval, no amount limits, no separate cashier role. |
| **Overpayment** | Rental requires `overpayment_acknowledged` if payment > balance (`ValidatesRentPayment.php:59-80`). Sales: **no** equivalent check. |
| **Verdict** | **Manual ledger only** — acceptable for small trusted teams, **not production-ready** for high-volume or multi-staff Somalia operations without controls. |
| **Missing** | Payment method enum (EVC Plus, eDahab, Waafi, cash, bank); reference number validation; maker-checker void; daily cash reconciliation report; optional gateway webhooks with HMAC + idempotency. |

#### 13. Receipts

| Aspect | Finding |
|--------|---------|
| **Generation** | **Optional** — user clicks “Print receipt” in `PaymentsView.vue` / `TenantPaymentHistory.vue` → `paymentReceipt.js`. **Not** auto-created on save. |
| **Numbering** | `invoice_reference` optional manual field (`StoreRentPaymentRequest.php:29`). Fallback display: `RCP-{payment.id}` (`paymentReceipt.js:108-109`). **No** sequential server-side sequence; **no** gap detection; duplicates allowed. |
| **Verdict** | **Incomplete** for formal receipting. Fine for internal acknowledgment. |
| **Missing** | Auto-assign receipt number on post; unique per company; printable mandatory copy; QR verification; Somali/Arabic receipt text. |

---

### REPORTING

#### 14. Do dashboard totals match raw records?

| Metric | Source | Matches raw? | Notes |
|--------|--------|--------------|-------|
| **Occupancy (units)** | `RentalUnit::count()` by `status` occupied/vacant (`RentalDashboardService.php:39-41, 55-60`) | **Usually** | Based on **unit.status**, not count of active tenants. Can drift if unit/tenant status desynced. |
| **Active tenants** | `Tenant::where(status, Active)->count()` (`:42`) | **Yes** | Independent of unit status. |
| **Collections (month)** | `SUM(rent_payments.amount)` active, `whereBetween paid_at` (`:152-155`) | **Yes** | Excludes voided. Does not add `discount` to “collected” in `current_month` amount (discount counted separately in some reports). |
| **Outstanding** | Per-tenant `TenantBalanceBreakdownService::breakdown()` summed (`:116-136`) | **Yes** vs breakdown definition | **May not match** arrears aging totals — different allocation algorithms. |
| **Tenant balance report** | `RentalReportService::tenantBalances()` uses same `balanceCalculator` (`:45`) but `charged_amount` = all-purpose sum (`:51`) | **Partial** | `balance` column matches badge; `charged_amount` can exceed what breakdown uses. |
| **Arrears aging** | FIFO by charge period (`ArrearsAgingService.php:109-139`) | **Internally consistent** | **Disagrees** with `TenantBalanceBreakdownService` category-order allocation — same tenant can show different “total_balance” in aging vs tenant list. |
| **Sales dashboard** | `ClientBalanceCalculator` per client (`SalesDashboardService`) | **Yes** vs formula | Tests in `SalesDashboardTest`. |

| Verdict | Dashboard collection sums are trustworthy. **Occupancy** and **outstanding vs aging** require staff training — numbers differ by design/bugs. |
| **Missing** | Automated reconciliation test: dashboard totals = SQL aggregates; single allocation engine. |

#### 15. Company settings respected everywhere?

| Setting | Defined | Actually used |
|---------|---------|---------------|
| Rental currency | `config/money.php`, `.env` | `MoneyConfig`, resources, reports — **consistent** backend |
| Sales currency | env + `currency_code` column | Server-set on create; CHECK locks USD — **consistent but inflexible** |
| Notification emails | `config/notifications.php` | Charge batch email only — **not** per-tenant |
| Branding / app name | `config/app.php` | Receipt print uses hardcoded `'Rent & Sales Platform'` (`paymentReceipt.js:cr function area`) |
| Late fees, due dates | N/A | N/A |

| Verdict | **Deployment-level** env settings only — no per-company settings entity. Frontend money display **ignores** API `currency_code` on sales rows (uses hardcoded MODULE_MONEY). |
| **Missing** | Company settings table; Settings API consumed by frontend; branded receipts. |

---

## Findings Table

| ID | Severity | Area | File:line (primary) | Description | Suggested fix (business) |
|----|----------|------|---------------------|-------------|------------------------|
| B-01 | **CRITICAL** | Payments | No gateway code | No EVC Plus / eDahab / WaafiPay integration; all payments manual | Add payment method + reference fields; plan gateway webhooks with idempotency |
| B-02 | **CRITICAL** | Payments | `RentPaymentController.php:37-46`, `SalesPaymentController.php:38-47` | No maker-checker; any staff can record/void large payments | Segregate cashier vs approver; void requires manager |
| B-03 | **HIGH** | Lease | `TenantService.php` — no `end_date` | No lease term end; units stay occupied until manual move-out | Add lease end date + expiry workflow |
| B-04 | **HIGH** | Lease | `tenants` migration — no DB unique on active unit | Double active tenant possible via import/DB drift | DB constraint: one active tenant per unit |
| B-05 | **HIGH** | Termination | `TenantService.php:103-111` | Move-out refund not posted; no proration | Final statement + prorated charge/credit |
| B-06 | **HIGH** | Currency | `money.php`, `2026_07_02_120000` migration | SOS not supported; sales locked to USD CHECK | Per-company currency including SOS |
| B-07 | **HIGH** | Sales | `ClientBalanceCalculator.php` | No installment schedule or missed-payment logic | Installment schedule with due dates |
| B-08 | **HIGH** | Reporting | `ArrearsAgingService.php` vs `TenantBalanceBreakdownService.php` | Two allocation methods → different balances | Single source of truth for outstanding |
| B-09 | **MEDIUM** | Billing | `config/app.php:70` | Timezone UTC, not Africa/Mogadishu | Set Mogadishu TZ; document due-date rules |
| B-10 | **MEDIUM** | Billing | — | Late fees not implemented | Configurable penalty rules |
| B-11 | **MEDIUM** | Receipts | `paymentReceipt.js:108-109` | Non-sequential receipt numbers; optional print | Mandatory sequential receipts |
| B-12 | **MEDIUM** | Occupancy | `RentalDashboardService.php:39-41` | Occupancy from unit flag, not tenant count | Reconcile unit status with active tenant |
| B-13 | **MEDIUM** | Sales payments | `StoreSalesPaymentRequest.php` | No overpayment guard (rental has one) | Align sales validation with rental |
| B-14 | **MEDIUM** | Cross-module | Separate building tables | Same physical unit can exist in rental + sales | Asset registry or building link |
| B-15 | **MEDIUM** | Reports | `RentalReportService.php:51` | Charged sum includes all purposes; balance excludes adjustments | Align report columns with balance logic |
| B-16 | **LOW** | Lease edit | `UpdateTenantRequest.php:35` | `start_date` editable after payments | Warn or block when financial history exists |
| B-17 | **LOW** | Generator charges | `2026_07_02_100000` partial unique | `Rent + service + generator` not in unique index | Add to billable purposes or document exception |
| B-18 | **LOW** | Frontend | `frontend/src/utils/money.js:1-4` | Hardcoded KES/USD display | Load from API settings |
| B-19 | **LOW** | Receipt branding | `paymentReceipt.js` | Generic platform title on receipts | Company name/logo from settings |

---

## Missing Features for Production (Somali Property Management — Day One)

These are **not implemented** today but are commonly required before trusting the system with real money and legal disputes:

### Rental / tenancy
- [ ] Formal **lease contract** (start, end, renewal, notice period)
- [ ] **Automatic lease expiry** reminders and unit status update
- [ ] **Prorated rent** on mid-month move-in / move-out
- [ ] **Deposit ledger** (hold, deduct damages, refund) tied to move-out
- [ ] **Late payment penalties** (configurable % or flat fee after grace days)
- [ ] **Somali/Arabic** tenant statements and SMS reminders (mobile-first)
- [ ] **Rent roll** export aligned with tax/accounting expectations
- [ ] **Generator / shared meter** billing rules documented in UI (partial support in charge purposes)

### Utilities (common in Mogadishu buildings)
- [ ] **Prepaid vs postpaid** electricity models
- [ ] **Shared water tank** cost allocation beyond per-tenant meter (Kenya water aggregation exists in legacy import only)

### Payments (Somalia)
- [ ] **Payment method** capture: EVC Plus, eDahab, Waafi, Premier Wallet, bank, cash
- [ ] **Transaction reference** required for electronic payments
- [ ] **Daily cashier reconciliation** (expected vs recorded)
- [ ] **Maker-checker** on voids and large discounts
- [ ] Optional **payment gateway** webhooks (signature verify, idempotent callback handling)

### Sales (off-plan / installment sales)
- [ ] **Reservation** with expiry before full client registration
- [ ] **Installment schedule** (amount, due date, status)
- [ ] **Missed installment** aging and reminders
- [ ] **Handover / title** milestone tracking
- [ ] **SOS and USD** (or chosen currency) per project without hardcoded CHECK

### Receipts & compliance
- [ ] **Sequential receipt numbering** (no gaps policy)
- [ ] **Auto-receipt** on every payment (print/PDF/SMS)
- [ ] Company **letterhead** (name, address, phone, tax ID if applicable)

### Reporting & operations
- [ ] **Single balance engine** (dashboard = tenant list = aging = PDF statement)
- [ ] **Africa/Mogadishu** timezone for month-close
- [ ] **Occupancy reconciliation** report (unit status vs active tenants)
- [ ] **Audit trail** for payment voids (who/when/why) beyond generic activity log

### Organization (if multi-branch)
- [ ] Per-company **currency, branding, fee rules** (see `TENANCY_ASSESSMENT.md`)
- [ ] Branch/building-level **due day** configuration

---

## Summary Verdict

| Domain | Production-ready? |
|--------|-------------------|
| Basic rent charging (batch → approve → post) | **Yes**, with trained staff |
| Utility metering + batch integration | **Yes**, if readings entered before batch |
| Tenant move-in / move-out (simple) | **Yes**, manual |
| Partial payments & balance tracking | **Yes**, derived ledger |
| Lease law / contract lifecycle | **No** |
| Somalia mobile money | **No** |
| Installment sales | **No** |
| Late fees & formal overdue | **No** |
| Receipts & fiscal numbering | **No** |
| Staff fraud controls | **No** |
| SOS / flexible currency | **No** |

**Overall:** Suitable as an **internal operations tool** for a single company already using manual mobile-money reconciliation, **not** as a complete commercial property-management product for Somalia without the missing features above.

---

*End of business logic audit. No code was modified.*
