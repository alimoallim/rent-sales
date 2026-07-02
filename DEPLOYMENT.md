# Deployment guide — rasulmart.com/app

First production deploy for the Rent & Sales platform on **shared hosting** with **PostgreSQL 13**.

**Target URL:** https://rasulmart.com/app

> **Version 2 live test:** see **[DEPLOYMENT-V2.md](./DEPLOYMENT-V2.md)** for the full pre-flight checklist, upgrade path, and UAT verification steps.

---

## 1. Server requirements

Confirm with your host (cPanel → **Select PHP Version** / **PHP Extensions**):

| Requirement | Minimum |
|-------------|---------|
| PHP | **8.2+** (Laravel 12) |
| PostgreSQL | **13** (installed) |
| Composer | 2.x (SSH or local vendor upload) |
| Apache | `mod_rewrite` enabled |

**Required PHP extensions:** `pdo_pgsql`, `pgsql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`

**Node.js** is only needed on your **local machine** to build the Vue frontend. It does not need to run on shared hosting.

---

## 2. Recommended folder layout (cPanel)

Keep Laravel **outside** the web root; expose only `public/` at `/app`.

```
/home/rasulmar/
├── rent-sales/                    # Laravel app (NOT web-accessible)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/                    # contents copied/symlinked below
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   └── .env                       # production secrets (never commit)
└── public_html/
    └── app/                       # document URL: https://rasulmart.com/app
        ├── index.php              # from Laravel public/
        ├── .htaccess
        ├── index.html             # Vue SPA (from frontend build)
        └── assets/                # Vue hashed JS/CSS
```

### Option A — Symlink (preferred if SSH allowed)

```bash
cd ~/public_html
mkdir -p app
# Point web-visible app folder at Laravel public
ln -sfn ~/rent-sales/public/* ~/public_html/app/
```

Edit `~/public_html/app/index.php` paths if Laravel lives elsewhere:

```php
require __DIR__.'/../../rent-sales/vendor/autoload.php';
$app = require_once __DIR__.'/../../rent-sales/bootstrap/app.php';
```

### Option B — Copy `public/` only

Upload the full `backend/` tree to `~/rent-sales/`, then copy `public/*` into `public_html/app/` and fix `index.php` paths as above.

---

## 3. Database (PostgreSQL)

Create the database in cPanel → **PostgreSQL Databases** (if not already created).

| Setting | Value |
|---------|-------|
| Database | `rasulmar_rent_sales` |
| User | `rasulmar_alisax` |
| Password | *(use the password you set in cPanel)* |
| Host | Usually `localhost` |
| Port | Usually `5432` |

> **Security:** Rotate the database password after first deploy if it was shared in chat or email. Never commit `.env` to git.

---

## 4. Production `.env`

On the server, create `~/rent-sales/.env`:

```env
APP_NAME="Rasul Mart Rent & Sales"
APP_ENV=production
APP_KEY=                          # generate in step 6
APP_DEBUG=false
APP_URL=https://rasulmart.com/app

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=rasulmar_rent_sales
DB_USERNAME=rasulmar_alisax
DB_PASSWORD=YOUR_DB_PASSWORD_HERE

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/app
SESSION_DOMAIN=rasulmart.com
SESSION_SECURE_COOKIE=true

SANCTUM_STATEFUL_DOMAINS=rasulmart.com,www.rasulmart.com

FRONTEND_URL=https://rasulmart.com

CACHE_STORE=database
QUEUE_CONNECTION=database

BCRYPT_ROUNDS=12
```

**Important subdirectory settings**

- `APP_URL` must include `/app`
- `SESSION_PATH=/app` so auth cookies work under `/app`
- `SANCTUM_STATEFUL_DOMAINS` = your domain (no path)

---

## 5. Build locally (on your dev machine)

From the project root:

```bash
chmod +x scripts/build-for-production.sh
./scripts/build-for-production.sh
```

This:

1. Builds the Vue app with `VITE_BASE_PATH=/app/` (see `frontend/.env.production`)
2. Copies `dist/*` into `backend/public/` (SPA + assets)

### Install PHP dependencies (exclude dev packages)

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

---

## 6. Upload to server

Upload via SFTP / File Manager:

1. Entire `backend/` → `~/rent-sales/` (include `vendor/` if Composer is not available on host)
2. Ensure `public_html/app/` serves Laravel `public/` (symlink or copy from step 2)

**Do not upload:** `node_modules/`, `frontend/`, `.git/`, `tests/`, local `.env`

---

## 7. Server setup (SSH or cPanel Terminal)

```bash
cd ~/rent-sales

# Application key (run once)
php artisan key:generate

# Writable directories
chmod -R ug+rwx storage bootstrap/cache

# Database schema
php artisan migrate --force

# First admin user only (then change passwords immediately)
php artisan db:seed --force --class=Database\\Seeders\\DatabaseSeeder

# Caches for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### After seeding — change default passwords

Default seeded users (`admin`, `rental`, `sales`) use password `password`. **Change these before going live** via the admin Users screen or:

```bash
php artisan tinker
>>> \App\Models\User::where('username','admin')->update(['password' => bcrypt('YOUR_STRONG_PASSWORD')]);
```

---

## 8. Apache `.htaccess` for subdirectory

In `public_html/app/.htaccess`, add `RewriteBase` **after** `RewriteEngine On`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /app/

    # ... rest of Laravel default .htaccess ...
</IfModule>
```

If the host uses a subdomain document root instead of a subfolder, skip `RewriteBase`.

---

## 9. Cron (monthly charge batch)

Laravel schedules `rental:generate-charge-batches` on the 1st of each month.

cPanel → **Cron Jobs** → add:

```cron
5 0 1 * * cd /home/rasulmar/rent-sales && php artisan schedule:run >> /dev/null 2>&1
```

Or every minute (standard Laravel scheduler pattern):

```cron
* * * * * cd /home/rasulmar/rent-sales && php artisan schedule:run >> /dev/null 2>&1
```

---

## 10. HTTPS

Ensure **SSL** is active for `rasulmart.com` (Let's Encrypt in cPanel). Session cookies require HTTPS when `SESSION_SECURE_COOKIE=true`.

If behind a proxy/CDN, you may need `TrustProxies` middleware configured (usually default on shared hosting is fine).

---

## 11. Post-deploy verification

| Check | Expected |
|-------|----------|
| https://rasulmart.com/app | Login page loads, styles OK |
| https://rasulmart.com/app/api/v1/auth/me | JSON `401` when logged out |
| https://rasulmart.com/app/sanctum/csrf-cookie | `204` response |
| Login as admin | Redirect to rental or admin module |
| Browser devtools → Network | API calls go to `/app/api/v1/...` |
| `storage/logs/laravel.log` | No permission errors |

### Common issues

| Symptom | Fix |
|---------|-----|
| 404 on `/app/rental` refresh | `RewriteBase /app/` in `.htaccess`; SPA `index.html` in `public/` |
| Login succeeds then logs out | `SESSION_PATH=/app`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` |
| API calls hit `/api/...` (root) | Rebuild frontend with `frontend/.env.production` |
| 500 on migrate | Check `pdo_pgsql` extension and DB credentials |
| Blank page | Check `public/assets/` uploaded; browser console for 404 JS |
| `storage` not writable | `chmod -R ug+rwx storage bootstrap/cache` |

---

## 12. Updates (subsequent releases)

On your machine:

```bash
git pull
./scripts/build-for-production.sh
cd backend && composer install --no-dev --optimize-autoloader
```

Upload changed files, then on server:

```bash
cd ~/rent-sales
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

---

## 13. Security checklist (production)

- [ ] `APP_DEBUG=false`
- [ ] Strong passwords for all users (not `password`)
- [ ] `.env` not web-accessible (outside `public_html`)
- [ ] Database user has access only to `rasulmar_rent_sales`
- [ ] HTTPS enforced
- [ ] Remove or protect demo seed data if not needed

---

## Quick reference — URLs

| Resource | URL |
|----------|-----|
| Application | https://rasulmart.com/app |
| Login | https://rasulmart.com/app/login |
| API base | https://rasulmart.com/app/api/v1 |
| Health | https://rasulmart.com/app/up |
