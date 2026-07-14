<?php

namespace App\Services\Promo;

use App\Enums\PromoCodeStatus;
use App\Models\PromoCode;
use App\Services\Promos\PromoCodeCalculator;
use App\Services\Promos\PromoCodeService;

/**
 * Validates promo codes for admin preview and lightweight checks.
 * Checkout apply/remove uses {@see PromoCodeService}.
 */
class PromoCodeValidationService
{
    public function __construct(
        protected PromoCodeService $promoCodeService,
        protected PromoCodeCalculator $calculator,
    ) {}

    /**
     * @return array{valid: bool, errors: list<string>, promo_code: ?PromoCode}
     */
    public function validate(string $code, ?int $agencyId = null, ?float $amount = null): array
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return [
                'valid' => false,
                'errors' => ['Promo code is required.'],
                'promo_code' => null,
            ];
        }

        $query = PromoCode::query()->whereRaw('UPPER(code) = ?', [$normalized]);

        if ($agencyId !== null) {
            $query->where(function ($q) use ($agencyId): void {
                $q->where('agency_id', $agencyId)->orWhereNull('agency_id');
            });
        }

        /** @var PromoCode|null $promo */
        $promo = $query->orderByDesc('agency_id')->first();

        if ($promo === null) {
            return [
                'valid' => false,
                'errors' => ['Promo code not found.'],
                'promo_code' => null,
            ];
        }

        $errors = $this->collectAdminPreviewErrors($promo, $amount);

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'promo_code' => $promo,
        ];
    }

    /**
     * @return list<string>
     */
    protected function collectAdminPreviewErrors(PromoCode $promo, ?float $amount): array
    {
        $previewAmount = $amount ?? 10000.0;
        $result = $this->promoCodeService->calculateDiscount($promo, $previewAmount);
        $errors = [];

        if ($promo->status !== PromoCodeStatus::Active) {
            $errors[] = 'Promo code is not active.';
        }

        $now = now();
        if ($promo->starts_at !== null && $promo->starts_at->isFuture()) {
            $errors[] = 'Promo code is not yet valid.';
        }
        if ($promo->ends_at !== null && $promo->ends_at->isPast()) {
            $errors[] = 'Promo code has expired.';
        }
        if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit) {
            $errors[] = 'Promo code usage limit reached.';
        }
        if ($amount !== null && $promo->min_amount !== null && $amount < (float) $promo->min_amount) {
            $errors[] = 'Order amount does not meet the minimum for this promo code.';
        }
        if ((float) $promo->value <= 0) {
            $errors[] = 'Promo code value is invalid.';
        }
        if ($result['final_payable'] < 0) {
            $errors[] = 'Promo discount would make payable negative.';
        }

        return $errors;
    }
}
