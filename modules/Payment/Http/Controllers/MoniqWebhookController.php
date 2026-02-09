<?php

namespace Modules\Payment\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Order\Entities\Order;
use Modules\Checkout\Events\OrderPlaced;
use Modules\Payment\Libraries\Moniq\MoniqService;

class MoniqWebhookController
{
    /**
     * Handle Moniq webhook notification.
     *
     * Webhook URL: https://yoursite.com/moniq/webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('Moniq webhook received', ['payload' => $request->all()]);

        try {
            // Extract order ID from webhook payload
            // Moniq may send: orderId, order_id, metadata.order_id, or referenceKey
            $orderId = $this->extractOrderId($request);

            if (!$orderId) {
                Log::warning('Moniq webhook: Could not extract order ID', ['payload' => $request->all()]);
                return response()->json(['status' => 'error', 'message' => 'Order ID not found'], 400);
            }

            $order = Order::find($orderId);

            if (!$order) {
                Log::warning('Moniq webhook: Order not found', ['order_id' => $orderId]);
                return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
            }

            // Check if order is already completed
            if ($order->status === 'completed') {
                Log::info('Moniq webhook: Order already completed', ['order_id' => $orderId]);
                return response()->json(['status' => 'success', 'message' => 'Order already processed']);
            }

            // Get Moniq API order ID from order notes
            $notes = json_decode($order->note ?? '{}', true);
            $apiOrderId = $notes['moniq_order_id'] ?? null;

            if (!$apiOrderId) {
                // Try to get from webhook payload
                $apiOrderId = $request->input('order.id') ?? $request->input('orderId') ?? $request->input('id');
            }

            if (!$apiOrderId) {
                Log::warning('Moniq webhook: API order ID not found', ['order_id' => $orderId]);
                return response()->json(['status' => 'error', 'message' => 'API order ID not found'], 400);
            }

            // Verify payment with Moniq API (don't trust webhook data blindly)
            $moniqService = $this->getMoniqService();
            $paymentResponse = $moniqService->verifyPayment($apiOrderId);

            $chargeStatus = strtolower($paymentResponse['result']['charges'][0]['status'] ?? '');

            if (!in_array($chargeStatus, ['completed', 'paid', 'successful', 'succeeded'])) {
                Log::info('Moniq webhook: Payment not completed', [
                    'order_id' => $orderId,
                    'status' => $chargeStatus
                ]);
                return response()->json(['status' => 'pending', 'message' => 'Payment not completed yet']);
            }

            // Payment verified - complete the order
            $order->storeTransaction($paymentResponse);

            event(new OrderPlaced($order));

            Log::info('Moniq webhook: Order completed successfully', ['order_id' => $orderId]);

            return response()->json(['status' => 'success', 'message' => 'Order completed']);

        } catch (Exception $e) {
            Log::error('Moniq webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Extract FleetCart order ID from webhook payload.
     *
     * @param Request $request
     * @return int|null
     */
    private function extractOrderId(Request $request): ?int
    {
        // Try various possible locations for the order ID
        $possibleKeys = [
            'metadata.order_id',
            'order.metadata.order_id',
            'referenceKey',           // We send 'order_{id}'
            'order.referenceKey',
        ];

        foreach ($possibleKeys as $key) {
            $value = data_get($request->all(), $key);
            if ($value) {
                // Handle 'order_123' format from referenceKey
                if (str_starts_with($value, 'order_')) {
                    return (int) str_replace('order_', '', $value);
                }
                if (is_numeric($value)) {
                    return (int) $value;
                }
            }
        }

        return null;
    }

    /**
     * Get configured Moniq service instance.
     *
     * @return MoniqService
     */
    private function getMoniqService(): MoniqService
    {
        $config = [
            'public_key' => setting('moniq_public_key'),
            'api_secret' => setting('moniq_api_secret'),
        ];

        return new MoniqService($config);
    }
}
