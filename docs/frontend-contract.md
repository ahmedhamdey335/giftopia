# Giftopia frontend contract

This contract is for the separate Next.js application. All endpoints below use
the `/api` prefix. Amounts are integer piasters and currency is `EGP`.

## Authentication and CORS

Register or log in with `POST /api/register` or `POST /api/login`. Store the
returned Sanctum token using the frontend's secure token strategy and send
`Authorization: Bearer <token>` on authenticated requests. Registration always
creates a customer; clients cannot select a role. Guests can browse, validate,
quote, upload inspiration, and post studio funnel events. Guest drafts stay in
browser storage; the API does not persist guest drafts.

The API CORS allowlist comes from comma-separated `FRONTEND_URL`. Send
`Authorization`, `Content-Type`, and `Idempotency-Key` headers as applicable.
Do not use redirects as proof of payment; the frontend must read order status
from the authenticated API.

## Catalog caching and drafts

`GET /api/studio/catalog` returns `catalog_version` in JSON and as an `ETag`.
Cache the catalog and send `If-None-Match` on subsequent reads; an unchanged
catalog returns `304 Not Modified`, while a changed catalog returns `200` with
the new ETag and data. When the version changes, refresh the catalog and
revalidate any restored draft.

Guest draft JSON is versioned and contains option IDs, not labels or prices:

```json
{
  "schema_version": 1,
  "option_ids": {
    "base_item_id": 12,
    "mold_id": 20,
    "mood_id": 4,
    "technique_id": 3,
    "base_color_id": 8,
    "accent_color_id": 9,
    "insert_ids": [5],
    "font_id": 2,
    "text_color_id": 4
  },
  "text": "For Sara",
  "note": "Center the lettering",
  "inspiration_asset_ids": [],
  "saved_at": "2026-10-26T10:00:00+03:00",
  "catalog_version": "sha256-hex-string"
}
```

Restore a draft by mapping its option IDs to the flat request keys
(`base_item_id`, `mold_id`, `mood_id`, `technique_id`, `base_color_id`,
`accent_color_id`, `insert_ids`, `font_id`, `text_color_id`), `text` to
`personalization_text`, and `note` to `personalization_note`. Then call
`POST /api/studio/validate`. Use returned violations/warnings and partial
pricing to prompt the customer to repair stale or unavailable selections.
Only after successful strict validation call `POST /api/studio/quote`.

Upload inspiration first with `POST /api/studio/assets` as multipart
`files[]` (maximum three images, 5 MB each). The returned UUIDs can be placed
in `inspiration_asset_ids`; the server rechecks ownership and availability
when quoting and attaches them at checkout.

## Quote, checkout, and payment

`POST /api/studio/quote` returns a `quote_id`, integer price breakdown, total,
indicative `ready_at`, and expiration. The customer-facing promise is **seven
days after the deposit is confirmed**; the quote date is only indicative.

After login, submit `POST /api/checkout` with the quote ID, Tanta/Gharbia
shipping, `payment_method` (`card` or `wallet`), `Idempotency-Key`, and required
acknowledgments:

- Always: `handmade_variation` and `refund_policy`.
- Personalized text: also `proof_auto_approval`.

The response includes the order, `payment.redirect_url`, deposit amount, and
hold expiration. Redirect the customer to the payment provider. After return
from the provider, poll `GET /api/orders/{order}` with the bearer token until
the signed webhook has updated the order; never mark payment successful from
the browser return URL. A pending deposit expires after the configured hold
period. `POST /api/orders/{order}/cancel` only works before deposit payment.

When the item is packed, the order moves to `awaiting_balance`. Create the
balance attempt with `POST /api/orders/{order}/payments` and
`{"kind":"balance","method":"card"}` or `wallet`; redirect and poll as for the
deposit. Delivery is only available after the balance is paid.

## Orders and customer-facing states

`GET /api/orders` lists the current customer's orders;
`GET /api/orders/{order}` returns one owned order. The response contains
`status_label`, amounts paid/due, delivery information, gift options, and
each item's `proof_status`. `GET /api/orders/{order}/timeline` contains only
customer-safe milestone events.

| API order status | Suggested customer label |
|---|---|
| `awaiting_deposit` | Deposit payment needed |
| `confirmed` | Order confirmed |
| `awaiting_balance` | Balance payment needed |
| `ready_for_delivery` | Ready for delivery |
| `out_for_delivery` | Out for delivery |
| `delivered` | Delivered |
| `cancelled` | Cancelled |
| `refunded` | Refunded |

Internal item production statuses are `placed`, `awaiting_proof`,
`ready_to_start`, `in_production`, `curing`, `qc`, and `packed`. They are for
staff workflows and are not customer order states.
Customer `proof_status` values are `none`, `pending`, `approved`, and
`changes_requested`; use the proof endpoints and customer-safe timeline rather
than attempting to update these values directly.

## Proofs

For personalized orders, show `GET /api/orders/{order}/proof` after deposit
confirmation. The customer can approve all pending proofs with
`POST /api/orders/{order}/proof/approve`, or request changes with
`POST /api/orders/{order}/proof/request-changes` and a required note. A revised
proof increments the version; only the text and note may change. The customer
must approve the current version before production starts. A reminder is sent
after 24 hours; the proof auto-approves after the configured period (default
48 hours). The timeline records proof requests, approvals, and changes.

## Refunds

The customer can submit one open claim per order through
`POST /api/orders/{order}/refund-requests` with a 10–1000 character reason and
optional evidence UUIDs. Upload evidence with `POST /api/refund-evidence`
(`order_id` plus multipart `files[]`) before submitting the claim. Delivered
orders can be claimed only during `STUDIO_CLAIM_WINDOW_DAYS` after delivery
(default three days). The approved proof and QC photos are retained as
staff-facing references. Poll `GET /api/orders/{order}/refund-requests` for the
request state and decision note.

Request states are `requested`, `approved`, `rejected`, `refunded`, and
`failed`. A provider failure is not silently treated as success; staff can
retry a failed refund. Refunds are limited to the amount paid.

## Reorder

`POST /api/orders/{order}/reorder` revalidates the stored design against the
current catalog and returns a new quote plus an editable draft. Inspiration
images are deliberately not copied. A `422` response includes
`errors.issues` and `errors.draft`; map the draft back to the same browser
schema above so the customer can change unavailable choices.

## Errors and event reporting

Responses use:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "data": null,
  "errors": {"field": ["Explanation"]}
}
```

Handle `401` by prompting authentication, `403` as an authorization failure,
`404` as a missing/hidden resource, `409` as a conflict requiring refreshed
data or idempotent retry, and `422` by displaying field errors. `429` means
the client should back off.

The client may send up to 20 events to `POST /api/studio/events`, each with a
random UUID `session_id`, `step` 1–6, and `event` `viewed`, `completed`, or
`abandoned`. Do not include names, email addresses, or other personal data.
