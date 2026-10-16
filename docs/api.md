# Giftopia API

The API is served under `/api`. JSON responses use this envelope:

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "errors": {}
}
```

All monetary amounts are integer piasters (minor units); currency is `EGP`.
Send the Sanctum token as `Authorization: Bearer <token>` on authenticated
requests.

## Authentication

### `POST /api/register`

Creates a customer account. Any `role` included in the request is ignored.

```json
{
  "name": "Amina",
  "email": "amina@example.test",
  "password": "a-secure-password",
  "password_confirmation": "a-secure-password"
}
```

Returns `201` with `data.user` and a one-time `data.token`.

### `POST /api/login`

Accepts `email` and `password`; limited to five attempts per minute. Returns
the same user/token shape as registration.

### `POST /api/logout` and `GET /api/me`

Both require authentication. Logout revokes the current access token; `/me`
returns the current user's `id`, sanitized `name`, `email`, and `role`.

## Studio

All studio endpoints are throttled.

### `GET /api/studio/catalog`

Returns active base items and moods, their molds, available colors and inserts,
techniques, personalization choices, compatibility rules, and the
`handmade_variation` / `non_returnable` expectation-copy keys. Options are
grouped under `data.steps`.

### `POST /api/studio/validate`

Performs non-strict validation and returns compatibility warnings/violations
plus the current partial price.

```json
{
  "base_item_id": 12,
  "mood_id": 4,
  "base_color_id": 8
}
```

### `POST /api/studio/quote`

Accepts option IDs and free text only; it never accepts client-supplied prices
or snapshots. Guests may quote without a token. If a valid bearer token is
provided, the quote is associated with that customer.

```json
{
  "base_item_id": 12,
  "mold_id": 20,
  "mood_id": 4,
  "technique_id": 3,
  "base_color_id": 8,
  "accent_color_id": 9,
  "insert_ids": [5],
  "personalization_text": "For Sara",
  "font_id": 2,
  "text_color_id": 4,
  "note": "Center the lettering"
}
```

Returns `201` with a `quote_id`, itemized `price_breakdown`, integer `total`,
`ready_at`, and `expires_at`. Quotes expire after 30 minutes by default.
Configuration errors return `422` with the compatibility violations.
`inspiration_asset_ids` is not supported yet and is rejected.

Example response:

```json
{
  "success": true,
  "message": "Quote created.",
  "data": {
    "quote_id": "78fd1cab-8099-46e8-b884-5fa2e42bd330",
    "price_breakdown": {
      "items": [
        {"key": "base_item", "id": 12, "name": "Round Coaster Set", "amount": 28000},
        {"key": "base_color", "id": 8, "name": "Pearl White", "amount": 0}
      ],
      "total": 28000,
      "currency": "EGP"
    },
    "total": 28000,
    "ready_at": "2026-10-20T10:00:00+03:00",
    "expires_at": "2026-10-16T20:00:00+03:00"
  },
  "errors": {}
}
```

## Checkout and customer orders

### `POST /api/checkout`

Requires a customer token and an `Idempotency-Key` header. Submit a quote ID,
shipping details, payment method, optional gift options, and both acknowledgments.
Do not submit prices or customization data.

```http
Idempotency-Key: mobile-checkout-7f4d
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "quote_id": "78fd1cab-8099-46e8-b884-5fa2e42bd330",
  "shipping": {
    "recipient_name": "Amina",
    "phone": "+201234567890",
    "address_line1": "10 Example Street",
    "address_line2": null,
    "city": "Cairo",
    "governorate": "Cairo",
    "postal_code": "11511",
    "country": "Egypt"
  },
  "payment_method": "cash_on_delivery",
  "gift_options": {
    "gift_wrap": true,
    "gift_message": "Happy birthday"
  },
  "acknowledgments": {
    "handmade_variation": true,
    "non_returnable": true
  }
}
```

A successful first submission returns `201` with an order number, status,
itemized total, shipping data, `ready_by`, and the customer's customization
snapshot. Reusing the same idempotency key for that customer returns the
original order with `200`. If current catalog pricing changed, checkout returns
`409` with a fresh quote to review. Expired quotes return `422`, quotes owned
by another customer return `403`, and unavailable configurations return `422`.

Example successful response excerpt (minor-unit amounts shown; other order and snapshot fields are omitted):

```json
{
  "success": true,
  "message": "Order placed.",
  "data": {
    "id": 42,
    "order_number": "GFT-261016-AB12CD34",
    "status": "placed",
    "currency": "EGP",
    "subtotal": 28000,
    "total": 28000,
    "payment_method": "cash_on_delivery",
    "payment_status": "pending",
    "ready_by": "2026-10-20T10:00:00+03:00",
    "items": [
      {
        "id": 61,
        "name": "Round Coaster Set",
        "unit_price": 28000,
        "customization": {
          "base_item": {"id": 12, "name": "Round Coaster Set", "piece_count": 4},
          "mood": {"id": 4, "name": "Summer/Cheerful"},
          "color": {"base": {"id": 8, "name": "Pearl White"}}
        }
      }
    ]
  },
  "errors": {}
}
```

The default driver places cash-on-delivery orders as `placed`; generic online
payment methods remain `awaiting_payment` with payment status `pending`. No
payment provider is integrated.

### `GET /api/orders`

Returns the authenticated customer's paginated order list.

### `GET /api/orders/{id}`

Returns a single order only when it belongs to the authenticated customer.
Customer resources omit internal labor and production fields.

## Admin catalog

All catalog CRUD endpoints require an admin token. The resource paths are:

| Catalog type | Path |
|---|---|
| Base items | `/api/admin/catalog/base-items` |
| Molds | `/api/admin/catalog/molds` |
| Moods | `/api/admin/catalog/moods` |
| Color swatches | `/api/admin/catalog/color-swatches` |
| Techniques | `/api/admin/catalog/techniques` |
| Inserts | `/api/admin/catalog/inserts` |
| Fonts | `/api/admin/catalog/fonts` |
| Text colors | `/api/admin/catalog/text-colors` |
| Option rules | `/api/admin/catalog/option-rules` |

Each path supports `GET` list, `POST` create, `GET /{id}`, `PUT/PATCH /{id}`,
and `DELETE /{id}`. Deleting an item referenced by a quote or order returns
`409`.

Admins and producers may use the restricted availability endpoint:

```http
PATCH /api/admin/catalog/inserts/{id}/availability
```

It accepts only `{"is_available": false}`. Base items and moods use
`{"is_active": false}`; color swatches use `is_available`. Producers cannot
change catalog prices or other fields.
