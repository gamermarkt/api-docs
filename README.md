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
2. Navigate to **Dashboard → API Access** (`/dashboard/api-access`).
3. Complete email verification and click **Generate API Key**.
4. Copy the key immediately — it will be partially masked afterwards.

> **Note:** Only one key exists per account. You can regenerate it at any time (the old key becomes invalid immediately) or toggle it active/inactive. A minimum purchase history may be required before key generation is unlocked.

---

## Currencies

Your account currency is determined by your registered country:

- **Turkish accounts** → `TRY`
- **All other accounts** → `EUR`

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

| Field             | Description                                             |
| ----------------- | ------------------------------------------------------- |
| `balance`         | Spendable balance                                       |
| `blocked_balance` | Balance reserved for pending orders — not yet spendable |
| `currency`        | `TRY` or `EUR`                                          |

---

### Products

#### `GET /products`

Returns active, purchasable standard products. To include top-up products as well, pass `list_topups=1`.

**Query Parameters:**

| Parameter     | Type            | Description                                                                                               |
| ------------- | --------------- | --------------------------------------------------------------------------------------------------------- |
| `lang`        | `tr` \| `en`    | Language for `name` and `category_name` fields. Defaults to the authenticated user's account language.    |
| `per_page`    | integer (1–100) | Items per page. **When omitted, all products are returned in a single response without a `meta` object.** |
| `page`        | integer (≥1)    | Page number. Only used when `per_page` is also provided. Default: `1`                                     |
| `category_id` | integer         | Filter by category ID. Omit to return all categories.                                                     |
| `list_topups` | `0` \| `1`      | When set to `1`, top-up products are included alongside standard products. Default: `0`                   |

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

| Field          | Description                                                              |
| -------------- | ------------------------------------------------------------------------ |
| `ref`          | Unique product reference — use this value when creating orders           |
| `is_topup`     | `true` for products that require buyer input before fulfilment           |
| `topup_inputs` | Input definition array for top-up products; `null` for standard products |
| `price`        | Unit price in the user's account currency                                |
| `stock`        | Units currently available; `0` means out of stock                        |

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

### Orders

#### `GET /orders`

Returns a paginated list of all orders placed by the authenticated user, sorted newest first.

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
- The full order amount is deducted from the user's balance atomically; digital goods are delivered to the account automatically.

**Request Body:**

```json
{
  "products": [
    { "ref": "steam-50-try", "amount": 2 },
    { "ref": "pubg-mobile-60uc", "amount": 5 }
  ]
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
    "amount": "100.00",
    "discount": "0.00",
    "currency": "TRY"
  }
}
```

**`ORDER_FAILED` reasons:**

| Condition                                 | Error message                                                                |
| ----------------------------------------- | ---------------------------------------------------------------------------- |
| Account has no purchase permission        | `You do not have permission to make purchases.`                              |
| Product ref not found or inactive         | `Product not found: {ref}`                                                   |
| Insufficient stock                        | `Not enough stock for {name}. Available: {n}`                                |
| Insufficient balance                      | `Insufficient balance.` (+ `redirect`, `balance_needed`, `currency` fields)  |
| Currency mismatch                         | `Currency mismatch.`                                                         |
| Steam/Google/Apple codes for non-TR users | `Steam Wallet Codes, Google Play Codes, Apple iTunes Codes are unavailable.` |
| Top-up product amount > 1                 | `Top-up products can only be ordered 1 at a time: {ref}`                     |
| Top-up product missing inputs             | `topup_inputs is required for top-up product: {ref}`                         |
| Missing required topup field              | `Missing required topup input '{key}' for product: {ref}`                    |
| Invalid topup SELECT value                | `Invalid option for topup input '{key}' on product: {ref}`                   |
| Topup TEXT input too long                 | `Topup input '{key}' exceeds maximum length of {max} for product: {ref}`     |
| Topup NUMBER input not numeric            | `Topup input '{key}' must be a number for product: {ref}`                    |
| Topup NUMBER below minimum                | `Topup input '{key}' must be at least {min} for product: {ref}`              |
| Topup NUMBER above maximum                | `Topup input '{key}' must be at most {max} for product: {ref}`               |

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

> `deliveries` is empty while the order is still `processing`. Delivery entries are generated from the same order snapshot used by the order details page.
