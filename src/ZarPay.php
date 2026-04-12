<?php
/**
 * ZarPay PHP SDK v1.0.1
 *
 * Official SDK for integrating with the ZarPay payment gateway.
 */

namespace ZarPay;

class ZarPayAPIError extends \Exception
{
    public int $statusCode;
    public string $error;
    public array $body;

    public function __construct(int $statusCode, string $error, array $body)
    {
        $this->statusCode = $statusCode;
        $this->error = $error;
        $this->body = $body;
        parent::__construct("ZarPay API error ({$statusCode}): {$error}");
    }
}

class Payments
{
    private ZarPay $client;

    public function __construct(ZarPay $client)
    {
        $this->client = $client;
    }

    /** Create a new payment. */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/payments', $params);
    }

    /** Get a payment by ZarPay ID. */
    public function get(string $zarpayId): array
    {
        return $this->client->request('GET', '/payments/' . urlencode($zarpayId));
    }

    /** Get a payment by your merchant order ID. */
    public function getByOrderId(string $orderId): array
    {
        return $this->client->request('GET', '/payments/by-order/' . urlencode($orderId));
    }
}

class Refunds
{
    private ZarPay $client;

    public function __construct(ZarPay $client)
    {
        $this->client = $client;
    }

    /**
     * Initiate a refund on a completed payment.
     *
     * @param array $params {
     *     @type string $zarpay_id  ZarPay payment ID to refund
     *     @type float  $amount     Refund amount in PKR
     *     @type string $reason     Optional reason
     * }
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/refunds', $params);
    }
}

class Balance
{
    private ZarPay $client;

    public function __construct(ZarPay $client)
    {
        $this->client = $client;
    }

    /** Get the project's financial balance summary. */
    public function get(): array
    {
        return $this->client->request('GET', '/balance');
    }
}

class Settlements
{
    private ZarPay $client;

    public function __construct(ZarPay $client)
    {
        $this->client = $client;
    }

    /**
     * List settlements for the project.
     *
     * @param array $params Optional: status, page, limit
     */
    public function list(array $params = []): array
    {
        $query = http_build_query(array_filter($params));
        $path = '/settlements' . ($query ? "?{$query}" : '');
        return $this->client->request('GET', $path);
    }
}

class Channels
{
    private ZarPay $client;

    public function __construct(ZarPay $client)
    {
        $this->client = $client;
    }

    /** List available payment channels. */
    public function list(): array
    {
        return $this->client->request('GET', '/channels');
    }
}

class ZarPay
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public Payments $payments;
    public Refunds $refunds;
    public Balance $balance;
    public Settlements $settlements;
    public Channels $channels;

    private const DEFAULT_BASE_URL = 'https://zarpay.pk/api/v1';
    private const DEFAULT_TIMEOUT = 120;
    private const SDK_VERSION = '1.0.1';

    public function __construct(string $apiKey, array $config = [])
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('ZarPay API key is required');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($config['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT;

        $this->payments = new Payments($this);
        $this->refunds = new Refunds($this);
        $this->balance = new Balance($this);
        $this->settlements = new Settlements($this);
        $this->channels = new Channels($this);
    }

    /** @internal */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'User-Agent: zarpay-php/' . self::SDK_VERSION,
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new ZarPayAPIError(0, "cURL error: {$curlError}", []);
        }

        $data = json_decode($responseBody, true);

        if ($httpCode >= 400 && isset($data['error'])) {
            throw new ZarPayAPIError($httpCode, $data['error'], $data);
        }

        return $data;
    }

    /**
     * Verify a ZarPay webhook signature.
     *
     * @param string $rawBody         The raw request body
     * @param string $signatureHeader  The X-ZarPay-Signature header value
     * @param string $secret           Your webhook signing secret (whsec_...)
     * @param int    $toleranceSec     Max age in seconds (default: 300)
     * @return array The parsed webhook payload
     * @throws \Exception If signature is invalid or timestamp is too old
     */
    public static function verifyWebhook(
        string $rawBody,
        string $signatureHeader,
        string $secret,
        int $toleranceSec = 300
    ): array {
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        $t = $parts['t'] ?? '';
        $v1 = $parts['v1'] ?? '';

        if (empty($t) || empty($v1)) {
            throw new \Exception('Invalid X-ZarPay-Signature header format');
        }

        if (abs(time() - (int)$t) > $toleranceSec) {
            throw new \Exception('Webhook timestamp too old — possible replay attack');
        }

        $expected = hash_hmac('sha256', "{$t}.{$rawBody}", $secret);

        if (!hash_equals($expected, $v1)) {
            throw new \Exception('Invalid webhook signature');
        }

        return json_decode($rawBody, true);
    }
}
