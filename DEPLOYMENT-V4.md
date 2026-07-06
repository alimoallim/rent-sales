# Version 4 — Production deployment

Deploy to **https://rasulmart.com/app** (upgrade from V3).

| Item | Value |
|------|-------|
| **Target URL** | https://rasulmart.com/app |
| **Health check** | https://rasulmart.com/app/up |
| **API base** | https://rasulmart.com/app/api/v1 |
| **Package script** | `./scripts/package-for-rasulmart.sh app` |
| **Prior guide** | [DEPLOYMENT-V3.md](./DEPLOYMENT-V3.md) |

---

## What is new in V4

| Area | Change |
|------|--------|
| **Password reset** | Forgot-password flow with email OTP (`/forgot-password`, `/reset-password`) |
| **Settings** | Profile, password change, admin SMTP test (`/settings`) |
| **Admin module** | User management, activity log, recycle bin |
| **Soft deletes** | Buildings, units, expenses, employees, payroll, shareholders, users — restore via recycle bin |
| **Activity log** | Audit trail for create/update/delete/restore |
| **Charge batch email** | Scheduler runs `rental:generate-charge-batches --notify` on the 1st of each month |
| **UI polish** | Collapsible sidebar, compact icon row actions, password policy |
| **Sales CSV export** | Balance and income statement reports export to CSV |
| **Duplicate prevention** | Unique building names (rental + sales) and unit numbers per building |

### New database migrations (required)

| Migration | Purpose |
|-----------|---------|
| `2026_07_05_100000_create_password_reset_codes_table` | Email OTP storage for password reset |
| `2026_07_05_110000_add_soft_deletes_to_domain_tables` | `deleted_at` on 11 domain tables |
| `2026_07_05_120000_create_activity_logs_table` | Admin activity log |

Run `php artisan migrate --force` on production after upload.

---

## Part A — Build package (dev machine)

```bash
cd /home/ali/rent-sales-platform

# Run tests (requires PostgreSQL on port 5433)
cd backend && php artisan test && cd ..

# Build frontend
cd frontend && npm ci && npm run build && cd ..

# Package for server
chmod +x scripts/package-for-rasulmart.sh
./scripts/package-for-rasulmart.sh app
```

Optional archive:

```bash
cd deploy/output
tar -czf rent-sales-v4-$(date +%Y%m%d).tar.gz public_html/
```

Output: `deploy/output/public_html/rent-sales/`

---

## Part B — Environment variables (production `.env`)

Add or confirm these on the server (do **not** overwrite the whole `.env` — merge):

```dotenv
# App
APP_URL=https://rasulmart.com/app
FRONTEND_URL=https://rasulmart.com
SESSION_PATH=/app
SESSION_DOMAIN=rasulmart.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=rasulmart.com,www.rasulmart.com

# Mail (required for password reset + charge batch alerts)
MAIL_MAILER=smtp
MAIL_SCHEME=smtps          # or null for port 587 STARTTLS
MAIL_HOST=mail.rasulmart.com
MAIL_PORT=465
MAIL_USERNAME=info@rasulmart.com
MAIL_PASSWORD="your-mailbox-password"
MAIL_FROM_ADDRESS=info@rasulmart.com
MAIL_FROM_NAME="${APP_NAME}"

# Password reset
PASSWORD_RESET_CODE_TTL=15

# Operational alerts (comma-separated)
ADMIN_NOTIFICATION_EMAILS=admin@rasulmart.com,info@rasulmart.com
```

After changing `.env`:

```bash
php artisan config:clear
php artisan config:cache
```

Test mail from **Settings** (admin user) or:

```bash
php artisan tinker
# Mail::raw('test', fn ($m) => $m->to('you@example.com')->subject('SMTP test'));
```

---

## Part C — Upgrade from V3

### 1. Backup

```bash
pg_dump -h localhost -U rasulmar_alisax rasulmar_rent_sales \
  > ~/backup_before_v4_$(date +%F).sql

cp -a ~/public_html/rent-sales ~/public_html/rent-sales.backup.v3.$(date +%F)
```

### 2. Upload

Upload `deploy/output/public_html/rent-sales/` over the existing install, **except** `.env`.

At minimum, ensure these paths are updated:

| Path | Required |
|------|----------|
| `app/` | Full folder (new controllers, models, mail, rules) |
| `routes/api.php` | Auth, admin, soft-delete routes |
| `bootstrap/app.php` | Scheduler with `--notify` |
| `config/notifications.php` | Admin email list |
| `database/migrations/2026_07_05_*` | Three new migrations |
| `resources/views/mail/` | Password reset + test email templates |
| `public/index.html` + `public/assets/` | New frontend build |
| `vendor/` | Full folder OR `composer install --no-dev` on server |

### 3. Server commands (critical order)

```bash
cd ~/public_html/rent-sales

php artisan route:clear
php artisan config:clear
php artisan view:clear

php artisan migrate --force
php artisan db:seed --force    # only if fresh install; skip on upgrade with live data

php artisan route:cache
php artisan config:cache
php artisan view:cache
```

Verify migrations:

```bash
php artisan migrate:status | grep 2026_07_05
```

Expected: all three `2026_07_05_*` migrations **Ran**.

---

## Part D — Cron (scheduler)

V4 uses `--notify` so admins receive email when draft charge batches are generated.

cPanel → **Cron Jobs** → add (every minute — standard Laravel pattern):

```cron
* * * * * cd ~/public_html/rent-sales && php artisan schedule:run >> /dev/null 2>&1
```

Or monthly-only (not recommended — misses other scheduled tasks):

```cron
5 0 1 * * cd ~/public_html/rent-sales && php artisan schedule:run >> /dev/null 2>&1
```

Confirm scheduled command:

```bash
php artisan schedule:list
```

Expected entry: `rental:generate-charge-batches --notify` on the 1st of each month.

Manual test (dry run on server):

```bash
php artisan rental:generate-charge-batches --notify
```

---

## Part E — Post-deploy verification

### Infrastructure

| # | Check | Expected |
|---|-------|----------|
| 1 | https://rasulmart.com/app | Login page loads |
| 2 | https://rasulmart.com/app/up | `200` |
| 3 | Hard refresh (Ctrl+Shift+R) | New JS/CSS loads |

### V4 features

| # | Check | Expected |
|---|-------|----------|
| 4 | `/forgot-password` | Email step loads |
| 5 | `/settings` (logged in) | Profile + password forms |
| 6 | Admin → **Users** | User list loads |
| 7 | Admin → **Activity log** | Audit entries visible after an edit |
| 8 | Admin → **Recycle bin** | Soft-deleted items restorable |
| 9 | Rental sidebar | Collapse toggle works on desktop |
| 10 | Sales → Reports → **Export CSV** | CSV downloads |

### API smoke tests

```bash
# Health
curl -s -o /dev/null -w "%{http_code}" https://rasulmart.com/app/up
# Expected: 200

# Migrations (on server)
php artisan migrate:status
```

---

## Part F — Legacy data import (optional, after V4)

If migrating real data from the old MySQL app, follow [LEGACY_IMPORT.md](./LEGACY_IMPORT.md).

**Prerequisite:** Full SQL dump with INSERT data (not structure-only).

```bash
php artisan legacy:inspect storage/legacy/rasulmar_db.sql
php artisan legacy:import storage/legacy/rasulmar_db.sql --dry-run
php artisan legacy:import storage/legacy/rasulmar_db.sql
```

Run import **after** V4 migrations are applied.

---

## Part G — Troubleshooting

| Symptom | Fix |
|---------|-----|
| Password reset email not sent | Check `MAIL_*` in `.env`; `php artisan config:clear`; test from Settings |
| **500** after migrate | Check `storage/logs/laravel.log`; `chmod -R ug+rwx storage bootstrap/cache` |
| Activity log / recycle bin 404 | Upload full `app/` + `routes/api.php`; `php artisan route:clear && php artisan route:cache` |
| Soft delete restore fails | Confirm `2026_07_05_110000` migration ran |
| Charge batch email not received | Set `ADMIN_NOTIFICATION_EMAILS`; confirm cron runs `schedule:run` |
| Old UI after upload | Hard refresh; confirm new `assets/index-*.js` on server |
| **403** on admin pages | Login as `admin` user (role `admin`) |

---

## Part H — Rollback to V3

```bash
cp -a ~/public_html/rent-sales.backup.v3.* ~/public_html/rent-sales
cd ~/public_html/rent-sales
php artisan route:clear
php artisan config:cache
```

**Note:** Rolling back code without restoring the database may leave V4 migrations applied. Restore the DB backup if you need a full rollback.

---

## Quick reference

```bash
# Local
cd frontend && npm run build && cd ..
./scripts/package-for-rasulmart.sh app

# Server (V3 → V4 upgrade)
cd ~/public_html/rent-sales
php artisan migrate --force
php artisan config:clear && php artisan config:cache
php artisan route:clear && php artisan route:cache
php artisan schedule:list
```

---

## Related docs

| Doc | Purpose |
|-----|---------|
| [README.md](./README.md) | Project status and quick start |
| [DEPLOYMENT-V3.md](./DEPLOYMENT-V3.md) | Prior release guide |
| [LEGACY_IMPORT.md](./LEGACY_IMPORT.md) | Legacy SQL import |
| [deploy/rasulmart/SYMLINK-DEPLOY.md](./deploy/rasulmart/SYMLINK-DEPLOY.md) | Symlink layout |
