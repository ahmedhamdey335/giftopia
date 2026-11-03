# Studio: from an idea to an order-ready quote

The Studio is the customer’s design conversation with Giftopia. The customer
chooses the shape and look of a keepsake. The backend checks that the
combination can be made and calculates the price. A quote is a short-lived,
server-calculated offer that can later be accepted at checkout.

## The choices in business language

- **Base item:** the product being made, such as a tray, keychain, bookmark,
  or coaster set. It supplies the starting price and personalization limit.
- **Mold:** the shape and size used to pour the resin. Each mold belongs to a
  base item and can add to the price.
- **Mood:** a design direction such as “Summer/Cheerful.” A mood can suggest a
  technique, colors, or insert, but the customer remains in control.
- **Technique:** the resin finish or visual treatment. It may add cost or
  require an accent color.
- **Insert:** an optional object placed in the resin, such as flowers, foil,
  shell, or glitter. Availability and mold depth can affect whether it fits.
- **Font and text color:** the style and color used for optional text.
- **Personalization:** the text and optional note for the maker. Text length
  is limited by the selected base item. A personalized order needs a font,
  text color, and customer proof approval before production.
- **Inspiration assets:** optional reference images. They help the maker
  understand the customer’s idea; they are not an order price or a promise to
  copy an image exactly.
- **Quote:** the backend’s saved configuration, price breakdown, total,
  indicative ready date, and expiration time.
- **Proof:** a preview of the requested text styling. The customer can approve
  it or request changes before production.
- **Refund:** a customer claim reviewed by staff under the claim-window and
  amount-paid rules.
- **Admin workflow:** staff maintain catalog choices, prices, availability,
  compatibility rules, and blackout dates. Producers and admins manage
  production; admins also manage catalog records and reports.

## What the backend receives

The browser sends catalog IDs (not labels or prices), optional text and notes,
and optional uploaded asset IDs. For example:

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
  "personalization_note": "Center the lettering",
  "inspiration_asset_ids": []
}
```

The IDs must refer to current catalog records. The API accepts partial choices
for live validation, but a quote requires the essential choices and a valid
combination. Prices sent by a browser are never trusted.

## Validation and compatibility

Validation is like a design check before the customer asks the maker to reserve
the quoted price. The backend checks:

- required choices are present for a quote;
- the selected mold belongs to the selected base item;
- base and accent colors have the correct roles and are available;
- a technique that needs an accent has one;
- an insert is available and fits the mold depth;
- text does not exceed the base item’s character limit;
- a personalized design has both a font and text color;
- admin-defined rules do not block the combination and required options are
  included;
- inspiration asset IDs refer to usable, unattached inspiration images.

`POST /api/studio/validate` is non-strict: incomplete work can receive
warnings, compatibility hints, and partial pricing. `POST /api/studio/quote`
is strict: unresolved problems prevent quote creation and return `422` with
violations to explain what must change.

## What quote creation means

After strict validation succeeds, the backend stores a quote containing:

- the normalized design choices and optional personalization;
- a hash of the configuration, so the accepted design can be checked later;
- the server-calculated itemized price and total in integer piasters;
- an indicative ready date;
- an expiration time (30 minutes by default).

The quote does not mean payment has happened or production has started. At
checkout, the backend checks availability and price again. If the catalog
price changed, checkout returns a fresh quote for the customer to review.
After checkout, the order starts in `awaiting_deposit`. Signed payment
webhooks update payment state; a browser return alone cannot confirm payment.
The production promise is calculated from deposit confirmation, not the
quote’s indicative date.

## How price and compatibility work

The backend resolves each ID to its current database record. `PriceCalculator`
adds the base item price, mold and color price differences, technique price,
insert prices, and—when text is present—font and text-color price differences.
Amounts are integer piasters and the currency is EGP. The configured delivery
fee is added later during checkout, not in the Studio quote.

Compatibility is checked separately from price. Admin-managed option rules
can block or require combinations. The validator also applies built-in
business checks such as mold ownership, color roles, stock availability,
insert depth, required accent colors, and personalization limits. A low price
does not mean a design is valid; the quote is created only after strict
compatibility succeeds.

## Inspiration and personalization

Inspiration images are uploaded before quoting using
`POST /api/studio/assets` as multipart `files[]`. Up to three JPEG, PNG, or
WebP images of at most 5 MB each are accepted. The response includes asset
UUIDs; include them in `inspiration_asset_ids`. The backend checks the IDs
again when validating and quoting, and links them to the resulting order at
checkout. Upload responses include short-lived signed file URLs.

Personalization text is optional. If supplied, it must meet the base item’s
character limit and include a font and text color. When checkout accepts the
quote, the backend records the customer acknowledgments. After the deposit is
confirmed, the backend creates a proof for each personalized item. Customers
can approve it or request changes; a revised proof gets a new version.
Unanswered proofs auto-approve after the configured period (48 hours by
default), so customers should respond promptly.

## Example endpoints

All examples use the `/api` prefix. Responses use the common envelope:
`success`, `message`, `data`, and `errors`. Use the real IDs returned by the
catalog endpoint; the sample IDs below are illustrative.

### 1. Read the catalog

```http
GET /api/studio/catalog
Accept: application/json
```

The response’s `data.steps` groups base items, molds, moods, colors,
techniques, inserts, fonts, and text colors. It also includes compatibility
rules, expectation-copy keys, checkout settings, and `catalog_version`.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "steps": {
      "base_items": [{"id": 12, "name": "Round Coaster Set", "base_price": 28000}],
      "molds": [{"id": 20, "base_item_id": 12, "name": "Round", "price_delta": 0}],
      "moods": [{"id": 4, "name": "Summer/Cheerful"}],
      "colors": {"base": [{"id": 8, "name": "Pearl White"}], "accent": [{"id": 9, "name": "Gold"}]},
      "techniques": [{"id": 3, "name": "Gradient", "requires_accent": true}],
      "inserts": [{"id": 5, "name": "Gold Foil Flakes"}],
      "personalization": {"fonts": [{"id": 2, "name": "Classic Serif"}], "text_colors": [{"id": 4, "name": "Gold Leaf"}]}
    },
    "rules": [],
    "expectation_copy_keys": ["handmade_variation", "refund_policy", "proof_auto_approval"],
    "checkout": {"delivery_cities": ["Tanta", "طنطا"], "deposit_percent": 50},
    "catalog_version": "catalog-hash"
  },
  "errors": {}
}
```

The real response contains additional fields. The server may return `304 Not
Modified` when a matching `If-None-Match` header is supplied.

### 2. Check a design while it is being built

```http
POST /api/studio/validate
Content-Type: application/json
```

```json
{
  "base_item_id": 12,
  "mold_id": 20,
  "mood_id": 4,
  "technique_id": 3,
  "base_color_id": 8,
  "insert_ids": []
}
```

This endpoint can return `allowed`, `blocked`, `violations`, `warnings`,
`blocked_options`, `required_options`, and partial `price` information. It
does not reserve prices or create an order.

### 3. Request a strict quote

```http
POST /api/studio/quote
Content-Type: application/json
```

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
  "personalization_note": "Center the lettering",
  "inspiration_asset_ids": []
}
```

Success returns HTTP `201` with `quote_id`, `price_breakdown`, `total`,
`ready_at`, and `expires_at`. A configuration that cannot be made returns
HTTP `422` with validation violations. Guests may request quotes; an optional
Sanctum token associates a quote with that customer.

### 4. Log in

```http
POST /api/login
Content-Type: application/json
```

```json
{
  "email": "customer@example.test",
  "password": "your-local-password"
}
```

Success returns the customer’s user details and a Sanctum token. Send later
authenticated requests with:

```http
Authorization: Bearer <token>
```

Registration is `POST /api/register`; it always creates a customer account.

### 5. Accept a quote at checkout

```http
POST /api/checkout
Authorization: Bearer <token>
Idempotency-Key: <unique-key-for-this-checkout-attempt>
Content-Type: application/json
```

```json
{
  "quote_id": "78fd1cab-8099-46e8-b884-5fa2e42bd330",
  "shipping": {
    "recipient_name": "Amina",
    "phone": "+201234567890",
    "address_line1": "10 Example Street",
    "city": "Tanta",
    "governorate": "Gharbia",
    "country": "Egypt"
  },
  "payment_method": "card",
  "gift_options": {
    "gift_wrap": false,
    "gift_message": null
  },
  "acknowledgments": {
    "handmade_variation": true,
    "refund_policy": true,
    "proof_auto_approval": true
  }
}
```

The proof acknowledgment is required for a personalized quote and may be
omitted otherwise. A successful response includes the order and a payment
`redirect_url`. Reuse the same idempotency key when safely retrying the same
checkout request, so the API can return the existing order instead of
creating another one.

The API recalculates price during checkout. A changed price returns HTTP `409`
with a fresh quote to review; an expired quote returns `422`. The order is
initially awaiting a deposit. Payment status changes only after the backend
accepts a signed provider webhook.

## Related API routes

- Upload inspiration: `POST /api/studio/assets`
- Customer orders: `GET /api/orders` and `GET /api/orders/{order}`
- Customer-safe timeline: `GET /api/orders/{order}/timeline`
- Proof review: `GET /api/orders/{order}/proof`,
  `POST /api/orders/{order}/proof/approve`, and
  `POST /api/orders/{order}/proof/request-changes`
- Refund claims: `POST /api/orders/{order}/refund-requests`
- Staff production, catalog, calendar, and refund endpoints are grouped under
  `/api/admin` in `backend/routes/api.php`

See [`architecture.md`](./architecture.md) for how these backend modules fit
together, and [`api.md`](./api.md) for the full endpoint contract.
