<?php

namespace Modules\Payment\Gateways;

use Exception;
use Illuminate\Http\Request;
use Modules\Order\Entities\Order;
use Modules\Payment\GatewayInterface;
use Modules\Payment\Libraries\Moniq\MoniqService;
use Modules\Payment\Responses\MoniqResponse;

class Moniq implements GatewayInterface
{
    public $label;
    public $description;

    public function __construct()
    {
        $this->label = setting('moniq_label');
        $this->description = setting('moniq_description');
    }

    /**
     * @throws Exception
     */
    public function purchase(Order $order, Request $request)
    {
        $service = $this->getService();
        $response = $service->createCharge(
            $order,
            $this->getRedirectUrl($order),
            $this->getWebhookUrl()
        );

        // Store API order ID in order for later verification
        $moniqOrderId = $response['result']['order']['id'] ?? null;
        if ($moniqOrderId) {
            $existingNotes = json_decode($order->note ?? '{}', true);
            $existingNotes['moniq_order_id'] = $moniqOrderId;
            $order->update(['note' => json_encode($existingNotes)]);
        }

        return new MoniqResponse($order, $response);
    }

    public function complete(Order $order)
    {
        return new MoniqResponse($order, []);
    }

    private function getService(): MoniqService
    {
        $config = [
            'public_key' => setting('moniq_public_key') ?? '',
            'api_secret' => setting('moniq_api_secret') ?? '',
        ];

        return new MoniqService($config);
    }

    private function getRedirectUrl(Order $order): string
    {
        return route('checkout.complete.store', ['orderId' => $order->id, 'paymentMethod' => 'moniq']);
    }

    private function getWebhookUrl(): string
    {
        return route('moniq.webhook');
    }
}
