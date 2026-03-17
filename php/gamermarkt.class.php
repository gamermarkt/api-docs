<?php

declare(strict_types=1);

/**
 * GamerMarkt API Client
 *
 * PHP 8.4+ (backward compatible with PHP 7.4)
 *
 * @see https://www.gamermarkt.com/api/v1
 */

// ---------------------------------------------------------------------------
// Exception
// ---------------------------------------------------------------------------

class GamerMarktException extends RuntimeException
{
    /** @var string */
    private $errorCode;

    /** @var array<string, mixed> */
    private $payload;

    /**
     * @param array<string, mixed> $payload Full error payload returned by the API.
     *                                       For ORDER_FAILED / insufficient balance this
     *                                       includes `redirect`, `balance_needed`, `currency`.
     */
    public function __construct(
        string $message,
        string $errorCode = '',
        int    $httpStatus = 0,
        array  $payload    = []
    ) {
        parent::__construct($message, $httpStatus);
        $this->errorCode = $errorCode;
        $this->payload   = $payload;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** HTTP status code of the failed response. */
    public function getHttpStatus(): int
    {
        return $this->getCode();
    }

    /**
     * Full error payload from the API.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}

// ---------------------------------------------------------------------------
// Client
// ---------------------------------------------------------------------------

class GamerMarkt
{
    private const BASE_URL = 'https://www.gamermarkt.com/api/v1';
    private const TIMEOUT  = 30;

    /** @var string */
    private $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // ── Account ─────────────────────────────────────────────────────────────

    /**
     * Returns the authenticated user's available and blocked balance.
     *
     * @return array{balance: string, blocked_balance: string, currency: string}
     * @throws GamerMarktException
     * @throws RuntimeException
     */
    public function getAccount(): array
    {
        return $this->request('GET', '/account')['data'];
    }

    // ── Products ────────────────────────────────────────────────────────────

    /**
     * Returns active, purchasable products.
     *
     * When $perPage is null all products are returned in a single response
     * (no `meta` key). When $perPage is set the response includes a `meta`
     * pagination object.
     *
     * @param  string|null $lang       Language for name/category_name: 'tr' or 'en'.
     *                                 Defaults to the user's account language.
     * @param  int|null    $perPage    Items per page (1–100).
     *                                 Null → all products, no pagination.
     * @param  int         $page       Page number (≥1). Only used with $perPage.
     * @param  int|null    $categoryId Filter by category ID.
     * @return array{success: bool, data: array<int, array<string, mixed>>, meta?: array}
     * @throws GamerMarktException
     * @throws RuntimeException
     */
    public function getProducts(
        ?string $lang       = null,
        ?int    $perPage    = null,
        int     $page       = 1,
        ?int    $categoryId = null
    ): array {
        $query = [];

        if ($lang       !== null) $query['lang']        = $lang;
        if ($perPage    !== null) $query['per_page']    = $perPage;
        if ($page       !== 1)   $query['page']        = $page;
        if ($categoryId !== null) $query['category_id'] = $categoryId;

        return $this->request('GET', '/products', $query);
    }

    // ── Orders ──────────────────────────────────────────────────────────────

    /**
     * Returns a paginated list of orders, sorted newest first.
     *
     * @param  int $page    Page number (≥1).
     * @param  int $perPage Items per page (1–50).
     * @return array{success: bool, data: array, meta: array}
     * @throws GamerMarktException
     * @throws RuntimeException
     */
    public function getOrders(int $page = 1, int $perPage = 20): array
    {
        return $this->request('GET', '/orders', [
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Places a new order using the account balance.
     *
     * Each element of $products must be an associative array with:
     *   - 'ref'    (string) — product reference from getProducts()
     *   - 'amount' (int)    — quantity, 1–49
     *
     * Rules enforced locally before sending the request:
     *   - At least 1 product, at most 20.
     *   - No duplicate refs.
     *   - Amount between 1 and 49.
     *
     * @param  array<int, array{ref: string, amount: int}> $products
     * @return array{order_id: int, amount: string, discount: string, currency: string}
     * @throws InvalidArgumentException   on local validation failure
     * @throws GamerMarktException        on API error
     * @throws RuntimeException
     */
    public function createOrder(array $products): array
    {
        $this->validateOrderProducts($products);

        return $this->request('POST', '/orders', [], ['products' => $products])['data'];
    }

    /**
     * Returns full details for a single order, including deliveries.
     *
     * Each entry in `deliveries` is either:
     *   - Account product: {product_ref, username, password, delivered_at}
     *   - E-pin / code:    {product_ref, code, delivered_at}
     *
     * `deliveries` is empty while status is 'processing'.
     *
     * @return array<string, mixed>
     * @throws GamerMarktException
     * @throws RuntimeException
     */
    public function getOrder(int $id): array
    {
        return $this->request('GET', '/order/' . $id)['data'];
    }

    // ── Validation ──────────────────────────────────────────────────────────

    /**
     * @param  array<int, array{ref: string, amount: int}> $products
     * @throws InvalidArgumentException
     */
    private function validateOrderProducts(array $products): void
    {
        if (empty($products)) {
            throw new InvalidArgumentException('Products array must not be empty.');
        }

        if (count($products) > 20) {
            throw new InvalidArgumentException('Maximum 20 distinct products per request.');
        }

        $seenRefs = [];

        foreach ($products as $i => $item) {
            if (empty($item['ref']) || !is_string($item['ref'])) {
                throw new InvalidArgumentException(
                    "Product at index {$i}: 'ref' must be a non-empty string."
                );
            }

            if (
                !isset($item['amount']) ||
                !is_int($item['amount'])  ||
                $item['amount'] < 1       ||
                $item['amount'] > 49
            ) {
                throw new InvalidArgumentException(
                    "Product at index {$i}: 'amount' must be an integer between 1 and 49."
                );
            }

            if (isset($seenRefs[$item['ref']])) {
                throw new InvalidArgumentException(
                    "Duplicate product ref '{$item['ref']}'. Combine quantities into a single entry."
                );
            }

            $seenRefs[$item['ref']] = true;
        }
    }

    // ── HTTP ────────────────────────────────────────────────────────────────

    /**
     * Executes an API request and returns the parsed JSON response.
     *
     * @param  string               $method HTTP method (GET, POST, …)
     * @param  string               $path   API path, e.g. '/account'
     * @param  array<string, mixed> $query  Query string parameters
     * @param  array<string, mixed>|null $body JSON request body
     * @return array<string, mixed>
     * @throws GamerMarktException  when the API returns success=false
     * @throws RuntimeException     on cURL failure or unparseable response
     */
    private function request(
        string  $method,
        string  $path,
        array   $query = [],
        ?array  $body  = null
    ): array {
        $url = self::BASE_URL . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL              => $url,
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_TIMEOUT          => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST    => $method,
            CURLOPT_FOLLOWLOCATION   => true,
            CURLOPT_UNRESTRICTED_AUTH => true,
            CURLOPT_HTTPHEADER       => [
                'X-Api-Key: ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($body));
        }

        $response   = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);

        if ($response === false) {
            throw new RuntimeException('cURL error: ' . $curlError);
        }

        $data = json_decode((string) $response, true);

        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON response from API (HTTP ' . $httpStatus . ').');
        }

        if (isset($data['success']) && $data['success'] === false) {
            throw new GamerMarktException(
                (string) ($data['error'] ?? 'Unknown API error.'),
                (string) ($data['code']  ?? ''),
                $httpStatus,
                $data
            );
        }

        return $data;
    }
}
