# Version 3 — Production deployment

Deploy to **https://rasulmart.com/app** (upgrade from V2 or fresh install).

| Item | Value |
|------|-------|
| **Target URL** | https://rasulmart.com/app |
| **Health check** | https://rasulmart.com/app/up |
| **API base** | https://rasulmart.com/app/api/v1 |
| **Package script** | `./scripts/package-for-rasulmart.sh app` |
| **Prior guide** | [DEPLOYMENT-V2.md](./DEPLOYMENT-V2.md) |

---

## What is new in V3

| Area | Change |
|------|--------|
| **Bulk meter readings** | New page: `/rental/bulk-meter-readings` — enter water/electricity readings for all metered tenants in one session |
| **Bulk API** | `GET/POST /api/v1/rental/bulk-meter-readings` |
| **Searchable dropdowns** | Building/tenant/client selects render above panels (teleport fix — no clipped menus) |
| **Sales print** | Client statement print works reliably (iframe-based, no blank page) |
| **Sales dashboard** | Hero pipeline cards readable in light mode (fixed white-on-white) |
| **Route cache** | Rental/sales routes use prefixed names (`rental.*`, `sales.*`) — `php artisan route:cache` works |

**No new database migrations** in V3 — safe to deploy without schema changes.

---

## Part A — Build package (dev machine)

```bash
cd /home/ali/rent-sales-platform
chmod +x scripts/package-for-rasulmart.sh
./scripts/package-for-rasulmart.sh app
```

Optional archive:

```bash
cd deploy/output
tar -czf rent-sales-v3-$(date +%Y%m%d).tar.gz public_html/
```

Output: `deploy/output/public_html/rent-sales/`

---

## Part B — Upgrade from V2 (recommended)

### 1. Backup

```bash
# Database
pg_dump -h localhost -U rasulmar_alisax rasulmar_rent_sales \
  > ~/backup_before_v3_$(date +%F).sql

# Files
cp -a ~/public_html/rent-sales ~/public_html/rent-sales.backup.v2.$(date +%F)
```

### 2. Upload backend files

Merge into `~/public_html/rent-sales/`:

| Path | Required for V3 |
|------|-----------------|
| `routes/api.php` | Yes — bulk routes + route name prefixes |
| `app/Http/Controllers/Api/V1/Rental/BulkMeterReadingController.php` | Yes |
| `app/Services/Rental/BulkMeterReadingService.php` | Yes |
| `app/Http/Requests/Rental/BulkMeterReadingGridRequest.php` | Yes |
| `app/Http/Requests/Rental/StoreBulkMeterReadingsRequest.php` | Yes |
| `app/Http/Controllers/...` (other changed controllers) | If unsure, upload full `app/` |
| `vendor/` | Upload full folder OR run `composer install --no-dev` on server |

**Keep existing `.env`** — do not overwrite.

### 3. Upload frontend (public/)

Because `public_html/app` → symlink → `rent-sales/public/`:

Upload into `~/public_html/rent-sales/public/`:

- `index.html`
- `assets/` (entire folder — replace old hashed JS/CSS)
- `.htaccess` (from package if unchanged)

### 4. Server commands (critical order)

```bash
cd ~/public_html/rent-sales

php artisan route:clear
php artisan config:clear
php artisan view:clear

php artisan migrate --force          # no-op if already up to date
php artisan route:cache
php artisan config:cache
php artisan view:cache
```

### 5. Verify routes

```bash
php artisan route:list --path=bulk-meter
```

Expected:

```
GET|HEAD   api/v1/rental/bulk-meter-readings
POST       api/v1/rental/bulk-meter-readings
```

If missing → `routes/api.php` not uploaded or route cache not cleared.

---

## Part C — Full upload (simplest)

Upload entire `deploy/output/public_html/rent-sales/` over existing install, **except** `.env`.

Then run Part B step 4 server commands.

---

## Part D — Post-deploy verification

### Infrastructure

| # | Check | Expected |
|---|-------|----------|
| 1 | https://rasulmart.com/app | Login loads |
| 2 | https://rasulmart.com/app/up | `200` |
| 3 | Hard refresh (Ctrl+Shift+R) | New JS/CSS loads |

### V3 features

| # | Check | Expected |
|---|-------|----------|
| 4 | Rental → **Bulk readings** in sidebar | Page loads |
| 5 | Bulk readings → building dropdown | List visible above card (not clipped) |
| 6 | Load tenants → enter readings → Save | Readings saved |
| 7 | Sales → client → Print statement | Print preview shows data |
| 8 | Sales dashboard | Pipeline cards readable (not white on white) |
| 9 | Any searchable dropdown (tenants, units) | Menu not hidden behind panels |

### API smoke test

Logged in as rental user, DevTools → Network:

```
GET /app/api/v1/rental/bulk-meter-readings?utility=water&building_id=1&billing_month=6&billing_year=2026
```

- **200** with JSON `data.rows` = OK  
- **404** = backend not deployed or route cache stale  
- **401** = route exists, need session  

---

## Part E — Troubleshooting

| Symptom | Fix |
|---------|-----|
| **404** on `bulk-meter-readings` | Upload `routes/api.php` + new PHP files; `php artisan route:clear && php artisan route:cache` |
| **Route cache error** on deploy | Ensure V3 `api.php` (has `->name('rental.')` on groups) |
| Building dropdown invisible / clipped | Upload latest frontend (`SearchableSelect.vue` teleport fix) |
| Old UI after upload | Hard refresh; confirm new `assets/index-*.js` on server |
| Blank print (sales client) | Upload latest frontend assets |
| **500** after upload | Check `storage/logs/laravel.log`; `chmod -R ug+rwx storage bootstrap/cache` |

---

## Part F — Rollback to V2

```bash
cp -a ~/public_html/rent-sales.backup.v2.* ~/public_html/rent-sales
cd ~/public_html/rent-sales
php artisan route:clear
php artisan config:cache
```

---

## Quick reference

```bash
# Local
./scripts/package-for-rasulmart.sh app

# Server (V2 → V3 upgrade)
cd ~/public_html/rent-sales
php artisan route:clear
php artisan migrate --force
php artisan route:cache
php artisan config:cache
php artisan view:cache
php artisan route:list --path=bulk-meter
```

---

## Related docs

| Doc | Purpose |
|-----|---------|
| [DEPLOYMENT.md](./DEPLOYMENT.md) | General hosting reference |
| [DEPLOYMENT-V2.md](./DEPLOYMENT-V2.md) | V2 live test guide |
| [DEPLOYMENT-V4.md](./DEPLOYMENT-V4.md) | V4 — auth, admin, soft deletes, migrations |
| [LEGACY_IMPORT.md](./LEGACY_IMPORT.md) | Legacy SQL import |
| [deploy/rasulmart/SYMLINK-DEPLOY.md](./deploy/rasulmart/SYMLINK-DEPLOY.md) | Symlink layout |
