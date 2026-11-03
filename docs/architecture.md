# Backend architecture

This guide explains how a request travels through Giftopia and where to find
the code responsible for each part. The backend is a Laravel JSON API; the
Next.js frontend is a separate application.

## Request flow

Most API requests follow this path:

1. **Nginx** accepts the HTTP request and forwards PHP requests to the Laravel
   application container.
2. **Routes** in `backend/routes/api.php` select a controller and attach
   access rules such as rate limits, authentication, and roles.
3. **Middleware** checks tokens and roles. Customer and staff routes are
   separated before controller actions run.
4. **Form Requests** validate and normalize incoming fields.
5. **Controllers** translate the HTTP request into a business operation and
   return a response.
6. **Services** apply business rules, coordinate models, and use database
   transactions when a workflow must succeed or fail together.
7. **Models** read and write records through Eloquent.
8. **Resources and `ApiResponse`** shape data for the caller. API responses
   normally use `success`, `message`, `data`, and `errors`.

For example, `POST /api/studio/quote` is rate-limited and accepts optional
authentication. Laravel validates the fields, builds a `StudioConfiguration`,
checks compatibility, and only then asks `StudioQuoteService` to save a quote.

## Key folders

| Folder | Purpose |
|---|---|
| `backend/routes/` | API, browser, and console routes. |
| `backend/app/Http/Controllers/` | HTTP entry points for Studio, assets, auth, orders, payments, proofs, refunds, and staff operations. |
| `backend/app/Http/Requests/` | Input validation and request normalization. |
| `backend/app/Http/Middleware/` | Optional Sanctum authentication and role checks. |
| `backend/app/Http/Resources/` | Public response representations, including customer order details. |
| `backend/app/Services/` | Business workflows and rules, grouped by domain where useful. |
| `backend/app/Models/` | Database-backed domain records and relationships. |
| `backend/app/Enums/` | Supported roles and order, payment, production, proof, and refund states. |
| `backend/app/Policies/` | Ownership and staff authorization for orders and production items. |
| `backend/app/Support/` | Shared API response formatting and catalog definitions. |
| `backend/app/Mail/` | Customer and staff notifications. |
| `backend/database/migrations/` | Database schema history. |
| `backend/database/seeders/` | Starter catalog and local user accounts. |
| `backend/config/` | Laravel and business configuration, including Studio delivery and timing settings. |
| `backend/tests/` | Feature and unit tests for API behavior and business rules. |

Laravel registers API routes, middleware aliases, and JSON exception handling
in `backend/bootstrap/app.php`. `AppServiceProvider` binds the lead-time and
payment gateway interfaces and registers authorization policies.

## How the main modules connect

### Studio, catalog, and quote

`StudioController` exposes the customer-facing catalog, validation, and quote
endpoints. `StudioConfiguration` converts selected IDs into catalog models
and a normalized design. `CompatibilityValidator` checks required and
forbidden combinations. `PriceCalculator` totals catalog prices in integer
piasters. `StudioQuoteService` saves the configuration, calculated prices,
hash, indicative ready date, and expiration.

The `OptionRule` model stores admin-managed rules between catalog choices.
The catalog endpoint exposes those rules and a catalog version so clients can
refresh stale drafts.

### Checkout, orders, and payments

`OrderController` delegates checkout to `CheckoutService`. Checkout verifies
the quote is current, owned or guest-usable, unused, and still compatible.
It recalculates the price before creating the order, then records shipping,
acknowledgments, a customization snapshot, and an idempotency key.

`PaymentService` creates deposit or balance attempts through the configured
`PaymentGateway`. A signed webhook updates the payment and order inside a
transaction. The fake gateway supports local development; payment redirects
alone never update the order.

### Production and proofs

Producer/admin routes use production sheets, scheduling, attention flags,
status transitions, checklists, and QC photos. `TransitionService` records
production events and enforces allowed movement between states.

Personalized orders receive a proof after the deposit is confirmed. Customers
may approve the current proof or request changes; staff can publish a revised
version. `ProofService` records versions and events, applies the auto-approval
deadline, and coordinates customer notifications.

### Refunds and assets

Customers upload refund evidence and submit claims under the order's
eligibility rules. Staff approve or reject claims. `RefundService` connects an
approved amount to the payment gateway and records failures rather than
pretending a failed refund succeeded.

`AssetUploadService` validates, processes, and stores inspiration images, QC
photos, and refund evidence. Files are served through short-lived signed URLs
with access checks; assets can be linked to customers, orders, and production
items.

### Admin catalog, calendar, and reporting

`CatalogRegistry` describes editable catalog types for `CatalogController`.
Admins manage options and compatibility rules; producers/admins may change
availability. `CalendarController` exposes schedule and blackout-date
operations. Reports are admin-only and summarize order, production, and
business outcomes.

## Identity and access

- Registration always creates a `customer`; clients cannot choose a role.
- Login returns a Sanctum personal access token. Send it as a bearer token for
  authenticated requests.
- Customer endpoints are limited to customer-role accounts and order
  ownership is checked by policies.
- Producer and admin accounts can access production and operational workflows.
- Admin-only routes manage reports and catalog definitions.
- Studio catalog, validation, guest quote, and inspiration-upload endpoints
  are public or use optional authentication as indicated in the routes.

## Background work and configuration

The Compose stack includes an application container, MySQL, Nginx, a queue
worker, and a scheduler. Queued mail and scheduled maintenance do not run as
part of an ordinary HTTP request.

Studio defaults and environment-backed settings live in
`backend/config/studio.php`. The payment gateway is selected through
`backend/config/services.php`. Use `.env` for local secrets and environment
specific values; never commit credentials.

Useful exploration commands:

```sh
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan model:show Order
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test
```
