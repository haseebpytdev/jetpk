<?php

namespace App\Services\Payments\DTO;

use App\Models\PaymentTransaction;

readonly class PaymentGatewayCallbackResult
{
    /**
     * @param  array<string, mixed>|null  $safeCallbackPayload
     */
    public function __construct(
        public ?PaymentTransaction $transaction = null,
        public ?string $clientTransactionId = null,
        public ?string $gatewayOrderId = null,
        public ?array $safeCallbackPayload = null,
        public ?string $redirectRoute = null,
        public ?string $redirectMessage = null,
    ) {}
}
