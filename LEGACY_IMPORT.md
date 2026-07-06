# Legacy data import — production (rasulmart.com)

Import a **MySQL `.sql` dump** from the old PHP app into the greenfield PostgreSQL database.

The importer reads `INSERT` statements directly from the dump file. It does **not** need MySQL on the server.

---

## Important: export must include data

The file `rasulmar_db.sql` in this repo is mostly **empty** (only users + 1 unit). A structure-only export cannot import tenants, charges, or payments.

Before importing, inspect your dump:

```bash
php artisan legacy:inspect /path/to/rasulmar_db.sql
```

You should see row counts for at least:

| Legacy table | Greenfield entity |
|--------------|-------------------|
| `categories` | Rental buildings |
| `houses` | Rental units |
| `tenants` | Tenants |
| `charge` | Rent charges |
| `payments` | Rent payments |
| `water_bill` | Tenant water bills |
| `electricity` | Building electricity |
| `kenya_water` | Nairobi water utilities |
| `employee`, `payroll`, `expenses` | Staff & expenses |
| `shareholders`, `shareholders_bill` | Shareholders |
| `buildings`, `forsale_apt`, `clients`, `cpayments` | Sales module |

### phpMyAdmin export (legacy server)

1. Open database `rasulmar_db`
2. **Export** → **Custom**
3. Select **all tables**
4. Format: **SQL**
5. Ensure **Data** / INSERT statements are included
6. Save as `rasulmar_db.sql`

---

## Production import steps

### 1. Backup live greenfield database first

In cPanel → PostgreSQL → backup, or:

```bash
pg_dump -h localhost -U rasulmar_alisax rasulmar_rent_sales > backup_before_legacy_$(date +%F).sql
```

### 2. Upload SQL dump to server

```bash
mkdir -p ~/public_html/rent-sales/storage/legacy
# Upload rasulmar_db.sql via SFTP to:
# ~/public_html/rent-sales/storage/legacy/rasulmar_db.sql
chmod 600 ~/public_html/rent-sales/storage/legacy/rasulmar_db.sql
```

### 3. Inspect dump on server

```bash
cd ~/public_html/rent-sales
php artisan legacy:inspect storage/legacy/rasulmar_db.sql
```

### 4. Dry-run (no database writes)

```bash
php artisan legacy:import storage/legacy/rasulmar_db.sql --dry-run
```

Review the summary and warnings.

### 5. Import rental data (recommended first pass)

Clears demo rental/sales domain data, keeps users unless you choose otherwise:

```bash
php artisan legacy:import storage/legacy/rasulmar_db.sql \
  --fresh \
  --force \
  --skip-sales \
  --skip-users
```

| Flag | Meaning |
|------|---------|
| `--fresh` | Truncate domain tables (tenants, charges, payments, etc.) |
| `--force` | Skip confirmation prompt |
| `--skip-sales` | Rental module only |
| `--skip-users` | Keep current greenfield logins; map legacy `created_by` by username. If no usernames match (e.g. dump has only `BILE`), attribution falls back to the first manager account. |

To **import legacy users and passwords** (staff log in with old passwords):

```bash
php artisan legacy:import storage/legacy/rasulmar_db.sql --fresh --force --skip-sales
```

Legacy passwords are plain text in the dump; the importer hashes them for Laravel.

### 6. Import sales data (optional second pass)

Only after rental import looks correct:

```bash
php artisan legacy:import storage/legacy/rasulmar_db.sql --fresh --force --skip-users
```

> `--fresh` clears rental data too. For sales-only add-on without wiping rental, run a full import once without `--skip-sales` instead of two passes.

**Recommended single full import:**

```bash
php artisan legacy:import storage/legacy/rasulmar_db.sql --fresh --force --skip-users
```

### 7. Verify in the app

- [ ] Buildings and units match legacy
- [ ] Tenant list and balances look correct
- [ ] Sample tenant payment history matches legacy
- [ ] Water bills and charges present for metered tenants
- [ ] Staff can log in (legacy users or greenfield users)

### 8. Automated validation (recommended)

After import, compare the database against the same SQL dump:

```bash
php artisan legacy:validate storage/legacy/rasulmar_db.sql
```

Optional flags:

| Flag | Purpose |
|------|---------|
| `--samples=5` | Spot-check 5 tenants/clients with the most activity (default) |
| `--samples=0` | Check every tenant and client in the dump |
| `--tenant=10` | Validate one legacy tenant `ClientID` |
| `--client=39` | Validate one legacy client `ClientID` |
| `--tolerance=0.01` | Allowed monetary rounding difference |

The command checks:

1. **Entity counts** — buildings, units, tenants, payments, clients (partial OK for orphaned charge/water rows)
2. **Tenant financial totals** — legacy `charge` + `water_bill` amounts vs imported `rent_charges`; payment amounts vs `rent_payments`
3. **Client financial totals** — agreed price, deposit, and `cpayments` vs imported sales data

**Local full-data test dump** (used in CI/tests):

```bash
php artisan legacy:inspect /home/ali/legacy-app/rasulmar_karama.sql
php artisan legacy:import /home/ali/legacy-app/rasulmar_karama.sql --fresh --force --skip-users
php artisan legacy:validate /home/ali/legacy-app/rasulmar_karama.sql
```

**Production dump check:** `rasulmar_db.sql` in the legacy repo is structure-only (no tenant/charge rows). Re-export from phpMyAdmin with INSERT data before production import.

---

## Legacy → greenfield mapping

| Legacy | Greenfield |
|--------|------------|
| `categories` | `rental_buildings` |
| `houses` | `rental_units` |
| `tenants` | `tenants` |
| `charge` (Rent + service / Water) | `rent_charges` |
| `payments` | `rent_payments` |
| `water_bill` | `tenant_water_bills` + water `rent_charges` |
| `moved_out` | `tenant_move_outs` |
| `kenya_water` | `building_water_utility_bills` (aggregated per building/period) |
| `electricity` | `building_electricity_bills` |
| `users` | `users` (roles: Admin, Sales/Marketing, Rental) |
| `buildings` | `sale_buildings` |
| `forsale_apt` | `sale_units` |
| `clients` | `clients` (sales) |
| `cpayments` | `sales_payments` |

Financial integrity rules apply after import: one rent/water/electricity charge per tenant per month (duplicates in legacy dump are skipped with warnings).

---

## User roles from legacy

| Legacy `type` | Greenfield role |
|---------------|-----------------|
| Admin, All, `1` | `admin` |
| Sales, Marketing | `sales` |
| Rental, Staff, `2` | `rental` |

---

## Troubleshooting

| Issue | Action |
|-------|--------|
| `No INSERT statements found` | Re-export with data |
| `Legacy rental data already imported` | Add `--fresh` or truncate manually |
| Many skipped charges | Orphan rows in legacy dump (missing tenant/unit) — review warnings |
| Wrong balances | Compare one tenant in legacy vs greenfield payment summary |
| Import timeout | Run via SSH, not browser; split is not supported — use full dump |

---

## Local testing

```bash
cd backend
php artisan legacy:inspect /home/ali/legacy-app/rasulmar_karama.sql
php artisan legacy:import /home/ali/legacy-app/rasulmar_karama.sql --dry-run --skip-sales
```

`rasulmar_karama.sql` is a **full** sample dump (142 tenants, 1283 charges) used in automated tests.
