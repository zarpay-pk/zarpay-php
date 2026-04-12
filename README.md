# ZarPay PHP SDK

Official PHP SDK for the [ZarPay](https://zarpay.pk) payment gateway.

## Installation

```bash
composer require zarpay/zarpay-php
```

## Quick Start

```php
<?php
require 'vendor/autoload.php';

$zarpay = new \ZarPay\ZarPay('sk_sandbox_xxxxxxxxxxxxx');

$payment = $zarpay->payments->create([
    'merchant_order_id' => 'ORD-123',
    'amount' => 1500,
    'channel_id' => 1,
    'customer_phone' => '03001234567',
]);

echo $payment['data']['status'];
```

## Payments

```php
// Create a payment
$payment = $zarpay->payments->create([
    'merchant_order_id' => 'ORD-456',
    'amount' => 2500,
    'channel_id' => 1,
    'customer_phone' => '03001234567',
    'metadata' => ['customer_name' => 'Ahmed Khan'],
    'idempotency_key' => 'unique-key-456',
]);

// Get by ZarPay ID
$payment = $zarpay->payments->get('ZP_abc123def456');

// Get by your order ID
$payment = $zarpay->payments->getByOrderId('ORD-456');
```

## Refunds

```php
$refund = $zarpay->refunds->create([
    'zarpay_id' => 'ZP_abc123def456',
    'amount' => 500,
    'reason' => 'Customer requested refund',
]);

echo $refund['data']['status']; // 'pending' — requires admin approval
```

## Balance

```php
$balance = $zarpay->balance->get();

echo 'Available: ' . $balance['data']['available'];
echo 'Settled: ' . $balance['data']['settled'];
echo 'Unsettled: ' . $balance['data']['unsettled'];
echo 'Pending: ' . $balance['data']['pending'];
```

## Settlements

```php
$settlements = $zarpay->settlements->list([
    'status' => 'PAID',
    'page' => 1,
    'limit' => 10,
]);

foreach ($settlements['data']['settlements'] as $s) {
    echo "#{$s['id']}: PKR {$s['net_amount']} ({$s['status']})\n";
}
```

## Channels

```php
$channels = $zarpay->channels->list();

foreach ($channels['data']['channels'] as $ch) {
    echo "{$ch['id']}: {$ch['wallet_type']}\n";
}
```

## Verify Webhooks

```php
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
        case 'refund.completed':
            // Update order status
            break;
        case 'settlement.paid':
            // Record settlement
            break;
    }

    http_response_code(200);
} catch (\Exception $e) {
    http_response_code(400);
}
```

## Error Handling

```php
use ZarPay\ZarPayAPIError;

try {
    $payment = $zarpay->payments->create([...]);
} catch (ZarPayAPIError $e) {
    echo $e->statusCode;  // 400, 401, 409, etc.
    echo $e->error;       // Human-readable error
}
```

## Configuration

```php
$zarpay = new \ZarPay\ZarPay('sk_sandbox_xxx', [
    'base_url' => 'http://localhost:3550/api/v1',
    'timeout' => 60,
]);
```

## API Reference

| Resource | Method | Endpoint |
|----------|--------|----------|
| `$payments->create()` | POST | /payments |
| `$payments->get()` | GET | /payments/:id |
| `$payments->getByOrderId()` | GET | /payments/by-order/:id |
| `$refunds->create()` | POST | /refunds |
| `$balance->get()` | GET | /balance |
| `$settlements->list()` | GET | /settlements |
| `$channels->list()` | GET | /channels |
| `ZarPay::verifyWebhook()` | — | Verify webhook signature |

## Requirements

- PHP 7.4+
- ext-curl
- ext-json

## License

MIT
