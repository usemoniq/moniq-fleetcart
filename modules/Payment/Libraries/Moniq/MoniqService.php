<?php

namespace Modules\Payment\Libraries\Moniq;

use Exception;
use Modules\Order\Entities\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MoniqService
{
    private const BASE_URL = 'https://em-api-prod.everydaymoney.app';
    private const TOKEN_CACHE_KEY = 'moniq_jwt_token';
    private const TOKEN_TTL = 3000; // ~50 minutes

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getToken(): string
    {
        if (Cache::has(self::TOKEN_CACHE_KEY)) {
            return Cache::get(self::TOKEN_CACHE_KEY);
        }

        $authString = base64_encode($this->config['public_key'] . ':' . $this->config['api_secret']);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Api-Key' => $this->config['public_key'],
            'Authorization' => 'Basic ' . $authString,
        ])->post(self::BASE_URL . '/auth/business/token');

        $data = $response->json();

        if ($data['isError'] ?? true) {
            throw new Exception('Failed to obtain Moniq token');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $data['result'], self::TOKEN_TTL);

        return $data['result'];
    }

    public function createCharge(Order $order, string $redirectUrl, string $webhookUrl = null): array
    {
        $token = $this->getToken();

        $orderLines = [];

        foreach ($order->products as $orderProduct) {
            $orderLines[] = [
                'itemName' => $orderProduct->product?->name ?? 'Item',
                'quantity' => (int) $orderProduct->qty,
                'amount' => round(
                    (float) $orderProduct->unit_price->amount(),
                    2
                ),
            ];
        }

        /*
     |------------------------------------------------------------
     | Other fields available on $orderProduct (order_products)
     |------------------------------------------------------------
     | $orderProduct->id
     | $orderProduct->order_id
     | $orderProduct->product_id
     | $orderProduct->product_variant_id
     | $orderProduct->unit_price        // decimal string
     | $orderProduct->qty               // quantity ordered
     | $orderProduct->line_total        // unit_price * qty
     |
     |------------------------------------------------------------
     | Related models you already have loaded
     |------------------------------------------------------------
     | $orderProduct->product
     |   - id
     |   - sku
     |   - price
     |   - selling_price
     |   - special_price
     |   - is_virtual
     |   - is_active
     |   - name            // translated attribute
     |
     | $orderProduct->product_variant (nullable)
     |   - id
     |   - name
     |   - sku
     |   - price
     |
     | $orderProduct->options      // Collection
     | $orderProduct->variations   // Collection
     |
     |------------------------------------------------------------
     | Useful derived values
     |------------------------------------------------------------
     | ($orderProduct->unit_price * $orderProduct->qty) === line_total
     |------------------------------------------------------------
     */

        $payload = [
            // Order currency (orders.currency)
            'currency' => $order->currency,

            // Customer contact
            'email' => $order->customer_email,
            'phone' => $order->customer_phone ?? '',

            // Customer name (derived, not a column)
            'customerName' => trim(
                ($order->customer_first_name ?? '') . ' ' . ($order->customer_last_name ?? '')
            ),

            // Display text for the transaction
            'narration' => 'Order #' . $order->id . ' from ' . (setting('store_name') ?? 'Store'),

            // Unique transaction references
            'transactionRef' => 'FC-' . $order->id . '-' . time(),
            'referenceKey' => 'order_' . $order->id,

            // Redirect after payment
            'redirectUrl' => $redirectUrl,

            // Line items (built from OrderProduct)
            'orderLines' => $orderLines,

            // Arbitrary metadata (string-safe values)
            'metadata' => [
                'order_id'   => (string) $order->id,
                'store_name' => (string) (setting('store_name') ?? 'Store'),
                'order_total'=> (string) $order->total,
            ],
        ];


        // Add customer address if available
        if ($order->billing_address_1) {
            $payload['customerAddress'] = [
                'line1' => $order->billing_address_1,
                'line2' => $order->billing_address_2 ?? '',
                'city' => $order->billing_city ?? '',
                'state' => $order->billing_state ?? '',
                'postalCode' => $order->billing_zip ?? '',
                'country' => $order->billing_country ?? '',
            ];
        }

        // Add webhook URL if provided (for server-to-server notifications)
        if ($webhookUrl) {
            $payload['webhookUrl'] = $webhookUrl;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->post(self::BASE_URL . '/payment/checkout/api-charge-order', $payload);

        $data = $response->json();

        if ($data['isError'] ?? true) {
            throw new Exception($data['message'] ?? 'Failed to create Moniq charge');
        }

        return $data;
    }

    public function verifyPayment(string $apiOrderId): array
    {
        $token = $this->getToken();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->get(self::BASE_URL . '/business/order/' . $apiOrderId);

        $data = $response->json();

        if ($data['isError'] ?? true) {
            throw new Exception($data['message'] ?? 'Failed to verify Moniq payment');
        }

        return $data;
    }
}
