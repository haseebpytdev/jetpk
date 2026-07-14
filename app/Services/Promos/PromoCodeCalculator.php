<?php

namespace App\Services\Promos;

use App\Enums\PromoCodeType;
use App\Models\PromoCode;

/**
 * Pure promo discount math — does not mutate bookings or supplier fares.
 */
class PromoCodeCalculator
{
    /**
     * @return array{
     *     original_payable: float,
     *     discount_amount: float,
     *     final_payable: float,
     *     currency: string
     * }
     */
    public function calculateDiscount(PromoCode $promo, float $payable, string $currency = 'PKR'): array
    {
        $original = round(max(0, $payable), 2);
        $discount = $this->rawDiscount($promo, $original);

        if ($promo->max_discount !== null) {
            $discount = min($discount, round((float) $promo->max_discount, 2));
        }

        $allowZero = $this->allowsZeroPayable($promo);
        if (! $allowZero) {
            $discount = min($discount, max(0, $original - 1));
        } else {
            $discount = min($discount, $original);
        }

        $discount = round(max(0, $discount), 2);
        $final = round(max(0, $original - $discount), 2);

        if (! $allowZero && $final > 0 && $final < 1) {
            $final = 1.0;
            $discount = round(max(0, $original - $final), 2);
        }

        return [
            'original_payable' => $original,
            'discount_amount' => $discount,
            'final_payable' => $final,
            'currency' => $currency,
        ];
    }

    protected function rawDiscount(PromoCode $promo, float $payable): float
    {
        if ($payable <= 0) {
            return 0.0;
        }

        return match ($promo->type) {
            PromoCodeType::Percent => round($payable * ((float) $promo->value / 100), 2),
            PromoCodeType::Fixed => round((float) $promo->value, 2),
        };
    }

    public function allowsZeroPayable(PromoCode $promo): bool
    {
        if (! config('ota.promo.allow_zero_payable', false)) {
            return false;
        }

        return (bool) $promo->internal_testing_only;
    }
}
