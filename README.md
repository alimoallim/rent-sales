# Rent & Sales Management Platform

Greenfield rebuild of the legacy property-management system (rent + sales modules).

## Status

| Step | Deliverable | Status |
|------|-------------|--------|
| 0 | `REQUIREMENTS.md` | Done |
| 1 | `ARCHITECTURE.md` | Done |
| 2 | `DATA_MODEL.md` + migrations | Done — migrated on port 5433 |
| 3 | Auth + Vue shell (foundation) | Done |
| 4 | Rent core slice | Done |
| 5 | Rent financials | Not started |
| 5 | Legacy data migration | Not started |

## Stack

- **Backend:** Laravel 12, PHP 8.3+, PostgreSQL, Sanctum (Step 3)
- **Frontend:** Vue 3 + Vite + Tailwind (Step 3)
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
| `rental` | `password` | Rental staff → `/rental` |
| `sales` | `password` | Sales staff → `/sales` |

### Verify foundation (Step 3)

- [ ] Open http://localhost:5173 — login page loads
- [ ] Login as `rental` — lands on Rental dashboard; cannot open `/sales`
- [ ] Logout, login as `sales` — lands on Sales dashboard; cannot open `/rental`
- [ ] `php artisan test --filter=AuthenticationTest` — role/password tests pass

## Project layout

```
rent-sales-platform/
├── REQUIREMENTS.md
├── ARCHITECTURE.md
├── DATA_MODEL.md
├── docker-compose.yml
├── backend/          # Laravel API
└── frontend/         # Vue SPA (Step 3)
```

## Documentation

- Business requirements: `REQUIREMENTS.md`
- Stack and API design: `ARCHITECTURE.md`
- Schema and legacy mapping: `DATA_MODEL.md`
