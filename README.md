# ZarPay PHP SDK

Official PHP SDK for the [ZarPay](https://zarpay.pk) payment gateway.

## Installation

```bash
composer require zarpay/zarpay-php
```

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

$zarpay = new \ZarPay\ZarPay('sk_sandbox_xxxxxxxxxxxxx');

$payment = $zarpay->payments->create([
    'merchant_order_id' => 'ORD-123',
    'amount' => 1500,
    'channel_id' => 1,
    'customer_phone' => '03001234567',
]);

echo $payment['data']['status']; // 'completed' | 'processing' | 'failed'
```

## Usage

### List Available Channels

```php
$channels = $zarpay->channels->list();

foreach ($channels['data']['channels'] as $ch) {
    echo "{$ch['id']}: {$ch['wallet_type']}\n";
}
```

### Create a Payment

```php
$payment = $zarpay->payments->create([
    'merchant_order_id' => 'ORD-456',
    'amount' => 2500,
    'channel_id' => 1,
    'customer_phone' => '03001234567',
    'metadata' => ['customer_name' => 'Ahmed Khan'],
    'idempotency_key' => 'unique-key-456',
]);

if ($payment['success']) {
    echo "Payment completed: " . $payment['data']['zarpay_id'];
} else {
    echo "Payment failed: " . $payment['data']['failure_reason'];
}
```

### Get Payment Status

```php
// By ZarPay ID
$payment = $zarpay->payments->get('ZP_abc123def456');

// By your order ID
$payment = $zarpay->payments->getByOrderId('ORD-456');
```

### Verify Webhooks

```php
// In your webhook handler
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_ZARPAY_SIGNATURE'] ?? '';

try {
    $event = \ZarPay\ZarPay::verifyWebhook(
        $rawBody,
        $signature,
        'whsec_your_webhook_secret'
    );

    switch ($event['event']) {
        case 'payment.completed':
            // Fulfill the order
            break;
        case 'payment.failed':
            // Notify customer
            break;
    }

    http_response_code(200);
} catch (\Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}
```

### Error Handling

```php
use ZarPay\ZarPayAPIError;

try {
    $payment = $zarpay->payments->create([...]);
} catch (ZarPayAPIError $e) {
    echo $e->statusCode;  // 400, 401, 409, etc.
    echo $e->error;       // Human-readable error
}
```

### Configuration

```php
$zarpay = new \ZarPay\ZarPay('sk_sandbox_xxx', [
    'base_url' => 'http://localhost:3000/api/v1',  // local development
    'timeout' => 60,                                // seconds
]);
```

## Requirements

- PHP 7.4+
- ext-curl
- ext-json

## License

MIT
