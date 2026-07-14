<?php

namespace App\Data;

use App\Models\PromoCode;

/**
 * Result of validating a promo code against a booking payable context.
 */
readonly class PromoValidationResultData
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors,
        public ?PromoCode $promoCode = null,
        public float $originalPayable = 0.0,
        public float $discountAmount = 0.0,
        public float $finalPayable = 0.0,
        public string $currency = 'PKR',
    ) {}

    public static function invalid(string $message): self
    {
        return new self(valid: false, errors: [$message]);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors, ?PromoCode $promoCode = null): self
    {
        return new self(valid: false, errors: $errors, promoCode: $promoCode);
    }
}
