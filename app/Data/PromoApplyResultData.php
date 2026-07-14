<?php

namespace App\Data;

use App\Models\PromoCode;
use App\Models\PromoRedemption;

/**
 * Result of applying a promo code to a booking.
 */
readonly class PromoApplyResultData
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public bool $success,
        public array $errors = [],
        public ?PromoCode $promoCode = null,
        public ?PromoRedemption $redemption = null,
        public float $originalPayable = 0.0,
        public float $discountAmount = 0.0,
        public float $finalPayable = 0.0,
        public string $currency = 'PKR',
        public ?string $message = null,
    ) {}

    public static function failure(string $message): self
    {
        return new self(success: false, errors: [$message]);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(success: false, errors: $errors);
    }
}
