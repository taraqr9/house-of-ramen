# House of Ramen POS API (v1)

REST API for the Android POS app. It runs the **same** business rules as the web POS (`App\Services\Pos\*`), so the web and the app always agree on totals, statuses and permissions.

- **Base URL:** `https://<your-domain>/api/v1`. Use HTTPS in production; tokens are bearer secrets.
- **Format:** JSON only. Send `Accept: application/json` (the server forces JSON anyway) and `Content-Type: application/json`.
- **Money:** always a **string** with 2 decimals (`"1150.00"`). Parse it into `BigDecimal`, never `Double`.
- **Times:** ISO-8601 with offset (`2026-09-27T14:05:12+06:00`).

---

## 1. Authentication

Sanctum personal access tokens. There is one token per `device_name`: logging in again from the same device replaces its old token. Tokens expire after `SANCTUM_TOKEN_TTL_DAYS` (default **30 days**). Expired tokens are pruned daily by the scheduler.

Send the token on every call:

```
Authorization: Bearer 12|x8Yk...
Accept: application/json
```

### `POST /auth/login`
Rate limited to **5 attempts per minute** per username+IP.

```json
{ "username": "cashier1", "password": "••••••", "device_name": "Counter Tablet 1" }
```

`200`:
```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "12|x8Yk…",
    "token_type": "Bearer",
    "expires_at": "2026-10-27T14:05:12+06:00",
    "user": {
      "id": 4, "name": "Rafatun", "username": "cashier1", "email": "…", "avatar_url": null,
      "roles": ["Admin"],
      "permissions": ["billing-view", "order-create", "order-view", "payment-create", "…"]
    }
  }
}
```

| Status | When |
|---|---|
| 401 | Wrong username or password (the same message either way) |
| 403 | Account disabled, or the user still has a temporary password (they must change it on the web first) |
| 422 | Missing fields |
| 429 | Too many attempts (see the `Retry-After` header) |

### `POST /auth/logout`
Revokes the current token.

### `GET /auth/me`
Returns the current user (same shape as `data.user` above).

If an account is deactivated, its existing tokens are refused on the next call (`403`) and the token is deleted.

---

## 2. Response format

Success:
```json
{ "success": true, "message": "Order opened.", "data": { … } }
```

Some endpoints add top-level keys next to `data`, such as `created`, `duplicate`, `round_no`, `completion`, `order_completed` or `meta`.

Paginated lists add:
```json
"meta": { "current_page": 1, "last_page": 3, "per_page": 30, "total": 71 }
```
Use `?page=2` for further pages.

Errors:
```json
{ "success": false, "message": "Validation failed.", "errors": { "submission_key": ["The submission key field is required."] } }
{ "success": false, "message": "Cannot change Gyoza from Pending to Ready." }
```

| Status | Meaning |
|---|---|
| 200 / 201 | OK / created |
| 401 | Missing, invalid or expired token |
| 403 | Authenticated, but lacks the permission (or the account is disabled) |
| 404 | Unknown id |
| 422 | Validation error (`errors` present) **or** a business rule, e.g. invalid kitchen transition, order already completed, card overpayment (`message` only) |
| 429 | Rate limited (`Retry-After` header). The general API limit is 180 requests/minute per user. |
| 500 | `"Something went wrong."`. Internal details are never returned. |

---

## 3. Permissions

The server checks every call; a token alone grants nothing. Use `user.permissions` only to show or hide screens.

| Action | Permission |
|---|---|
| Bootstrap / tables / menu | any POS permission |
| Open order, send rounds, view a **running** order | `order-create` |
| List active/completed orders, view any order, history | `order-view` |
| Edit guest count / note | `order-edit` |
| Cancel whole order | `order-cancel` |
| Cancel one item (floor staff) | `order_item-cancel` |
| Discount | `order-discount` |
| Complete order | `order-complete` |
| Billing summary, bill request | `billing-view` |
| Kitchen feed | `kitchen-view` |
| Start / Ready | `kitchen-update` |
| Kitchen reject item | `kitchen-cancel` |
| Mark dish unavailable on the menu | `restaurant_menu_item-edit` |
| Ready-to-serve feed | `serving-view` |
| Acknowledge / Served | `serving-update` |
| List payments | `payment-view` |
| Take payment | `payment-create` |
| Void payment | `payment-delete` |
| Reports | `pos_report-view` |

---

## 4. Idempotency (retries are safe)

Generate a **UUID** per user action and **reuse it for every retry** of that same action.

- **Sending a round:** `submission_key` is **required**. The key is stored on the round's items and checked while the order is locked. A retry, even hours later from an offline queue, returns `200` with `"duplicate": true` and the current order; the items are **not** added twice. Use a new key for the next round.
- **Taking a payment:** `idempotency_key` is **required**. A retry returns the original payment with `200` and `"duplicate": true`. Reusing a key on a *different* order gets `422`.
- **Opening a table:** safe to repeat without a key. If the table already has a running order, that order is returned with `200` and `"created": false`. Two devices opening the same table at the same moment get the same order.
- **Served:** marking an item served twice returns `200` both times.

---

## 5. Endpoints

All paths below are under `/api/v1/pos`.

### 5.1 Reference data

**`GET /bootstrap`**: call once after login, and again when `catalog_updated_at` changes.
```json
{
  "api_version": "v1", "server_time": "…", "poll_interval_seconds": 4,
  "user": { … },
  "restaurant": { "id": 1, "name": "House of Ramen", "phone": "…", "address": "…", "area": "…", "logo_url": "…",
                  "vat_percent": "5.00", "service_charge_percent": "10.00", "currency": "BDT", "currency_symbol": "৳" },
  "tables": [ <Table> ],
  "catalog_updated_at": "…",
  "categories": [ { "id": 1, "name": "Ramen", "display_order": 1 } ],
  "menu_items": [ { "id": 7, "category_id": 1, "category_name": "Ramen", "name": "Shoyu Ramen", "price": "500.00",
                    "price_note": null, "is_available": true, "is_featured": false, "is_new": false,
                    "image_url": "https://…/storage/…jpg", "display_order": 0, "updated_at": "…" } ],
  "options": { "order_types": [{"value":"dine_in","label":"Dine In"}, …], "order_statuses": […], "item_statuses": […],
               "payment_methods": […], "discount_types": […], "kitchen_cancel_reasons": [{"value":"out_of_stock","label":"Out of stock"}, …] }
}
```
- Menu items only come from active categories. Items with `is_available: false` are included so the app can show them greyed out; the server rejects them in a round.
- There is no SKU/code field.

**`GET /tables`**: active tables. `<Table>`:
```json
{ "id": 1, "name": "T1", "area": "Ground Floor", "capacity": 4, "display_order": 1, "is_active": true,
  "status": "available | occupied | bill_requested",
  "active_order": null | { "id": 8, "order_number": "ORD-260927-00008", "status": "open", "guest_count": 4, "grand_total": "2541.50", "opened_at": "…" } }
```
Occupancy comes from the running order, not a stored flag.

**`GET /menu`**: `catalog_updated_at`, `categories` and `menu_items` only.

### 5.2 Orders

`<Order>` (the same shape is used everywhere):
```json
{
  "id": 8, "order_number": "ORD-260927-00008", "order_type": "dine_in", "status": "open", "status_label": "Open", "is_active": true,
  "table": { "id": 1, "name": "T1" }, "guest_count": 4, "general_note": "Birthday",
  "totals": { "subtotal": "2210.00", "discount_type": null, "discount_value": "0.00", "discount": "0.00",
              "service_charge_percent": "10.00", "service_charge": "221.00", "vat_percent": "5.00", "vat": "110.50",
              "grand_total": "2541.50", "paid": "0.00", "due": "2541.50" },
  "payment_status": "unpaid | partial | paid | nothing_due",
  "opened_at": "…", "opened_by": {"id":1,"name":"Admin"}, "bill_requested_at": null,
  "completed_at": null, "completed_by": null, "cancelled_at": null, "cancelled_by": null, "cancellation_reason": null,
  "updated_at": "…",
  "items": [ <OrderItem> ], "payments": [ <Payment> ], "payment_method_summary": "cash | card | mixed | null"
}
```
- Order `status`: `open` → `bill_requested` → `completed`, or `cancelled` at any point before completion.
- `table` is `null` for takeaway. The table name is a snapshot, so it still shows even if the table is later renamed or deleted.

`<OrderItem>`:
```json
{ "id": 31, "order_id": 8, "menu_item_id": 7, "category_id": 1, "name": "Shoyu Ramen", "category_name": "Ramen",
  "quantity": 2, "unit_price": "500.00", "line_total": "1000.00", "note": "no egg", "round_no": 1,
  "status": "pending | preparing | ready | served | cancelled", "status_label": "Pending",
  "sent_at": "…", "preparing_at": null, "ready_at": null, "ready_acknowledged_at": null,
  "served_at": null, "served_by": null, "cancelled_at": null, "cancelled_by": null, "cancellation_reason": null, "updated_at": "…" }
```
`name`, `unit_price` and `category_name` are **snapshots** taken when the item was sent, so later menu changes never alter an order.

| Method & path | Body | Notes |
|---|---|---|
| `POST /orders` | `{ "order_type": "dine_in", "dining_table_id": 1, "guest_count": 2, "general_note": "…" }` or `{ "order_type": "takeaway" }` | `201` with `"created": true`, or `200` with `"created": false` for an existing running order on that table |
| `GET /orders/active` | filters: `order_type`, `status` (`open`/`bill_requested`), `dining_table_id`, `keyword` | paginated, 50 per page, no items |
| `GET /orders/{id}` | | full order with items and payments |
| `PATCH /orders/{id}` | `{ "guest_count": 3, "general_note": "…" }` | `order-edit` |
| `POST /orders/{id}/rounds` | `{ "submission_key": "uuid", "items": [ { "menu_item_id": 7, "quantity": 2, "note": "no egg" } ] }` | `201` with `round_no` and `"duplicate": false`; a retry returns `200` with `"duplicate": true`. The same dish with different notes becomes separate lines. Prices are always taken from the server. |
| `POST /orders/{id}/bill-request` | | status becomes `bill_requested`; adding a new round moves it back to `open` |
| `POST /orders/{id}/cancel` | `{ "reason": "Customer left" }` | refused if completed payments exist (void them first) or if already completed |
| `POST /order-items/{id}/floor-cancel` | `{ "reason": "Wrong item" }` | floor staff; allowed for pending, preparing or ready items |

### 5.3 Kitchen

| Method & path | Body | Notes |
|---|---|---|
| `GET /kitchen/feed?since=<server_time>` | | see *Feeds* below |
| `POST /order-items/{id}/start` | | `pending` → `preparing` |
| `POST /order-items/{id}/ready` | | `preparing` → `ready` |
| `POST /order-items/{id}/cancel` | `{ "reason": "out_of_stock", "reason_detail": "…" }` | kitchen rejection, **pending/preparing only**. `reason` must be one of `kitchen_cancel_reasons`; `other` requires `reason_detail`. Only this item is cancelled; totals are recalculated. Refused if payments would then exceed the new total. The response includes `offer_mark_unavailable`. |
| `POST /order-items/{id}/mark-menu-unavailable` | | a separate, explicit action (`restaurant_menu_item-edit`); cancelling never does this automatically |

Status changes go one step at a time. Anything else returns `422`, for example `"Cannot change Gyoza from Pending to Ready."`. There are no server-side "start all" / "ready all" endpoints: call start or ready for each item.

### 5.4 Ready to serve

| Method & path | Notes |
|---|---|
| `GET /serving/feed?since=<server_time>` | ready items, plus item cancellations from the last 30 minutes on running orders so the waiter can tell the table |
| `POST /order-items/{id}/acknowledge` | "Got it"; clears the new-item highlight for everyone |
| `POST /order-items/{id}/served` | `ready` → `served`; repeating it is a no-op returning `200` |

**Feeds (polling):**
```json
{ "server_time": "2026-09-27T14:05:12+06:00", "full": true,
  "items": [ { "id": 31, "order_id": 8, "order_number": "ORD-…", "table": "T1", "order_note": "…", "order_active": true,
               "round_no": 1, "name": "Shoyu Ramen", "quantity": 2, "note": "no egg", "status": "pending",
               "acknowledged": false, "sent_at": "…", "ready_at": null, "updated_at": "…",
               "cancelled_at": null, "cancellation_reason": null, "cancelled_by": null } ] }
```
1. The first call has no `since` and returns `full: true` with a complete snapshot: replace your local list.
2. On later calls, pass the previous `server_time` as `since`. You get only items changed since then, in **any** status, so you can remove items that left your screen, e.g. a kitchen item now `ready`.
3. Merge by `id`. Repeated items are expected, because the server allows a 2-second overlap.
4. If `since` is more than 6 hours old, you get `full: true` again.

The suggested polling interval is `poll_interval_seconds`, 4 seconds. The feed format works unchanged if FCM "something changed" pushes are added later.

### 5.5 Billing

| Method & path | Body | Notes |
|---|---|---|
| `GET /orders/{id}/billing` | | full `<Order>` plus top-level `completion: { "allowed": false, "blockers": ["1 item(s) are not served yet…", "Payment is incomplete. Amount due: 1150.00"], "outstanding_items": 1 }` |
| `POST /orders/{id}/discount` | `{ "discount_type": "fixed", "discount_value": 100 }` or `{ "discount_type": "percent", "discount_value": 10 }` | a value of `0` removes the discount; refused if it would drop the bill below the amount already paid |

The server calculates totals as follows: subtotal (non-cancelled lines), minus discount; service charge % and VAT % are then each applied to that discounted amount. VAT and service-charge rates are copied onto the order when it is opened.

### 5.6 Payments and completion

`<Payment>`:
```json
{ "id": 3, "order_id": 8, "method": "cash | card", "amount": "1150.00", "tendered_amount": "2000.00", "change_amount": "850.00",
  "reference_no": null, "remarks": null, "status": "completed | voided", "paid_at": "…",
  "received_by": {"id":1,"name":"Admin"}, "voided_at": null, "voided_by": null, "void_reason": null }
```

| Method & path | Body | Notes |
|---|---|---|
| `GET /orders/{id}/payments` | | includes voided payments |
| `POST /orders/{id}/payments` | `{ "payment_method": "cash", "amount": 2000, "idempotency_key": "uuid", "reference_no": "4242", "remarks": "…" }` | `201`, or `200` with `"duplicate": true` on retry. Response: `data.payment`, `data.order`, `order_completed`, `completion_blocked_reason`. |
| `POST /payments/{id}/void` | `{ "reason": "Wrong card" }` | only on running orders; the payment row is kept with `status: voided` |
| `POST /orders/{id}/complete` | | requires everything served or cancelled and the bill fully paid |

Payment rules:
- **Split or mixed:** post one payment per tender, e.g. cash 1000 then card 1500.
- **Card:** an amount above the amount due is rejected with `422`.
- **Cash:** an amount above the amount due records only the due amount, with `tendered_amount` and `change_amount` filled in.
- **Auto-complete:** when a payment settles the bill, the order completes automatically if the user has `order-complete` and no items are outstanding (`order_completed: true`). Otherwise `completion_blocked_reason` explains why, and `POST /complete` can be used later.
- **Table release:** completing or cancelling an order releases its table.

### 5.7 History

| Method & path | Notes |
|---|---|
| `GET /orders/completed` | filters: `status` (`completed` default, or `cancelled`), `date_from`, `date_to`, `dining_table_id`, `order_number`, `payment_method` (`cash`/`card`/`mixed`), `completed_by`, `keyword`. Paginated, 30 per page, includes payments. |
| `GET /orders/{id}/history` | full read-only order: rounds, notes, kitchen timestamps, who served or cancelled what, and every payment including voided ones |

Completed and cancelled orders are read-only: every change returns `422`.

### 5.8 Reports (`pos_report-view`)

Query parameters `date_from` and `date_to` (`YYYY-MM-DD`). Both default to today. Only completed orders count as sales, dated by their completion time.

- **`GET /reports/summary`** returns `orders_count, sales, average_order, subtotal, discount, service_charge, vat, cash_total, card_total, mixed_orders, mixed_total, cancelled_orders_count, cancelled_orders_value, cancelled_items_value`, plus `date_from` / `date_to`.
- **`GET /reports/sales`** returns the same fields plus `by_item`, `by_category`, `by_table`, `cancelled_orders` and `cancelled_items`.

---

## 6. Typical flows

**Waiter:**
1. `GET /bootstrap`
2. Tap a free table: `POST /orders`
3. `POST /orders/{id}/rounds` with a new `submission_key`
4. Poll `GET /serving/feed`
5. `POST /order-items/{id}/served`

**Kitchen:** poll `GET /kitchen/feed`, then `POST /order-items/{id}/start` and `POST /order-items/{id}/ready`. Use `/cancel` with a reason if an item can't be made.

**Cashier:**
1. `GET /orders/{id}/billing`
2. Optionally `POST /orders/{id}/discount`
3. Optionally `POST /orders/{id}/bill-request` (prints the bill on the web)
4. `POST /orders/{id}/payments`, once or several times for split payments; the order auto-completes when settled
5. Otherwise `POST /orders/{id}/complete`
