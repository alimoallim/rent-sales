# Version 2 — Production live test deployment

Deploy the Rent & Sales platform to **https://rasulmart.com/app** for production-environment testing.

| Item | Value |
|------|-------|
| **Target URL** | https://rasulmart.com/app |
| **Health check** | https://rasulmart.com/app/up |
| **API base** | https://rasulmart.com/app/api/v1 |
| **Hosting** | Shared cPanel + Apache + PostgreSQL 13 |
| **Package script** | `./scripts/package-for-rasulmart.sh app` |

---

## What is in V2 (live test scope)

Use this deployment to validate:

- Rental module: tenants, units, charges, charge batches, payments, water/electricity, payroll, shareholders, expenses, reports
- Sales module: clients, units, payments, expenses, reports
- Admin: user management
- UX: dark mode, mobile top bar, searchable dropdowns (buildings, units, tenants, clients, etc.)
- Dual currency: rental KES / sales USD enforcement
- Charge batch workflow: water/electricity sync into batches; utility bill status `recorded` / `paid`

---

## Part A — Pre-flight (your dev machine)

Run these **before** uploading anything.

### 1. Requirements

| Tool | Version |
|------|---------|
| Node.js | 18+ (for frontend build) |
| npm | 9+ |
| PHP | 8.2+ |
| Composer | 2.x |

Server needs PHP 8.2+, PostgreSQL 13, extensions: `pdo_pgsql`, `pgsql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`.

### 2. Run tests (optional but recommended)

```bash
cd /home/ali/rent-sales-platform
docker compose up -d postgres    # if not already running
cd backend && php artisan test
```

All tests should pass before packaging.

### 3. Build the deploy package

```bash
cd /home/ali/rent-sales-platform
chmod +x scripts/package-for-rasulmart.sh
./scripts/package-for-rasulmart.sh app
```

This will:

1. Build the Vue SPA with `VITE_BASE_PATH=/app/` and `VITE_API_BASE_URL=/app`
2. Run `composer install --no-dev --optimize-autoloader`
3. Write output to `deploy/output/public_html/`

**Output layout:**

```
deploy/output/public_html/
└── rent-sales/                 # Full Laravel app
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/                 # SPA + Laravel entry
    │   ├── index.php
    │   ├── index.html
    │   ├── assets/
    │   └── .htaccess           # RewriteBase /app/
    ├── routes/
    ├── storage/
    ├── vendor/
    ├── .env.example.production
    └── .htaccess               # Deny direct web access
```

> **Do not upload** `.env` from your dev machine. Use `.env.example.production` on the server.

### 4. Create upload archive (optional)

```bash
cd deploy/output
tar -czf rent-sales-v2-$(date +%Y%m%d).tar.gz public_html/
```

Upload via cPanel File Manager or SFTP.

---

## Part B — Server layout

Your host may use one of two layouts. **Confirm which you have before uploading.**

### Layout 1 — Symlink (common on rasulmart)

```
public_html/app  →  symlink  →  rent-sales/public/
```

In this case, upload **only** into `~/public_html/rent-sales/` (the symlink makes `app/` see the same `public/` files).

See also: `deploy/rasulmart/SYMLINK-DEPLOY.md`

### Layout 2 — Separate `app/` folder

```
public_html/
├── app/          # Web root for /app URL (index.php, index.html, assets/)
└── rent-sales/   # Laravel app (not web-accessible)
```

See also: `deploy/rasulmart/FIX-403-AND-BLANK.md`

**Use only one public URL:** https://rasulmart.com/app — not `/rent-sales/public/`.

---

## Part C — Fresh install (empty database)

Use when this is the first V2 deploy or you want a clean test database.

### 1. Upload files

**Symlink layout:** merge `deploy/output/public_html/rent-sales/` into `~/public_html/rent-sales/`.

**Separate layout:** upload full `rent-sales/` **and** copy `rent-sales/public/*` into `public_html/app/`.

### 2. Permissions

```bash
chmod 755 ~/public_html/rent-sales/public
chmod -R ug+rwx ~/public_html/rent-sales/storage
chmod -R ug+rwx ~/public_html/rent-sales/bootstrap/cache
```

### 3. Create production `.env`

```bash
cd ~/public_html/rent-sales
cp .env.example.production .env
nano .env   # set DB password and other secrets
```

Required values:

```env
APP_NAME="Rasul Mart Rent & Sales"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rasulmart.com/app

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=rasulmar_rent_sales
DB_USERNAME=rasulmar_alisax
DB_PASSWORD=YOUR_DB_PASSWORD

SESSION_DRIVER=database
SESSION_PATH=/app
SESSION_DOMAIN=rasulmart.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=rasulmart.com,www.rasulmart.com
FRONTEND_URL=https://rasulmart.com

CACHE_STORE=database
QUEUE_CONNECTION=database
```

### 4. Initialize application

```bash
cd ~/public_html/rent-sales

php artisan key:generate
php artisan migrate --force
php artisan db:seed --force --class=Database\\Seeders\\DatabaseSeeder
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Change default passwords immediately

Seeded users (`admin`, `rental`, `sales`) default to password `password`. Change before sharing the live test URL.

### 6. Cron (charge batches)

cPanel → **Cron Jobs**:

```cron
* * * * * cd /home/rasulmar/public_html/rent-sales && php artisan schedule:run >> /dev/null 2>&1
```

(Adjust home path if your cPanel user path differs, e.g. `/home2/rasulmar/`.)

---

## Part D — Upgrade existing V1 install

Use when `rasulmar_rent_sales` already has data from an earlier deploy.

### 1. Backup database

cPanel → PostgreSQL → backup, or SSH:

```bash
pg_dump -h localhost -U rasulmar_alisax rasulmar_rent_sales \
  > ~/backup_v1_$(date +%F_%H%M).sql
```

### 2. Backup current files

```bash
cp -a ~/public_html/rent-sales ~/public_html/rent-sales.backup.$(date +%F)
```

### 3. Upload new code

Overwrite `app/`, `config/`, `database/`, `routes/`, `bootstrap/`, `public/` (keep `index.html` + `assets/`), and `vendor/`.

**Keep the existing `.env`** — do not overwrite with the example file.

### 4. Run migrations

```bash
cd ~/public_html/rent-sales
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### V2 migrations (will run automatically)

| Migration | Purpose |
|-----------|---------|
| `add_admin_role_to_users` | Admin role support |
| `add_is_manager_to_users` | Manager flag on rental users |
| `create_charge_batches_tables` | Monthly charge batch workflow |
| `add_metering_flags_to_tenants` | Per-tenant water/electricity metering |
| `link_tenant_water_bills_to_rent_charges` | Water bills linked to charges |
| `add_tenant_electricity_bills` | Tenant electricity bills |
| `enforce_rent_charge_financial_integrity` | Charge/payment integrity constraints |
| `add_currency_code_to_sales_tables` | Sales USD currency enforcement |
| `rename_utility_bill_pending_status_to_recorded` | Water/electricity status rename |

### 5. Do **not** re-run full seeder on production

`php artisan db:seed` would reset demo users. For upgrades, skip seeding unless you need demo buildings only:

```bash
php artisan db:seed --force --class=Database\\Seeders\\RentalDemoSeeder   # optional demo units
```

---

## Part E — Optional: import legacy MySQL data

To load real data from the old PHP app, follow `LEGACY_IMPORT.md`.

Summary:

```bash
# Upload rasulmar_db.sql to server
php artisan legacy:inspect ~/public_html/rent-sales/storage/legacy/rasulmar_db.sql
php artisan legacy:import ~/public_html/rent-sales/storage/legacy/rasulmar_db.sql --dry-run
php artisan legacy:import ~/public_html/rent-sales/storage/legacy/rasulmar_db.sql
```

**Backup first.** Run import before UAT if testers need real tenant/payment history.

---

## Part F — Live test verification checklist

Run through these on https://rasulmart.com/app after deploy.

### Infrastructure

| # | Check | Expected |
|---|-------|----------|
| 1 | https://rasulmart.com/app | Login page, styles load |
| 2 | https://rasulmart.com/app/up | `200` — application up |
| 3 | https://rasulmart.com/app/sanctum/csrf-cookie | `204` |
| 4 | DevTools → Network | JS/CSS from `/app/assets/*` (200, not 404) |
| 5 | DevTools → Network | API calls to `/app/api/v1/...` (not `/api/...`) |
| 6 | `storage/logs/laravel.log` | No permission or session errors |

### Authentication

| # | Check | Expected |
|---|-------|----------|
| 7 | Login as `rental` | Lands on rental dashboard |
| 8 | Try `/sales` URL | Blocked / redirected |
| 9 | Login as `sales` | Lands on sales dashboard |
| 10 | Login as `admin` | Can open admin users + both modules |
| 11 | Logout + login again | Session persists (no instant logout) |
| 12 | Hard refresh on `/app/rental/tenants` | SPA route works (no 404) |

### Rental module (smoke)

| # | Check |
|---|-------|
| 13 | Dashboard loads metrics |
| 14 | Tenants list + register tenant (searchable building/unit dropdowns) |
| 15 | Record rent payment |
| 16 | Water bill: create reading → appears in charge batch |
| 17 | Charge batch: generate → approve → balances update |
| 18 | Reports: income statement exports |

### Sales module (smoke)

| # | Check |
|---|-------|
| 19 | Clients list + register client |
| 20 | Record client payment (USD only) |
| 21 | Sales dashboard metrics |

### UX

| # | Check |
|---|-------|
| 22 | Dark mode toggle persists after reload |
| 23 | Mobile width: module switch + user menu usable |
| 24 | Large dropdowns: type-to-search works (unit, tenant, building) |

---

## Part G — Troubleshooting

| Symptom | Fix |
|---------|-----|
| **403** on `/app` | `index.php` missing in `public_html/app/`; see `deploy/rasulmart/FIX-403-AND-BLANK.md` |
| **Blank page** | `index.html` or `assets/` missing; re-run package script and re-upload `public/` |
| **503** on `/app` but `/app/up` works | Copy `index.html` into `rent-sales/public/` or set `SPA_INDEX_PATH` in `.env` |
| **Login then instant logout** | `SESSION_PATH=/app`, `SESSION_DOMAIN=rasulmart.com`, `SANCTUM_STATEFUL_DOMAINS` |
| **"Session store not set"** | Upload `EnsureSpaSession` middleware; see `deploy/rasulmart/SYMLINK-DEPLOY.md` |
| **500 on water bills** | Run `php artisan migrate --force` (utility status enum migration) |
| **API hits wrong path** | Rebuild with `frontend/.env.production` (`VITE_API_BASE_URL=/app`) |
| **500 on migrate** | Enable `pdo_pgsql`; verify DB credentials in cPanel |

---

## Part H — Rollback

If the live test fails critically:

```bash
# Restore files
rm -rf ~/public_html/rent-sales
mv ~/public_html/rent-sales.backup.YYYY-MM-DD ~/public_html/rent-sales

# Restore database (only if migrations caused data issues)
psql -h localhost -U rasulmar_alisax -d rasulmar_rent_sales < ~/backup_v1_YYYY-MM-DD.sql

cd ~/public_html/rent-sales
php artisan config:cache
```

---

## Quick command reference

```bash
# Local — build package
./scripts/package-for-rasulmart.sh app

# Server — first deploy
cd ~/public_html/rent-sales
cp .env.example.production .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache

# Server — V2 upgrade
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

---

## Related docs

| Doc | Purpose |
|-----|---------|
| `DEPLOYMENT.md` | General deployment reference |
| `deploy/rasulmart/SYMLINK-DEPLOY.md` | Symlink layout specifics |
| `deploy/rasulmart/FIX-403-AND-BLANK.md` | 403 / blank page fixes |
| `LEGACY_IMPORT.md` | Import old MySQL dump |
