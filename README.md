# Giftopia

Giftopia is a made-to-order resin keepsake shop. Customers design a piece in
the Studio, receive a server-priced quote, place an order, and follow its
production and delivery. Staff manage the catalog, production schedule,
personalization proofs, quality-control photos, and refund claims.

This repository contains two applications:

- **Backend:** Laravel 11 JSON API in [`backend/`](./backend/).
- **Frontend:** Next.js customer and staff web app in [`frontend/`](./frontend/).

The backend is the source of truth for catalog choices, compatibility,
prices, quotes, order state, and payment status. The browser sends selected
IDs and text; it does not decide the final price or confirm a payment.

## Architecture at a glance

```text
Browser (Next.js)
      │ JSON API requests
      ▼
Nginx → Laravel API → MySQL
                   ├─ queue worker: email and background work
                   └─ scheduler: expiring orders and scheduled workflows
```

Laravel routes requests through middleware and request validation, then
controllers coordinate business services. Eloquent models store catalog,
quote, order, payment, production, proof, and refund records. API responses
use a consistent JSON envelope. More detail is in
[`docs/architecture.md`](./docs/architecture.md).

## Main business areas

| Area | What it means |
|---|---|
| Studio | Builds a design from a base item, mold, mood, technique, colors, inserts, and optional personalization. |
| Quotes and checkout | Validates the design, calculates its price, creates a time-limited quote, then turns an accepted quote into an order. |
| Payments | Creates deposit and balance payment attempts. Signed provider webhooks—not browser redirects—update payment state. |
| Production | Gives staff production sheets, scheduling, status transitions, checklists, and QC photo uploads. |
| Proofs | Lets a customer approve or request changes to a personalization preview before production. |
| Refunds | Lets eligible customers submit a claim; authorized staff review it and record a decision. |
| Admin | Maintains catalog options and compatibility rules, blackout dates, reports, production, and refund decisions. |

Read [`docs/studio.md`](./docs/studio.md) for the customer design flow and
example API requests. The existing
[`docs/api.md`](./docs/api.md) and
[`docs/frontend-contract.md`](./docs/frontend-contract.md) contain the broader
endpoint and frontend contracts.

## Technologies

- PHP 8.2+ and Laravel 11
- Laravel Sanctum personal access tokens and role middleware
- MySQL 8 for persistent data
- Eloquent ORM and Laravel migrations/seeders
- Docker Compose for the complete local stack: Laravel, MySQL, Nginx,
  queue worker, scheduler, and Next.js
- Next.js, React, TypeScript, and Tailwind CSS
- PHPUnit for backend tests; Vitest for frontend tests

## Run locally with Docker

1. Copy the backend environment template and configure local application values:

   ```sh
   cp backend/.env.example backend/.env
   ```

   Set `APP_KEY`, allowed frontend origins, and any payment/mail settings
   required for your environment. Compose reads database credentials and port
   overrides from a repository-root `.env` file; the defaults are for
   disposable local development only. Set `DB_PASSWORD` there before using
   non-disposable data, and do not commit secrets.

2. Start the complete frontend and backend stack from the repository root:

   ```sh
   docker compose up -d
   ```

   The frontend is included by default. Its browser API URL defaults to
   `http://localhost:8080/api`; its Docker-internal API URL defaults to
   `http://nginx/api`. Override these with `NEXT_PUBLIC_API_URL` or
   `API_INTERNAL_URL` in the root `.env` if needed. `APP_PORT` and `MYSQL_PORT`
   can similarly override the default host ports.

3. Generate an application key if this is a fresh setup, then migrate and seed:

   ```sh
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   ```

   `DatabaseSeeder` calls both `CatalogSeeder` and `UserSeeder`. The seeded
   accounts and password are configured by the seeder and
   `SEED_USER_PASSWORD`; check those files and your environment before using
   them. Use only disposable local credentials.

4. The frontend is available at `http://localhost:3000`; the API is available
   at `http://localhost:8080/api`, with its health endpoint at
   `http://localhost:8080/up`. The MySQL port is `3307` by default. Frontend
   scripts and dependencies are listed in
   [`frontend/package.json`](./frontend/package.json).

## Useful backend commands

Run these from the repository root:

```sh
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app php artisan migrate:status
```

To run one backend test file:

```sh
docker compose exec app php artisan test tests/Feature/ExampleTest.php
```

## Where to start reading

- [`docs/architecture.md`](./docs/architecture.md) — request flow, folders,
  and how backend modules fit together.
- [`docs/studio.md`](./docs/studio.md) — Studio design, validation, pricing,
  quotes, and example requests.
- [`backend/routes/api.php`](./backend/routes/api.php) — API endpoints and
  their access rules.
- [`backend/app/Services/`](./backend/app/Services/) — business workflows.
- [`backend/database/migrations/`](./backend/database/migrations/) — database
  structure and domain relationships.

## Local setup notes

- Delivery locations, quote lifetime, deposit percentage, claim window, and
  proof timing are configured in `backend/config/studio.php` and its
  environment variables.
- The fake payment gateway is suitable for local development. Do not treat
  browser return URLs as proof of payment; the backend accepts signed
  webhooks to record provider results.
- This root README is the main onboarding guide for the repository.
