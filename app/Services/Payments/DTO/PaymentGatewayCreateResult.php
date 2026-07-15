<?php

namespace App\Services\Payments\DTO;

readonly class PaymentGatewayCreateResult
{
    /**
     * @param  array<string, mixed>|null  $safeResponse
     */
    public function __construct(
        public bool $success,
        public ?string $redirectUrl = null,
        public ?string $gatewayOrderId = null,
        public ?string $gatewayCode = null,
        public ?string $gatewayMessage = null,
        public ?array $safeResponse = null,
        public ?string $errorMessage = null,
    ) {}
}
