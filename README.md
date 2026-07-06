# Rent & Sales Management Platform

Greenfield rebuild of the legacy property-management system (rent + sales modules).

## Status

| Step | Deliverable | Status |
|------|-------------|--------|
| 0 | `REQUIREMENTS.md` | Done |
| 1 | `ARCHITECTURE.md` | Done |
| 2 | `DATA_MODEL.md` + migrations | Done |
| 3 | Auth + Vue shell | Done |
| 4 | Rent core slice | Done |
| 5 | Rent + sales financials | Done |
| 5 | Legacy data migration | **Not done** — tooling ready ([LEGACY_IMPORT.md](./LEGACY_IMPORT.md)) |
| — | Admin ops (users, audit, recycle bin) | Done |
| — | Auth (password reset, settings) | Done |
| — | Production deploy V4 | See [DEPLOYMENT-V4.md](./DEPLOYMENT-V4.md) |

## Stack

- **Backend:** Laravel 12, PHP 8.3+, PostgreSQL, Sanctum
- **Frontend:** Vue 3 + Vite + Tailwind
- **Legacy reference:** `/home/ali/legacy-app` (read-only)

## Quick start

```bash
# PostgreSQL (docker — uses host port 5433 if 5432 is taken)
docker compose up -d postgres

# Backend
cd backend
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan serve

# Frontend
cd ../frontend
npm install
npm run dev
```

### Demo logins (from seeder)

| Username | Password | Role |
|----------|----------|------|
| `admin` | `password` | Admin → users, activity log, recycle bin |
| `rental` | `password` | Rental staff → `/rental` |
| `sales` | `password` | Sales staff → `/sales` |

### Verify foundation

- [ ] Open http://localhost:5173 — login page loads
- [ ] Login as `rental` — lands on Rental dashboard; cannot open `/sales`
- [ ] Logout, login as `sales` — lands on Sales dashboard; cannot open `/rental`
- [ ] Login as `admin` — can open admin pages and settings SMTP panel
- [ ] `php artisan test` — all tests pass (requires PostgreSQL on port 5433)

## Project layout

```
rent-sales-platform/
├── REQUIREMENTS.md
├── ARCHITECTURE.md
├── DATA_MODEL.md
├── DEPLOYMENT-V4.md
├── LEGACY_IMPORT.md
├── docker-compose.yml
├── backend/          # Laravel API
└── frontend/         # Vue SPA
```

## Documentation

| Doc | Purpose |
|-----|---------|
| [REQUIREMENTS.md](./REQUIREMENTS.md) | Business requirements (legacy traceability) |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Stack and API design |
| [DATA_MODEL.md](./DATA_MODEL.md) | Schema and legacy mapping |
| [DEPLOYMENT-V4.md](./DEPLOYMENT-V4.md) | Production deploy (latest) |
| [LEGACY_IMPORT.md](./LEGACY_IMPORT.md) | Import legacy MySQL data |

## Remaining work (high level)

| Priority | Item |
|----------|------|
| High | Production deploy V4 + SMTP + cron |
| High | Legacy data import (full SQL dump) |
| Medium | Tenant/client document uploads (photos, signatures) |
| Medium | Payment receipts (printable per payment) |
| Low | Arrears aging report, sales installment schedules |
| Low | CI pipeline (GitHub Actions), frontend route lazy-loading |
