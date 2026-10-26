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
`handmade_variation`, `refund_policy`, and `proof_auto_approval`
expectation-copy keys. Options are
grouped under `data.steps`.
The `checkout` object contains launch delivery, fee, deposit, lead-time, claim
window, and proof auto-approval settings. `catalog_version` is a stable catalog
hash returned in the response and as the quoted `ETag` header.

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
`ready_at`, and `expires_at`. `ready_at` is indicative; the production promise
is seven days after deposit confirmation. Quotes expire after 30 minutes by default.
Configuration errors return `422` with the compatibility violations.
Optional `inspiration_asset_ids` may contain up to three owned or guest-uploaded
asset UUIDs; they are included in the quote hash and attached to the order item
at checkout.

### `POST /api/studio/assets`

Accepts `multipart/form-data` with a `files[]` array of up to three JPEG, PNG,
or WebP images, each at most 5 MB. Authentication is optional. Images are
re-encoded to strip metadata and bound dimensions, then stored on a private
disk. The response includes an asset UUID and a signed file URL that expires
after 15 minutes. Do not expose the private storage path.

### `GET /api/assets/{asset}/file`

Requires a valid temporary signature. Customers may read only their own assets;
producer/admin accounts may read order assets. Guest inspiration uploads can
be read via their signed capability URL.

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
    "city": "Tanta",
    "governorate": "Gharbia",
    "postal_code": "11511",
    "country": "Egypt"
  },
  "payment_method": "card",
  "gift_options": {
    "gift_wrap": true,
    "gift_message": "Happy birthday"
  },
  "acknowledgments": {
    "handmade_variation": true,
    "refund_policy": true,
    "proof_auto_approval": true
  }
}
```

A successful first submission returns `201` with an order number, an
`awaiting_deposit` status, itemized total, shipping data, an indicative
`ready_by`, the customer's customization snapshot, and a pending deposit
payment intent. Deposit is `ceil(total * deposit_percent / 100)`; delivery fee
is included in `total`. Reusing the same idempotency key returns the original
order and open deposit attempt with `200`. If current catalog pricing changed,
checkout returns `409` with a fresh quote to review. Expired quotes return
`422`, quotes owned by another customer return `403`, and unavailable
configurations return `422`. Launch delivery accepts Tanta / Gharbia only.

Example successful response excerpt (minor-unit amounts shown; other order and snapshot fields are omitted):

```json
{
  "success": true,
  "message": "Order placed.",
  "data": {
    "id": 42,
    "order_number": "GFT-261016-AB12CD34",
    "status": "awaiting_deposit",
    "currency": "EGP",
    "subtotal": 28000,
    "delivery_fee": 0,
    "total": 28000,
    "deposit_amount": 14000,
    "balance_amount": 14000,
    "payment_method": "card",
    "payment_status": "unpaid",
    "payment_summary": {
      "deposit_amount": 14000,
      "balance_amount": 14000,
      "amount_paid": 0,
      "due_now": 14000
    },
    "ready_by": "2026-10-20T10:00:00+03:00",
    "payment": {
      "redirect_url": "https://shop.example.test/payments/fake?reference=...",
      "amount": 14000,
      "expires_at": "2026-10-17T10:00:00+03:00"
    },
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

### `POST /api/orders/{order}/payments`

Customer owner only. Accepts `kind` (`deposit` or `balance`) and `method`
(`card` or `wallet`). A deposit is payable only while the order is
`awaiting_deposit`; a balance is payable only while it is `awaiting_balance`.
Repeating an open attempt with the same method returns that attempt.

### `POST /api/payments/webhook/{provider}`

Unauthenticated, signature-verified server callback. Processing is idempotent.
Unknown payment references return `200` without a state change. Browser
redirects never update payment state. Fake webhook test payloads use
`reference`, `status` (`succeeded`, `failed`, `cancelled`), and `amount`, signed
with `X-Fake-Signature`.

### `POST /api/orders/{order}/cancel`

Customer owner only; cancels only orders still `awaiting_deposit` and cancels
their open deposit attempt.

Order statuses are `awaiting_deposit`, `confirmed`, `awaiting_balance`,
`ready_for_delivery`, `out_for_delivery`, `delivered`, `cancelled`, and
`refunded`. Payment statuses are `unpaid`, `deposit_paid`, `paid`,
`partially_refunded`, and `refunded`. Customer order data includes a display
label, deposit/balance/paid amounts, and the amount due now.

The fake gateway is the default outside production. The Paymob adapter is
intentionally a documented skeleton: this environment exposed the overview
page but not the exact API-reference and HMAC specification. Do not enable
`PAYMENT_GATEWAY=paymob` until those provider-specific details and account
configuration have been verified.

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
