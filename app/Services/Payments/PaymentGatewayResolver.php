<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\AbhiPayGateway;
use InvalidArgumentException;

class PaymentGatewayResolver
{
    public function resolve(string $gatewayCode): PaymentGatewayInterface
    {
        return match ($gatewayCode) {
            PaymentGateway::CODE_ABHIPAY => app(AbhiPayGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$gatewayCode}]."),
        };
    }
}
