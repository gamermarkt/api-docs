# GamerMarkt API

REST API for the GamerMarkt gaming marketplace platform.

**Base URL:** `https://www.gamermarkt.com/api/v1`

---

## Usage Options

### 1. Swagger Editor

1. Go to [https://editor.swagger.io/](https://editor.swagger.io/).
2. Select **File → Import file** from the top menu.
3. Upload the `openapi.yaml` file from this repo.
4. Interactive documentation is rendered in the right panel — use **Try it out** on any endpoint to send live requests.

### 2. Postman Collection

1. Open Postman and click **Import** in the top left.
2. On the **File** tab, drag and drop `openapi.yaml` or browse to select it.
3. Postman automatically converts the file into a collection — all endpoints arrive grouped in folders.
4. After importing, create an **Environment** and add the following variables:
   - `base_url` → `https://www.gamermarkt.com/api/v1`
   - `api_key` → your 64-character hex API key
5. In each request's **Headers** tab, add `X-Api-Key: {{api_key}}`.

### 3. PHP Client

A ready-to-use PHP client is available in the [`php/`](php/) directory.

| File                                                   | Description      |
| ------------------------------------------------------ | ---------------- |
| [`php/gamermarkt.class.php`](php/gamermarkt.class.php) | API client class |
| [`php/examples.php`](php/examples.php)                 | Usage examples   |

**Requirements:** PHP 7.4+, cURL extension.

```php
require_once 'php/gamermarkt.class.php';

$api     = new GamerMarkt('your_64_char_hex_api_key');
$account = $api->getAccount();

echo $account['balance'];   // "150.00"
echo $account['currency'];  // "TRY"
```

For full usage examples see [`php/examples.php`](php/examples.php).

---

## Authentication

Every request must be authenticated with a **64-character hex API key** via the `X-Api-Key` header:

```
X-Api-Key: <api_key>
```

### Obtaining an API Key

1. Log in to your GamerMarkt account.
2. Navigate to **Dashboard → API Access** (`/api-access`).
3. Complete email verification and click **Generate API Key**.
4. Copy the key immediately — it will be partially masked afterwards.

> **Note:** Only one key exists per account. You can regenerate it at any time (the old key becomes invalid immediately) or toggle it active/inactive. A minimum purchase history may be required before key generation is unlocked.

---

## Currencies

Your account currency is determined by your registered country:

- **Turkish accounts** → `TRY`
- **All other accounts** → `EUR`

Prices and orders are always in your account currency, wherever your requests come from. A Turkish account also needs a verified phone number to place orders; without it `POST /orders` fails with `Currency mismatch.`

All monetary values in responses are returned as **strings** with exactly two decimal places (e.g. `"50.00"`), using `.` as the decimal separator and no thousands separator.

---

## Pagination

Paginated endpoints accept `page` and `per_page` query parameters and return a `meta` object:

```json
{
  "meta": {
    "total": 250,
    "page": 2,
    "per_page": 20,
    "last_page": 13
  }
}
```

---

## Error Format

All errors follow a consistent envelope:

```json
{
  "success": false,
  "error": "Human-readable message.",
  "code": "ERROR_CODE"
}
```

See individual endpoints for the specific error codes they can return.

---

## Rate Limiting

> **All endpoints share a single limit of 15 requests per minute.**
>
> Exceeding this limit will cause your requests to be blocked for an extended period. Design your integration accordingly — cache responses where possible and avoid polling at high frequency.

---

## Endpoints

### Account

#### `GET /account`

Returns the authenticated user's available and blocked balance in their account currency.

**Response:**

```json
{
  "success": true,
  "data": {
    "balance": "150.00",
    "blocked_balance": "10.00",
    "currency": "TRY"
  }
}
```

| Field             | Description                                                        |
| ----------------- | ------------------------------------------------------------------ |
| `balance`         | Spendable balance                                                  |
| `blocked_balance` | Balance on hold by GamerMarkt — not spendable until it is released |
| `currency`        | `TRY` or `EUR`                                                     |

---

### Products

#### `GET /products`

Returns active, purchasable standard products. To include top-up products as well, pass `list_topups=1`.

**Query Parameters:**

| Parameter     | Type            | Description                                                                                                                                                    |
| ------------- | --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `lang`        | `tr` \| `en`    | Language for `name` and `category_name` fields. Defaults to the authenticated user's account language.                                                         |
| `per_page`    | integer (1–100) | Items per page; a value outside 1–100 is moved to the nearest limit. **When omitted, all products are returned in a single response without a `meta` object.** |
| `page`        | integer (≥1)    | Page number. Only used when `per_page` is also provided. Default: `1`                                                                                          |
| `category_id` | integer         | Filter by category ID. Omit to return all categories.                                                                                                          |
| `list_topups` | `0` \| `1`      | When set to `1`, top-up products are included alongside standard products. Default: `0`                                                                        |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "ref": "steam-50-try",
      "name": "Steam 50 TRY",
      "category_id": 5,
      "category_name": "Steam",
      "is_topup": false,
      "price": "50.00",
      "currency": "TRY",
      "stock": 120,
      "topup_inputs": null
    }
  ]
}
```

| Field          | Description                                                                                                                 |
| -------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `ref`          | Unique product reference — use this value when creating orders                                                              |
| `is_topup`     | `true` for products that require buyer input before fulfilment                                                              |
| `topup_inputs` | Input definition array for top-up products; `null` for standard products                                                    |
| `price`        | Unit price in the user's account currency                                                                                   |
| `stock`        | Units available: exact below 50; from 50 up `50` (50–100), `100` (101–200) or `200` (more than 200); `0` means out of stock |

When `per_page` is provided, a `meta` pagination object is included in the response.

Top-up products include a `topup_inputs` array. Each entry describes one required field for ordering, for example:

```json
{
  "key": "riot_id",
  "type": "TEXT",
  "label": "Riot ID",
  "placeholder": "Örn: Player#TR1",
  "sort": 0,
  "max": 255
}
```

---

### Coupons

#### `GET /coupons`

Returns the authenticated user's coupons, newest first: the same list, states and figures as the **My Coupons** page on the website. Deleted coupons are not listed.

An `active` coupon is applied to an order automatically when it fits the ordered products (see `POST /orders`); its `code` can also be sent as `coupon_code` to pick it.

**Query Parameters:**

| Parameter  | Type                                     | Default          | Description                                                                                                                  |
| ---------- | ---------------------------------------- | ---------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `status`   | `all` \| `active` \| `used` \| `invalid` | `all`            | Filter by state. `invalid` covers cancelled, expired and run-out coupons. `meta.counts` holds the number of coupons in each. |
| `lang`     | `tr` \| `en`                             | account language | Language of `name` and `categories`                                                                                          |
| `page`     | integer (≥1)                             | `1`              | Page number                                                                                                                  |
| `per_page` | integer (1–50)                           | `20`             | Items per page                                                                                                               |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 812,
      "code": "K7Q2XPA",
      "name": "Special for You",
      "status": "active",
      "categories": ["Steam"],
      "discount_percentage": "10.00",
      "max_discount_amount": "25.00",
      "min_shopping_amount": "100.00",
      "currency": "TRY",
      "usage_count": 0,
      "max_usage_count": null,
      "total_discount": "0.00",
      "expires_at": "2026-11-30T23:59:59+03:00",
      "used_at": null,
      "order_id": null
    }
  ],
  "meta": {
    "total": 1,
    "page": 1,
    "per_page": 20,
    "last_page": 1,
    "counts": { "all": 4, "active": 1, "used": 2, "invalid": 1 }
  }
}
```

| Field                 | Description                                                                                                         |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `code`                | Coupon code; send it as `coupon_code` when ordering                                                                 |
| `status`              | `active` · `used` · `cancelled` · `expired` · `invalid`                                                             |
| `categories`          | Where the coupon applies                                                                                            |
| `discount_percentage` | Percentage off the matching products; `null` for a fixed-amount coupon, whose discount is `max_discount_amount`     |
| `max_discount_amount` | The most the coupon takes off one order                                                                             |
| `min_shopping_amount` | Smallest total of the matching products the coupon applies to                                                       |
| `currency`            | `TRY` or `EUR`; the coupon applies to orders in this currency                                                       |
| `usage_count`         | Times the coupon has been used; `max_usage_count` is how many times it can be used (`null` for a single-use coupon) |
| `total_discount`      | Total discount the coupon has given so far                                                                          |
| `used_at`, `order_id` | When a spent coupon was used and on which order                                                                     |

---

### Orders

#### `GET /orders`

Returns a paginated list of the authenticated user's product orders (placed through the API or the website basket), sorted newest first. Other order types, such as listing purchases and advertising, are not included.

**Query Parameters:**

| Parameter  | Type           | Default | Description    |
| ---------- | -------------- | ------- | -------------- |
| `page`     | integer (≥1)   | `1`     | Page number    |
| `per_page` | integer (1–50) | `20`    | Items per page |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "amount": "100.00",
      "currency": "TRY",
      "discount": "0.00",
      "status": "completed",
      "payment": "paid",
      "created_at": "2024-06-15T10:30:00+03:00",
      "completed_at": "2024-06-15T10:30:05+03:00",
      "cancelled_at": null
    }
  ],
  "meta": { "total": 2, "page": 1, "per_page": 20, "last_page": 1 }
}
```

`amount` is the total charged, after the coupon `discount`.

**`status` values:** `processing` · `completed` · `cancelled` · `unknown`

**`payment` values:** `pending` · `paid` · `refunded` · `unknown`

---

#### `POST /orders`

Places a new order using the authenticated user's account balance.

**Rules:**

- Each product is identified by its `ref` string (from `GET /products`).
- `amount` must be between **1 and 49** per line item.
- Maximum **20 distinct products** per request.
- Duplicate `ref` values in the same request are rejected — combine quantities into a single entry instead.
- Top-up products (`is_topup: true`) must use `amount: 1`.
- Top-up products require a `topup_inputs` object whose keys match the product's `topup_inputs` definitions.
- **Coupons:** send a coupon's `code` (from `GET /coupons`) as `coupon_code` to use that coupon. Without `coupon_code`, the active coupon with the largest discount for the ordered products is applied automatically, exactly as on the website basket. The applied coupon is returned as `coupon_code`.
- The full order amount, less the coupon discount, is deducted from the user's balance atomically; digital goods are delivered to the account automatically.

**Query Parameters:**

| Parameter | Type         | Description                                                                                                                                 |
| --------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `lang`    | `tr` \| `en` | Language of the `ORDER_FAILED` messages and of the product names stored on the order. Defaults to the account language (`tr` when Turkish). |

**Request Body:**

```json
{
  "products": [
    { "ref": "steam-50-try", "amount": 2 },
    { "ref": "pubg-mobile-60uc", "amount": 5 }
  ]
}
```

With a chosen coupon:

```json
{
  "products": [{ "ref": "steam-50-try", "amount": 2 }],
  "coupon_code": "K7Q2XPA"
}
```

Top-up example:

```json
{
  "products": [
    {
      "ref": "valorant-vp-1050",
      "amount": 1,
      "topup_inputs": {
        "riot_id": "Player#TR1"
      }
    }
  ]
}
```

**Response (`201 Created`):**

```json
{
  "success": true,
  "data": {
    "order_id": 456,
    "amount": "90.00",
    "discount": "10.00",
    "coupon_code": "K7Q2XPA",
    "currency": "TRY"
  }
}
```

`amount` is what was charged, after the `discount`; `coupon_code` is the coupon the order used, `null` if none applied.

**Errors:**

`code` is the stable part of an error; `error` is a human-readable message that may be reworded. `ORDER_FAILED` messages follow the `lang` parameter, all other messages are always English. The messages below are the English ones.

| Condition                                                                                | HTTP | `code`              | `error`                                                                                                              |
| ---------------------------------------------------------------------------------------- | ---- | ------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Body larger than 64 KB                                                                   | 413  | `PAYLOAD_TOO_LARGE` | `Request body too large.`                                                                                            |
| `products` missing, empty or not a list                                                  | 422  | `VALIDATION_ERROR`  | `products array is required.`                                                                                        |
| More than 20 products                                                                    | 422  | `VALIDATION_ERROR`  | `Maximum 20 products per order.`                                                                                     |
| An entry without a `ref`                                                                 | 422  | `VALIDATION_ERROR`  | `Each product must have a ref.`                                                                                      |
| The same `ref` twice                                                                     | 422  | `VALIDATION_ERROR`  | `Duplicate product refs are not allowed. Combine amounts into a single entry per ref.`                               |
| `ref` missing or `amount` outside 1–49                                                   | 422  | `VALIDATION_ERROR`  | `Invalid product entry: ref required, amount must be 1-49.`                                                          |
| `ref` unknown or inactive                                                                | 422  | `NOT_FOUND`         | `Product not found: {ref}`                                                                                           |
| Top-up product without `topup_inputs`                                                    | 422  | `VALIDATION_ERROR`  | `topup_inputs is required for top-up product: {ref}`                                                                 |
| A `topup_inputs` value that is not a string or number                                    | 422  | `VALIDATION_ERROR`  | `topup_inputs values must be strings or numbers: {ref}`                                                              |
| `coupon_code` empty or not a string                                                      | 422  | `VALIDATION_ERROR`  | `coupon_code must be a non-empty string.`                                                                            |
| `coupon_code` is not one of your coupons                                                 | 422  | `NOT_FOUND`         | `Coupon not found: {code}`                                                                                           |
| Account has no purchase permission                                                       | 422  | `ORDER_FAILED`      | `You do not have permission to make purchases.`                                                                      |
| Top-up product with `amount` above 1                                                     | 422  | `ORDER_FAILED`      | `Only 1 unit of each top-up product can be ordered at a time.`                                                       |
| Top-up input missing                                                                     | 422  | `ORDER_FAILED`      | `Missing required field: '{key}'.`                                                                                   |
| Top-up TEXT input too short or too long                                                  | 422  | `ORDER_FAILED`      | `Field '{key}' is shorter than minimum length.` / `Field '{key}' exceeds maximum length.`                            |
| Top-up NUMBER input not a number, below or above its limits                              | 422  | `ORDER_FAILED`      | `Field '{key}' must be a number.` / `Field '{key}' is below minimum value.` / `Field '{key}' exceeds maximum value.` |
| Top-up SELECT input not one of the options                                               | 422  | `ORDER_FAILED`      | `Invalid option for field '{key}'.`                                                                                  |
| Insufficient stock                                                                       | 422  | `ORDER_FAILED`      | `Not enough stock for {name}. Available: {n}`                                                                        |
| Turkish account whose phone number is not verified (orders in TRY need a verified phone) | 422  | `ORDER_FAILED`      | `Currency mismatch.`                                                                                                 |
| Insufficient balance                                                                     | 422  | `ORDER_FAILED`      | `Insufficient balance.` (with `redirect`, `balance_needed`, `currency`)                                              |
| Order could not be saved or paid                                                         | 422  | `ORDER_FAILED`      | `Order could not be created.` / `Payment failed.`                                                                    |

When the chosen `coupon_code` cannot be used, the order is not placed and `error` says why (`ORDER_FAILED`):

| Reason                             | `error`                                                                                    |
| ---------------------------------- | ------------------------------------------------------------------------------------------ |
| Already used                       | `This coupon has already been used.`                                                       |
| Expired                            | `This coupon has expired.`                                                                 |
| Cancelled                          | `This coupon has been cancelled.`                                                          |
| Not usable for another reason      | `This coupon can no longer be used.`                                                       |
| Not valid for the ordered products | `This coupon does not apply to the products in your order. It is valid for: {categories}.` |
| Coupon is in another currency      | `This coupon is only valid for orders in {currency}.`                                      |
| Ordered products below its minimum | `To use this coupon, the products it covers must total at least {amount}.`                 |
| Any other case                     | `This coupon cannot be applied to this order.`                                             |

The insufficient balance error also includes: `redirect` (URL to the top-up page), `balance_needed` (minimum amount to add, rounded up to the nearest integer), `currency` (lowercase).

---

#### `GET /order/{id}`

Returns full details for a single order, including its products and any delivered digital goods.

**Path Parameters:**

| Parameter | Type         | Description |
| --------- | ------------ | ----------- |
| `id`      | integer (≥1) | Order ID    |

**Delivery format:**

The `deliveries` array contains the digital goods delivered for this order. The shape of each entry depends on the product type:

_Account products_ (game accounts, subscriptions, etc.):

```json
{
  "product_ref": "netflix-1m-tr",
  "username": "user@example.com",
  "password": "secret123"
}
```

_E-pin / Code products_ (Steam cards, gift codes, etc.):

```json
{
  "product_ref": "steam-50-try",
  "code": "ABCD-EFGH-IJKL-MNOP"
}
```

_Top-up products_ expose the stored top-up input snapshot:

```json
{
  "product_ref": "valorant-vp-tr-375",
  "topup_values": {
    "player_id": "12345678",
    "region": "tr"
  }
}
```

> `deliveries` is empty while the order is still `processing`. Top-up entries are the exception: they echo the inputs the order was placed with, so they are present from the start; `products[].delivered` tells whether the top-up has been loaded. `delivered` below `amount` means a partial delivery, or units that were removed and refunded. Delivery entries are generated from the same order snapshot used by the order details page.
