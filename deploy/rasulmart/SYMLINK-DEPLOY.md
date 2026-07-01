# Deploy with symlink: app → rent-sales/public

Your server uses:

```
public_html/app  →  symlink  →  rent-sales/public/
```

So **`app` and `rent-sales/public` are the same folder.** Upload everything into `rent-sales/public/` only.

URL: **https://rasulmart.com/app**

---

## Required files in `rent-sales/public/`

```bash
ls -la ~/public_html/rent-sales/public/
```

You must see:

| File / folder | Purpose |
|---------------|---------|
| `index.php` | Laravel entry (already there) |
| `.htaccess` | Must have `RewriteBase /app/` |
| `index.html` | Vue SPA shell |
| `assets/` | JS + CSS from frontend build |

If `index.html` is missing → **503** on `/app/` while `/app/up` still works.

---

## Fix now (on server)

### 1. Check what is in the folder

```bash
ls -la ~/public_html/rent-sales/public/
ls -la ~/public_html/app/    # same folder via symlink
```

### 2. Fix `.htaccess`

Edit `~/public_html/rent-sales/public/.htaccess` — ensure this line exists after `RewriteEngine On`:

```apache
RewriteBase /app/
```

Or copy from the project: `deploy/rasulmart/symlink-app.htaccess`

### 3. Add the frontend build

On your computer:

```bash
cd /home/ali/rent-sales-platform
./scripts/package-for-rasulmart.sh app
```

Upload **only** from the package:

- `deploy/output/public_html/rent-sales/public/index.html`
- `deploy/output/public_html/rent-sales/public/assets/` (entire folder)

Into server: `~/public_html/rent-sales/public/`

(Because of the symlink, uploading to `~/public_html/app/` is the same place.)

### 4. Upload updated Laravel files (if not done yet)

- `rent-sales/routes/web.php`
- `rent-sales/app/Support/Spa.php`

Then:

```bash
cd ~/public_html/rent-sales
php artisan route:clear
php artisan config:cache
```

### 5. Confirm `.env`

```env
APP_URL=https://rasulmart.com/app
SESSION_PATH=/app
SESSION_DOMAIN=rasulmart.com
SANCTUM_STATEFUL_DOMAINS=rasulmart.com,www.rasulmart.com
```

---

## Verify

```bash
ls -la ~/public_html/rent-sales/public/index.html
ls -la ~/public_html/rent-sales/public/assets/
```

Browser:

1. https://rasulmart.com/app/ → login page with styles
2. https://rasulmart.com/app/up → Application up
3. DevTools → Network → `/app/assets/*.js` → 200

---

## Do not

- Do **not** replace `index.php` with the custom `deploy/rasulmart/public_html-app/index.php` — the symlink already uses Laravel’s normal `index.php`.
- Do **not** use `RewriteBase /rent-sales/public/` — your URL is `/app`, not `/rent-sales/public/`.
- Do **not** build frontend with `/rent-sales/public/` base path.

---

## Optional: recreate symlink

If the symlink is ever broken:

```bash
cd ~/public_html
rm -f app
ln -s /home2/rasulmar/public_html/rent-sales/public app
ls -la app
```

---

## Login error: "Session store not set on request."

Laravel API login needs PHP sessions, but Sanctum skipped starting them (domain / Referer mismatch on shared hosting).

**Upload these updated files:**

- `bootstrap/app.php`
- `app/Http/Middleware/EnsureSpaSession.php`
- `config/sanctum.php`

**Confirm `.env`:**

```env
SANCTUM_STATEFUL_DOMAINS=rasulmart.com,www.rasulmart.com
SESSION_DRIVER=database
SESSION_PATH=/app
SESSION_DOMAIN=rasulmart.com
SESSION_SECURE_COOKIE=true
APP_URL=https://rasulmart.com/app
```

**On server:**

```bash
cd ~/public_html/rent-sales
php artisan migrate --force
php artisan config:clear
php artisan config:cache
```

Then try login again.

