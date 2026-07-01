# Requirements — Rent & Sales Management Platform (Greenfield Rebuild)

**Date:** 2026-06-30  
**Status:** Step 0 — awaiting review (no code, no framework, no new project directory yet)  
**Legacy reference (read-only):** `/home/ali/legacy-app` (`rent/`, `sales/` modules)  
**Source documents used:**
- `CODEBASE_AUDIT.md`
- `MODULE_DIFF.md`
- `rasulmar_karama.sql` (schema/data reference)
- Direct reading of legacy `rent/` and `sales/` PHP where audits were silent

**Not found in workspace (referenced in brief but absent):** `RENT_SALES_DIFF.md`, `DATABASE_RELATIONSHIPS_RENT_SALES.md`. Findings below were re-derived from the sources above.

---

## Document purpose

This describes **what the new system must do** in business/functional terms. It is derived from legacy behavior worth preserving, not from legacy implementation patterns. Items marked **legacy bug — not reproduced** must not be carried into the greenfield build.

---

## 1. User roles & access scope

### 1.1 Roles in scope for this rebuild (proposed — confirm)

| Role | Legacy equivalent | Login redirect | Module |
|------|-------------------|----------------|--------|
| **Rental staff** | `users.type = 'Rental'` | `rent/index.php` | Rent management |
| **Sales staff** | `users.type = 'Sales'` | `sales/index.php` | Sales management |

Each role sees only their module’s navigation and data operations after login. The new system should enforce this with proper authorization (policies), not only UI hiding.

### 1.2 Roles explicitly **deferred** (out of scope unless you expand scope later)

| Role / area | Legacy evidence | Reason deferred |
|-------------|-----------------|-----------------|
| **Admin** (`users.type = 'Admin'`) | `admin/` module — full rent + sales + construction + accounts | Separate superseding portal; user asked to confirm Admin is out of scope |
| **All** (`users.type = 'All'`) | `all/` module — byte-identical to `rent/admin_class.php` + combined nav | Combined-role portal; deferred |
| **Construction** (`users.type = 'Construction'`) | `construction/` — materials, companies, sites | Explicitly excluded in rebuild brief |
| **User management screens** | `users.php`, `manage_user.php`, `createaccount.php` (admin nav) | Admin-only; deferred |
| **Accounts / deposits ledger** (“Ahmed” / Amaano) | `accounts.php`, `createaccount.php`, `deposits`, `withdraw`, `create_source`, `ramaano.php` | Admin nav only; not in `rent/` or `sales/` nav |
| **Site settings** | `site_settings.php`, `system_settings` table | Admin-level; deferred unless you want branding in v1 |
| **Bulk water payment UI** | `admin/bulkpayment.php` using `water_bill_new` | Admin-only; deferred |

### 1.3 Authentication (functional requirements)

- Users authenticate with **username + password**.
- Only users with `status = Active` may log in (legacy: `users.status`).
- Session identifies the user’s **display name** and **role**.
- Users must be able to **change their own password** while logged in (legacy: `password_change.php` / `save_passchange`).
- **Legacy bugs — not reproduced:**
  - Plaintext password storage and plaintext comparison on login (`admin_class.php` `login()`).
  - `admin/navbar.php` checking `$_SESSION['admin_login']` while `index.php` sets `$_SESSION['login_admin']`.
  - Typo session key `login_contruction` for Construction role (deferred role anyway).

### 1.4 Authorization (functional requirements)

- Rental staff: full rent capabilities in §2; no sales write access.
- Sales staff: full sales capabilities in §3; no rent write access.
- **Open question for you:** Should Rental staff have read-only visibility into sales (or vice versa)? Legacy modules are fully separated — default proposal is **no cross-module access**.

---

## 2. Rent management capabilities

Legacy module: `rent/`. Canonical business-logic reference: `rent/admin_class.php` (byte-identical to `all/`; preferred over `sales/` fork).

### 2.1 Buildings & units (rent)

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Rent buildings** | CRUD for rent property groupings (legacy table `categories`; UI label “Buildings”). | `categories.php`, `save_category()` |
| **Rent apartments / units** | CRUD units within a building: house number, floor, description, monthly rent price, status. | `houses.php`, `save_house()` |
| **Unit status** | `Vacant` on create; becomes `Occupied` when tenant assigned; returns to `Vacant` on move-out. | `save_house()`, `save_tenant()`, `update_status.php` |
| **Unit listing views** | Dashboard counts; list occupied vs vacant units. | `home.php`, `occupied.php`, `vacant.php` |

**Business rules to preserve:**
- Monthly rent amount for charging comes from `houses.price` at charge-generation time.
- Apartment is selected from **vacant** units in the chosen building when onboarding a tenant (`fetch_apt.php` pattern).

**Naming note for new system:** Legacy overloads “category” = rent building, “house” = apartment unit. New system should use clear domain names (e.g. `rental_buildings`, `rental_units`).

### 2.2 Tenants

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Tenant registration** | Create tenant linked to building + apartment; marks unit occupied. | `manage_tenant.php`, `save_tenant()` |
| **Tenant profile** | Name, phone, gender, email, passport/ID, deposit, start date, next-of-kin fields (name, address, ID, phone). | `manage_tenant.php` |
| **Tenant update** | Edit tenant; if apartment changes, vacate old unit and occupy new unit. | `save_tenant()` in `rent/admin_class.php` |
| **Active tenant list** | List tenants where `status = 'active'` with balance column. | `tenants.php` |
| **Moved-out tenants** | List tenants where `status = 'inactive'`. | `tenantout.php` |
| **Move-out** | Record move-out with refund amount and reason; set tenant inactive; set unit vacant; append `moved_out` record. | `update_status.php` |
| **Tenant statement** | Per-tenant payment/charge history view (popup/print). | `tenant_statement.php`, `view_payment.php` |
| **Delete tenant** | Hard delete tenant record (legacy). | `delete_tenant()` |

**Business rules to preserve:**
- On create: insert tenant, set apartment `Occupied`.
- On move-out: insert into move-out log (tenant_id, building, apartment, refund, reason, actor, date); tenant → inactive; apartment → Vacant.
- Deposit amount captured per tenant (`Deposit` field); total deposits surfaced on income statement header (legacy `income.php`).

**Legacy bugs / quirks — not reproduced:**
- Tenant `status` field populated from form input named `PassImage` (mislabeled “Status” dropdown storing `active` in a column meant for images). New system: explicit `status` enum (`active` / `inactive`).
- `get_tdetails()` computes a month-based payable estimate but **tenant list balance does not use it** (see §2.4).

**Open question:** Should tenant hard-delete remain, or should move-out + inactive be the only retirement path?

### 2.3 Rent charges

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Monthly charge generation** | For each **active** tenant, once per calendar month, create a charge row if one does not exist for current `YYYY-MM`. | `charge.php` included by `tenants.php` |
| **Charge composition** | `rent` = unit price; `service` = tenant `service_amount`; `total` = rent + service; `purpose` = `'Rent + service'`. | `charge.php` |
| **Charge adjustment** | Edit rent/service on existing charge; recalculate total. | `save_charge()`, `charge_list.php` |
| **Charge history** | Per-tenant charge list. | `charge_list.php`, `find_charges.php` |

**Business rules to preserve:**
- Charge is keyed by tenant + apartment + month (legacy checks `charge_date LIKE 'YYYY-MM%'`).
- Charge generation runs as a **side effect of loading the tenant list** in legacy — new system should implement this explicitly (scheduled job or explicit “generate charges” action) rather than as a hidden page include.

### 2.4 Rent payments & balances

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Record payment** | Amount, invoice/voucher number, discount, building, date, recording user. | `manage_payment.php`, `save_payment()`, `invoices.php` |
| **Edit / delete payment** | Update payment; hard delete payment. | `save_payment()`, `delete_payment()` |
| **Payment list** | All payments for active tenants with building/apartment context. | `invoices.php` |
| **Pay balance shortcut** | UI to record payment against tenant with outstanding balance. | `pay_balance.php`, `tenantout.php` |
| **Tenant balance (list & statements)** | `outstanding = SUM(charge.total) − (SUM(payment.amount) + SUM(payment.discount))` | `tenants.php`, `tenantout.php`, `get_tdetails()` |

**Business rules to preserve:**
- Discounts reduce balance like payments (summed from `payments.discount`).
- Balance is **charge-driven**, not computed from months × rent (the months calculation in `tenants.php` is computed but unused for the displayed balance).

**Legacy bugs — not reproduced:**
- Rent payment delete is hard delete with no audit trail — new system should use status/void pattern (see §5).

### 2.5 Tenant water billing — **canonical model decision**

#### Legacy state (audited)

| Artifact | Role | Activity |
|----------|------|----------|
| **`water_bill`** | Primary per-tenant metered bill | ~1,196 rows; used by `waterbill.php`, `manage_waterbill.php`, `water_history.php`, `home.php`, `save_waterbill()`, income reports |
| **`water_bill_new`** | Secondary table with `status` column | ~115 rows; used only by `WaterPaymentUpdate()` and `admin/bulkpayment.php` (admin-only, deferred) |
| **`water_bill_charge`** | Referenced in PHP | Table **not in schema dump**; `monthly_bill.php` + `save_water_bill_charge()` appear **broken/incomplete** |

#### Decision for greenfield system

Use **one** per-tenant water bill entity modeled on **`water_bill`** fields, plus an explicit **`status`** field (values at minimum: `pending`, `paid` — from `water_bill_new` usage):

| Field (conceptual) | Legacy column | Notes |
|--------------------|---------------|-------|
| Tenant | `tenant_id` | Required |
| Building | `houseid` | Legacy stores rent building id |
| Billing period | `month_id`, `year_id` | String month name + year (legacy uses names like “January”, not ISO) |
| Meter previous / current | `pr`, `cr` | Integer readings |
| Consumption | `consumption` | Derived: current − previous |
| Rate | `rate` | Per-unit rate |
| Charged fee | `charged_fee` | Fee component |
| Bill amount | `amount` | Total bill amount |
| Remark | `remark` | |
| Recorded by | `username` | Actor |
| Status | from `water_bill_new.status` | `pending` / `paid` |
| Created at | `date_created` | |

**Business rules to preserve:**
- **One bill per tenant per month per year** — reject duplicate (`save_waterbill()` returns error if exists).
- **Consumption formula:** `consumption = cr − pr`; bill uses rate and fee fields (legacy `save_waterbill()` swaps `charged_fee` and `amount` assignments — **bug not reproduced**; store semantically correct mapping).
- Marking paid updates amount paid and status (legacy `WaterPaymentUpdate` on `water_bill_new` — fold into single table).
- Water bill list/history filterable by building and period (`water_history.php`, `waterdetails.php`).

**Explicitly exclude from v1 unless you request:**
- `water_bill_charge` / `monthly_bill.php` meter-charge workflow (orphaned table reference).
- Admin bulk payment screen over `water_bill_new`.

### 2.6 Building-level utility bills (rent)

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Nairobi / Kenya Water** | Building-level water utility expense per month/year (not per-tenant). | `kenyawater.php`, `kenya_water` table, `save_kenya_water()` |
| **Electricity** | Building-level electricity bill by month/year. | `electricity.php`, `electricity` table, `save_electricity()` |
| **Duplicate period guard** | Optional check for duplicate Kenya Water entry (commented out in `rent`; active in `sales` fork). | `check_kenya_record()`, `save_kenya_water()` |

**Business rules to preserve:**
- Kenya Water links to rent building (`house_id` → `categories.id`).
- Electricity links to rent building (`houseid`).
- Both appear as expense lines on the **rent income statement** for a building/month (§2.9).

### 2.7 Rent expenses & payroll

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Building expenses** | Expense name, amount, description, date, linked to rent building. | `expense.php`, `expenses` table, `save_expense()` |
| **Expense reports** | Filter by building / period; history popups. | `exp_report.php`, `expense_history.php` |
| **Employees** | Name, address, salary, phone, position, building, status (`Current`). | `employee.php`, `save_employee()` |
| **Payroll** | Per employee per period: salary, month, year, building, date, recording user. | `payroll.php`, `save_payroll()` |
| **Payroll delete** | Hard delete payroll row. | `delete_payroll()` |

**Business rules to preserve:**
- Payroll stores `month_id` and `year_id` as separate fields (legacy strings/ints).
- Payroll and expenses both feed building income statement deductions.

### 2.8 Shareholders & shareholder billing

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Shareholders** | CRUD: name, phone, address. | `shareholders.php`, `save_shareholder()` |
| **Shareholder bills** | Bill shareholder for amount, date, building, remark. | `shareholderpayment.php`, `shareholders_bill`, `save_shareholder_bill()` |
| **Shareholder bill history** | Filter by building and period. | `shareholder_history.php` |

**Business rules to preserve:**
- Shareholder bills deduct from rent net income on income statement:  
  `rent_net = rent_payments − service_charges − shareholder_bills` (see §2.9).

**Nav note:** `rent/navbar.php` lists shareholders but **does not** link shareholder payment or income statement — those exist in `admin/` nav. For rebuild, include shareholder billing and income reporting in Rental scope if shareholders are in scope (recommended yes, per your brief).

### 2.9 Rent reporting & exports

| Report | Description | Key legacy evidence |
|--------|-------------|---------------------|
| **Tenant balance / payment reports** | Payment report by building/period. | `payment_report.php`, `rentreports.php` |
| **Balance report** | Tenants with outstanding balances. | `balance_report.php`, `apt_balance.php` |
| **Rent payment history** | By month/building. | `rent_history.php` |
| **Income statement (per building, per month)** | Composite P&L (see formula below). | `income.php` + `find_income.php` |
| **Charge reports** | Tenant charge summaries. | `chargereport.php` |
| **Print / popup detail windows** | Legacy uses print-friendly popups for histories. | `water_history.php`, `expense_history.php`, etc. |
| **Export** | Legacy `download.php` exists; behavior varies by page. | New system: explicit CSV/PDF export requirement |

**Income statement formula (legacy `find_income.php` — preserve intent):**

For a given rent building and calendar month:

1. **Rent collections** = sum of `payments.amount` in month for building.
2. **Service income** = sum of `tenants.service_amount` for each payment’s tenant (legacy adds service per payment row).
3. **Shareholder deductions** = sum of `shareholders_bill.amount` in month for building.
4. **Rent net** = rent collections − service income − shareholder bills.
5. **Water income** = sum of `water_bill.amount` for building/month.
6. **Service + water subtotal** = service income + water income.
7. **Expenses** = sum of `expenses.amountpaid` + `payroll.salary` + `electricity.amount` + `kenya_water.amount` for building/month.
8. **Net balance in hand** = (service + water − expenses) + rent net.

**Open question:** Service income calculation “per payment row” is unusual — confirm with business whether service should be counted once per tenant per month instead.

### 2.10 Rent capabilities in legacy code but **not** in `rent/` navigation

These PHP pages exist under `rent/` but are **not linked from `rent/navbar.php`**. Confirm whether they belong in v1:

| Page / feature | Tables | Recommendation |
|----------------|--------|----------------|
| Income statement | composite | **Include** (core reporting) |
| Shareholder payment mgmt | `shareholders_bill` | **Include** |
| Other income / other expense | `otherincome`, `otherexpense` | **Ask** — generic ledger not in rent nav |
| `watercharge.php` | `water_bill` | Appears duplicate/alternate water UI — **ask** |

---

## 3. Sales management capabilities

Legacy module: `sales/`. Use `rent/admin_class.php` for shared method behavior where `MODULE_DIFF.md` shows `sales/admin_class.php` diverges (sales fork has weaker delete guards and different client-delete semantics).

### 3.1 Buildings & sale units

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Sale buildings** | CRUD sale property groupings (legacy `buildings`; UI “Buildings for sale”). | `salesbuilding.php`, `save_building()` |
| **Sale apartments / units** | CRUD units: house number, floor, description, price, status. | `saleapartment.php`, `save_forsale_apt()` |
| **Unit status** | `Available` on create; `Sold` when client assigned; back to `Available` on client disable/delete. | `save_forsale_apt()`, `save_client()`, `delete_client()` |
| **Vacant / sold / with-balance views** | Dashboard and filtered lists. | `home.php`, `salesvacant.php`, `saleswithbalance.php`, `occupiedsales.php` |

### 3.2 Clients (sales buyers)

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Client registration** | Link client to sale building + apartment; mark unit sold. | `manage_client.php`, `save_client()` |
| **Client profile** | Name, phone, gender, email, passport/ID, next of kin, registration date, deposit. | `manage_client.php` |
| **Agreed sale price** | Stored in legacy `clients.PassImage` (UI label “Price Tag”). | `Clients.php`, `manage_client.php` |
| **Voucher reference** | Stored in legacy `clients.service_amount` (UI mislabeled “Voucher No”). | `manage_client.php` |
| **Photo & signature upload** | Client photo and signature file upload on create/edit. | `manage_client.php` (`photo`, `sign` blobs) |
| **Client update** | Apartment change frees old unit and marks new unit sold. | `save_client()` |
| **Client disable / soft delete** | Set `status = 'Disable'`, free apartment (rent module pattern in shared `delete_client()`). | `rent/admin_class.php` `delete_client()` |
| **Client statement** | Payment history per client. | `client_statement.php`, `view_client.php` |
| **Cancelled clients archive** | Legacy moves rows to `clients_del` (sales fork); views in `cancelledrecords.php`. | `sales/admin_class.php` `delete_client()` fork |

**Business rules to preserve (use rent-canonical delete, not sales fork):**
- **Do not delete client with existing sales payments** — return error (rent/all/construction pattern).
- On disable: set client inactive, mark apartment `Available`.
- **Sales balance on client list:**  
  `outstanding = sale_price − (sum(payments.amount) + sum(payments.discount) + deposit)`  
  where `sale_price` = legacy `PassImage` field.

**Legacy bugs — not reproduced:**
- Repurposed columns (`PassImage` for price, `service_amount` for voucher).
- Sales fork: archive to `clients_del` + hard delete without payment guard.

### 3.3 Sales payments — **canonical state model decision**

#### Legacy state

| Table | Purpose |
|-------|---------|
| `cpayments` | Active sales payments |
| `cpayments_del` | Archive copy on cancel/delete |

Cancel flow (`delete_cpayment()` in `rent/admin_class.php`): `INSERT INTO cpayments_del SELECT * FROM cpayments` then `DELETE FROM cpayments`.

#### Decision for greenfield system

**Single `sales_payments` table** with status enum, e.g.:

- `active` — counts toward balance
- `cancelled` — retained for audit, excluded from balance, visible in “cancelled payments” report
- (future) `refunded` if needed

**Business rules to preserve:**
- Payment fields: client, amount, invoice/reference, bank, remark, discount, building, date, recorded-by user.
- Cancelled payments remain viewable (`cancelledpayments.php`, `view_cancel.php`) with full history.
- Editing active payments allowed (legacy `save_cpayment()` update path).

**Do not reproduce:** shadow table copy (`cpayments_del`, `clients_del`).

### 3.4 Sales expenses

| Capability | Description | Key legacy evidence |
|------------|-------------|---------------------|
| **Sales building expenses** | Expense tied to sale building (`cexpenses` / `buildings.id`). | `cexpenses.php`, `save_cexpense()` |
| **Sales expense in income report** | Included in sales P&L reports. | `incomereport.php`, `find_income_report.php` |

### 3.5 Sales reporting

| Report | Description | Key legacy evidence |
|--------|-------------|---------------------|
| **Sales balance report** | Clients/units with outstanding sale balances. | `salesreport.php` |
| **Sales payments report** | Payments by building/apartment/date range. | `salesreport.php`, `salespayments.php` |
| **Sales income / expense report** | Building P&L for sales. | `incomereport.php` |
| **Cancelled clients** | Clients in cancelled/disabled/archive state. | `cancelledrecords.php` |
| **Cancelled payments** | Payments with cancelled status. | `cancelledpayments.php` |
| **Total clients / aggregates** | Dashboard metrics. | `totalclients.php`, `home.php` |

**Sales balance formula (preserve intent):**
- `paid_total = sum(active payments.amount) + deposit`
- `balance = agreed_sale_price − paid_total − sum(discounts)`

---

## 4. Cross-cutting requirements

### 4.1 Authentication & session

- Single login entry point; route to rent or sales home based on role.
- Password hashing with modern algorithm (bcrypt/argon2).
- Rate limiting on login.
- CSRF protection on state-changing requests.

### 4.2 Authorization

- Role-based policies per resource (buildings, tenants, payments, etc.).
- No ad hoc `if ($role == ...)` scattered in controllers.

### 4.3 Auditing & actor attribution

Legacy records `username` / `login_name` on many financial rows (payments, water bills, payroll, Kenya water). New system should store **created_by / updated_by** user id on all financial transactions.

### 4.4 Reporting & exports

- On-screen reports matching §2.9 and §3.5 capabilities at minimum.
- Printable views for statements and income reports.
- Export to CSV (minimum); PDF optional later.
- Reports filterable by building, date range, tenant/client.

### 4.5 File uploads

| Upload | Legacy | New system |
|--------|--------|------------|
| Client photo | `clients.photo` (blob) | File storage with metadata reference |
| Client signature | `clients.sign` (blob) | Same |
| Tenant ID image | `tenants.PassImage` (blob) — conflated with status in forms | Separate `id_document` storage + explicit status field |

Storage must be swappable (local disk dev, S3-compatible prod).

### 4.6 Money handling

- Store money as `decimal(12,2)` or integer minor units — **never float**.
- Currency: legacy uses unlabeled numeric amounts (KES implied for Nairobi Water references). **Confirm currency** — assume single-currency KES unless you say otherwise.

### 4.7 Data integrity (greenfield improvements over legacy)

- Foreign keys on all relationships (legacy had none).
- Consistent `id` primary keys.
- No string-concatenated SQL.
- Void/cancel via status, not shadow delete tables.

### 4.8 Legacy bugs & anti-patterns — do not reproduce

| Issue | Evidence |
|-------|----------|
| Plaintext passwords | `users.password`, `login()` |
| SQL injection surface | Widespread `$_GET`/`$_POST` concatenation |
| No CSRF | All legacy forms |
| `save_waterbill` swapped fee/amount | `rent/admin_class.php` `save_waterbill()` |
| Tenant status in `PassImage` column | `manage_tenant.php` |
| Sales price in `PassImage` column | `manage_client.php` |
| Charge generation as page side-effect | `tenants.php` includes `charge.php` |
| Unvalidated `index.php?page=` include | All modules |
| `water_bill_charge` table missing | PHP references non-existent table |
| `alumnus_bio` references in `signup()` | Dead template code |

---

## 5. Explicitly deferred capabilities

Listed so nothing is silently lost if scope expands:

| Area | Legacy location | Notes |
|------|-----------------|-------|
| Construction / materials | `construction/`, `materials`, `company`, `build_site` | Full module deferred |
| Admin combined portal | `admin/` | Superset of rent+sales+construction+accounts |
| All-role combined portal | `all/` | Rent+sales in one nav |
| User administration | `users.php`, `manage_user.php` | Create/edit system users |
| Accounts / deposits / withdrawals | `accounts.php`, `deposits`, `withdraw`, `create_source` | Separate ledger (“Amaano”) |
| Site branding settings | `system_settings`, `site_settings.php` | App name, email, cover image |
| Bulk water payment | `admin/bulkpayment.php` | Uses `water_bill_new` |
| Alumni / signup flow | `signup()` in `admin_class.php` | Dead code |
| Generic other income/expense (orphan pages) | `otherincome`, `otherexpense` in `rent/` & `sales/` files but not nav | Confirm with business |
| Kenya duplicate-check behavior | Differs rent vs sales | New system: enforce one Kenya Water entry per building per month/year |

---

## 6. Legacy → requirement traceability (rent & sales tables)

For Step 2 `DATA_MODEL.md` mapping. Admin/construction tables omitted (deferred).

| Legacy table | Rent | Sales | New conceptual entity |
|--------------|:----:|:-----:|----------------------|
| `categories` | ✓ buildings | | `rental_buildings` |
| `houses` | ✓ units | | `rental_units` |
| `tenants` | ✓ | | `tenants` |
| `charge` | ✓ | | `rent_charges` |
| `payments` | ✓ | | `rent_payments` |
| `water_bill` (+ status) | ✓ | | `tenant_water_bills` |
| `water_bill_new` | merge → | | *(merged into above)* |
| `electricity` | ✓ | | `building_electricity_bills` |
| `kenya_water` | ✓ | | `building_water_utility_bills` |
| `expenses` | ✓ | | `rental_expenses` |
| `employee` | ✓ | | `employees` |
| `payroll` | ✓ | | `payroll_entries` |
| `shareholders` | ✓ | | `shareholders` |
| `shareholders_bill` | ✓ | | `shareholder_bills` |
| `moved_out` | ✓ | | `tenant_move_outs` |
| `buildings` | | ✓ sale buildings | `sale_buildings` |
| `forsale_apt` | | ✓ units | `sale_units` |
| `clients` | | ✓ | `clients` |
| `cpayments` (+ status) | | ✓ | `sales_payments` |
| `cpayments_del` | | merge → | *(status on `sales_payments`)* |
| `clients_del` | | merge → | *(status on `clients`)* |
| `cexpenses` | | ✓ | `sales_expenses` |
| `users` | shared | shared | `users` |
| `system_settings` | deferred | deferred | `settings` (later) |

**Orphan / non-schema legacy references (migration TBD in Step 5):** `water_bill_charge`, `clients_del`, `alumnus_bio`.

---

## 7. Open questions for your review

Please confirm or correct before Step 1 (architecture):

1. **Project directory name** — `rent-sales-platform/` or another name?
2. **Roles in v1** — Rental + Sales only, or include a minimal Admin (user management, both modules)?
3. **Shareholder module + income statement** — in Rental v1? (Recommended: yes, per brief.)
4. **Other income / other expense** — include in v1 or defer?
5. **Tenant hard-delete** — keep or move-out only?
6. **Service income on rent income statement** — per payment row (legacy) or per tenant per month?
7. **Currency** — KES single-currency?
8. **Water billing period labels** — keep month names (“January”) or switch to ISO dates?
9. **Cross-module read access** — any shared visibility between Rental and Sales staff?
10. **Income statement** — rent nav omitted it but code exists; confirm it’s required for Rental staff in v1.

---

## 8. Step 0 completion checklist

| Deliverable | Status |
|-------------|--------|
| `REQUIREMENTS.md` (this file) | ✓ Drafted |
| New project directory | ✗ Not created (per instructions) |
| Framework install | ✗ Not started |
| Code | ✗ None |

**Next step after your approval:** Step 1 — `ARCHITECTURE.md` (technology stack proposal).  
**Not started:** Step 2 data model, Step 5 legacy data migration.

---

*Derived from legacy behavior observation only. Legacy codebase at `/home/ali/legacy-app` was not modified.*
