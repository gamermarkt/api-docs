<?php

declare(strict_types=1);

require_once __DIR__ . '/gamermarkt.class.php';

$api = new GamerMarkt('your_64_char_hex_api_key_here');

// ---------------------------------------------------------------------------
// Account
// ---------------------------------------------------------------------------

// Get account balance
$account = $api->getAccount();

echo $account['balance'];          // "150.00"
echo $account['blocked_balance'];  // "10.00"
echo $account['currency'];         // "TRY" or "EUR"


// ---------------------------------------------------------------------------
// Products
// ---------------------------------------------------------------------------

// All products (no pagination), language English
$result = $api->getProducts(lang: 'en');

foreach ($result['data'] as $product) {
    echo $product['ref'];           // "steam-50-try"
    echo $product['name'];          // "Steam 50 TRY"
    echo $product['price'];         // "50.00"
    echo $product['currency'];      // "TRY"
    echo $product['stock'];         // 120
    echo $product['category_name']; // "Steam"
}

// Paginated — page 2, 20 items per page, filtered by category
$result = $api->getProducts(lang: 'en', perPage: 20, page: 2, categoryId: 5);

foreach ($result['data'] as $product) {
    echo $product['ref'] . ' — ' . $product['price'];
}

echo $result['meta']['total'];     // 200
echo $result['meta']['last_page']; // 10


// ---------------------------------------------------------------------------
// Orders — list
// ---------------------------------------------------------------------------

// First page (default per_page = 20)
$result = $api->getOrders();

foreach ($result['data'] as $order) {
    echo $order['id'];       // 123
    echo $order['amount'];   // "100.00"
    echo $order['status'];   // "completed"
    echo $order['payment'];  // "paid"
}

echo $result['meta']['total']; // total order count

// Page 3, 10 orders per page
$result = $api->getOrders(page: 3, perPage: 10);


// ---------------------------------------------------------------------------
// Orders — create
// ---------------------------------------------------------------------------

// Single product
try {
    $order = $api->createOrder([
        ['ref' => 'steam-50-try', 'amount' => 1],
    ]);

    echo $order['order_id']; // 456
    echo $order['amount'];   // "50.00"
    echo $order['currency']; // "TRY"

} catch (InvalidArgumentException $e) {
    // Local validation failed (duplicate ref, amount out of range, etc.)
    echo 'Validation: ' . $e->getMessage();
} catch (GamerMarktException $e) {
    echo 'API error [' . $e->getErrorCode() . ']: ' . $e->getMessage();

    // Insufficient balance — extra fields available in the payload
    if ($e->getErrorCode() === 'ORDER_FAILED') {
        $payload = $e->getPayload();
        if (isset($payload['redirect'])) {
            echo 'Top-up needed: ' . $payload['balance_needed'] . ' ' . strtoupper($payload['currency']);
            echo 'Top-up URL: '    . $payload['redirect'];
        }
    }
}

// Multiple products
try {
    $order = $api->createOrder([
        ['ref' => 'steam-50-try',     'amount' => 2],
        ['ref' => 'pubg-mobile-60uc', 'amount' => 5],
    ]);

    echo 'Order #' . $order['order_id'] . ' placed — ' . $order['amount'] . ' ' . $order['currency'];
} catch (GamerMarktException $e) {
    echo 'API error [' . $e->getErrorCode() . ']: ' . $e->getMessage();
}


// ---------------------------------------------------------------------------
// Orders — detail
// ---------------------------------------------------------------------------

$order = $api->getOrder(123);

echo $order['id'];     // 123
echo $order['status']; // "completed"

// Iterate delivered items
foreach ($order['deliveries'] as $delivery) {
    if (isset($delivery['code'])) {
        // E-pin / gift card
        echo $delivery['product_ref'] . ': ' . $delivery['code'];
    } else {
        // Account credential
        echo $delivery['product_ref'] . ': ' . $delivery['username'] . ' / ' . $delivery['password'];
    }
}
