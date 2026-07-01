# Fix: 403 on /app and blank page on /rent-sales/public

## What is wrong

| URL | Problem |
|-----|---------|
| `https://rasulmart.com/app` → **403** | Folder `public_html/app/` is empty or has no `index.php` |
| `https://rasulmart.com/rent-sales/public/` → **blank** | Frontend was built for `/app/` but you open `/rent-sales/public/` — JS/CSS paths do not match |

**Use only one URL.** The app is configured for **`https://rasulmart.com/app`**.

---

## Correct folder layout on server

```
public_html/
├── app/                          ← https://rasulmart.com/app
│   ├── index.php                 ← special file (points to ../rent-sales)
│   ├── .htaccess                 ← RewriteBase /app/
│   ├── index.html                ← Vue SPA
│   └── assets/                   ← JS + CSS
└── rent-sales/                   ← Laravel (blocked from direct access)
    ├── .htaccess                 ← Deny all
    ├── app/
    ├── bootstrap/
    ├── .env
    ├── vendor/
    └── storage/
```

Do **not** use `https://rasulmart.com/rent-sales/public/` after fixing `/app`.

---

## Fix in 5 steps (cPanel File Manager or FTP)

### Step 1 — Build package on your computer

```bash
cd /home/ali/rent-sales-platform
./scripts/package-for-rasulmart.sh app
```

This creates `deploy/output/public_html/` ready to upload.

### Step 2 — Upload

Upload **everything inside** `deploy/output/public_html/` into your hosting `public_html/`:

- `public_html/app/` → all files (index.php, .htaccess, index.html, assets/)
- `public_html/rent-sales/` → full Laravel app

If `rent-sales` already exists, merge and overwrite `app/` files.

### Step 3 — Permissions (File Manager or SSH)

| Path | Permission |
|------|------------|
| `public_html/app/` | **755** |
| `public_html/app/index.php` | **644** |
| `public_html/rent-sales/storage/` | **775** (recursive) |
| `public_html/rent-sales/bootstrap/cache/` | **775** (recursive) |

### Step 4 — `.env` on server

File: `public_html/rent-sales/.env`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rasulmart.com/app

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=rasulmar_rent_sales
DB_USERNAME=rasulmar_alisax
DB_PASSWORD=your_password

SESSION_PATH=/app
SESSION_DOMAIN=rasulmart.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=rasulmart.com,www.rasulmart.com
```

Then in **Terminal** (cPanel → Terminal):

```bash
cd ~/public_html/rent-sales
php artisan key:generate
php artisan migrate --force
php artisan config:cache
```

### Step 5 — Test

1. https://rasulmart.com/app → login page with styling
2. https://rasulmart.com/app/up → plain `200` health response
3. Browser F12 → Network → assets load from `/app/assets/...` (not 404)

---

## If `/app/` shows 503 but `/app/up` works

Laravel is running but cannot find `index.html`. The SPA lives in `public_html/app/` while Laravel looks in `rent-sales/public/` by default.

**Quick fix on server (SSH / Terminal):**

```bash
cp ~/public_html/app/index.html ~/public_html/rent-sales/public/
```

Or add to `rent-sales/.env` (use your real home path from cPanel):

```env
SPA_INDEX_PATH=/home/rasulmar/public_html/app/index.html
php artisan config:cache
```

Then reload https://rasulmart.com/app/

---

## If /app still shows 403

1. Confirm `public_html/app/index.php` **exists** (not only an empty folder).
2. Confirm `.htaccess` in `app/` contains `RewriteBase /app/`.
3. In cPanel → **Indexes** → set directory indexing to **No Indexing** (not "Forbidden" without index).
4. Check **Error Log** in cPanel for the exact Apache message.

---

## Quick check: is index.html missing?

In File Manager, open `public_html/app/`. You must see:

- `index.php`
- `index.html`
- `assets/` folder with `.js` and `.css` files

If `index.html` is missing → re-run `./scripts/package-for-rasulmart.sh app` and re-upload `app/`.
