<?php

namespace App\Services\Payments\DTO;

use App\Enums\PaymentTransactionStatus;

readonly class PaymentGatewayVerifyResult
{
    /**
     * @param  array<string, mixed>|null  $safeResponse
     * @param  array<string, mixed>|null  $maskedCard
     */
    public function __construct(
        public bool $verified,
        public PaymentTransactionStatus $status,
        public ?string $gatewayOrderId = null,
        public ?string $gatewayStatus = null,
        public ?string $gatewayCode = null,
        public ?string $gatewayMessage = null,
        public ?array $safeResponse = null,
        public ?array $maskedCard = null,
        public ?string $failureReason = null,
    ) {}
}
