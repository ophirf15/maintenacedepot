# Depot Borrow Platform

Shared equipment borrowing system for property management companies. Properties borrow tools from one or more maintenance depots — catalog, cart-style requests, approval, QR checkout/return, defect tickets, maintenance schedules, and CapEx replacement planning.

## Stack

- **Backend:** Laravel 13 (PHP 8.3+), MySQL (SQLite works for local demo)
- **Frontend:** Vue 3 SPA + Vite + Tailwind CSS 4 (PWA-ready)
- **Auth:** Password, magic link, SAML 2.0 settings (ACS endpoint ready)
- **Roles:** Spatie permissions — IT Admin, Depot Admin, Depot Maintenance, Property Manager, Borrower
- **Updates:** WordPress-style GitHub Releases updater (IT Admin)

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
# SQLite is fine for demo; set DB_* for MySQL on client hosts
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Open http://127.0.0.1:8000

### Demo logins (password: `password`)

| Email | Role |
|-------|------|
| admin@depotborrow.test | IT Admin |
| mike@depotborrow.test | Depot Admin |
| joe@depotborrow.test | Borrower (Pinewood) |

## Client install (shared PHP/nginx + MySQL)

1. Upload a **release zip** (includes `vendor/` + built `public/build`) — no Composer/Node required on the host.
2. Point the web root at `/public`.
3. Create a MySQL database; copy `.env.example` → `.env` and set `APP_URL`, `DB_*`.
4. Visit `/install` and create the first IT admin (optionally seed demo data).
5. In **IT Admin → Updates**, set `DEPOT_GITHUB_REPO` / GitHub settings to enable one-click updates.

## Phases shipped

1. **Catalog / request / approval** — categories, items, manuals, cart requests, partial approve + borrower accept, reserve, waitlist, audit, installer, branding/SMTP/Twilio/SAML settings, backups, updater MVP
2. **QR / handoff** — QR tokens + PNG/ZIP labels, camera scan, offline outbox sync, checkout checklists, self-return + admin review, fuel gauge, extensions
3. **Maintenance / CapEx** — maintenance types/plans/work orders, unified tickets, lifespan + EOL flags, Excel/PDF CapEx exports

## Useful artisan

```bash
php artisan depot:mark-overdue   # optional: flag overdue loans (if scheduled)
php artisan migrate
php artisan db:seed --class=DemoDataSeeder
```

## Environment highlights

```
DEPOT_VERSION=1.0.0
DEPOT_GITHUB_REPO=your-org/depot-borrow
```

## License

Proprietary — for licensed client installs.
