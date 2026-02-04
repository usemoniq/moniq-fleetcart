<?php

namespace Modules\Payment\Responses;

use Modules\Order\Entities\Order;
use Modules\Payment\GatewayResponse;
use Modules\Payment\HasTransactionReference;
use Modules\Payment\ShouldRedirect;

class MoniqResponse extends GatewayResponse implements ShouldRedirect, HasTransactionReference
{
    private Order $order;
    private array $clientResponse;

    public function __construct(Order $order, array $clientResponse)
    {
        $this->order = $order;
        $this->clientResponse = $clientResponse;
    }

    public function getOrderId()
    {
        return $this->order->id;
    }

    public function getRedirectUrl()
    {
        return $this->clientResponse['result']['checkoutURL'] ?? null;
    }

    public function getTransactionReference()
    {
        return $this->clientResponse['result']['order']['charges'][0]['transactionRef']
            ?? ('moniq_' . time());
    }

    public function toArray()
    {
        $data = ['orderId' => $this->getOrderId()];

        if ($this->getRedirectUrl()) {
            $data['redirectUrl'] = $this->getRedirectUrl();
        }

        return $data;
    }
}
