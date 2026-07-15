<?php

namespace App\Services\Promos;

use App\Data\PromoApplyResultData;
use App\Data\PromoValidationResultData;
use App\Enums\BookingStatus;
use App\Enums\PromoCodeAppliesTo;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use App\Enums\PromoRedemptionStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;
use App\Support\Payments\BookingPayableResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Validates, applies, removes, and redeems promo codes on flight booking checkout payables.
 * Supplier fare / PNR amounts are never modified.
 */
class PromoCodeService
{
    public function __construct(
        protected PromoCodeCalculator $calculator,
    ) {}

    public function validateForBooking(string $code, Booking $booking, ?User $user = null, ?string $sessionId = null): PromoValidationResultData
    {
        $booking->loadMissing(['fareBreakdown', 'promoCode']);
        $originalPayable = BookingPayableResolver::fareTotal($booking);
        $currency = (string) ($booking->currency ?? 'PKR');

        if ($originalPayable <= 0) {
            return PromoValidationResultData::invalid('This booking has no payable amount.');
        }

        $promo = $this->findPromoCode($code, $booking->agency_id);
        if ($promo === null) {
            return PromoValidationResultData::invalid('Promo code is invalid or not available.');
        }

        $errors = $this->collectBookingErrors($promo, $booking, $user, $sessionId, $originalPayable);
        if ($errors !== []) {
            return PromoValidationResultData::failure($errors, $promo);
        }

        $calc = $this->calculator->calculateDiscount($promo, $originalPayable, $currency);

        return new PromoValidationResultData(
            valid: true,
            errors: [],
            promoCode: $promo,
            originalPayable: $calc['original_payable'],
            discountAmount: $calc['discount_amount'],
            finalPayable: $calc['final_payable'],
            currency: $calc['currency'],
        );
    }

    public function applyToBooking(string $code, Booking $booking, ?User $user = null, ?string $sessionId = null): PromoApplyResultData
    {
        $validation = $this->validateForBooking($code, $booking, $user, $sessionId);
        if (! $validation->valid || $validation->promoCode === null) {
            return PromoApplyResultData::invalid($validation->errors);
        }

        $promo = $validation->promoCode;

        return DB::transaction(function () use ($booking, $promo, $validation, $user, $sessionId): PromoApplyResultData {
            $locked = Booking::query()->lockForUpdate()->with('fareBreakdown')->findOrFail($booking->id);

            if ($this->bookingBlocksPromoChange($locked)) {
                return PromoApplyResultData::failure('Promo cannot be changed on a paid, ticketed, or cancelled booking.');
            }

            if (filled($locked->promo_code) && strtoupper((string) $locked->promo_code) === $promo->normalizedCode()) {
                $existing = PromoRedemption::query()
                    ->where('booking_id', $locked->id)
                    ->where('promo_code_id', $promo->id)
                    ->where('status', PromoRedemptionStatus::Applied)
                    ->first();

                return new PromoApplyResultData(
                    success: true,
                    promoCode: $promo,
                    redemption: $existing,
                    originalPayable: (float) ($locked->payable_before_promo ?? $validation->originalPayable),
                    discountAmount: (float) $locked->promo_discount_amount,
                    finalPayable: (float) ($locked->payable_after_promo ?? $validation->finalPayable),
                    currency: $validation->currency,
                    message: 'Promo code already applied.',
                );
            }

            $this->cancelActiveRedemptions($locked);

            $locked->forceFill([
                'promo_code_id' => $promo->id,
                'promo_code' => $promo->normalizedCode(),
                'promo_discount_amount' => $validation->discountAmount,
                'payable_before_promo' => $validation->originalPayable,
                'payable_after_promo' => $validation->finalPayable,
                'promo_applied_at' => now(),
            ])->save();

            $redemption = PromoRedemption::query()->create([
                'promo_code_id' => $promo->id,
                'booking_id' => $locked->id,
                'user_id' => $user?->id,
                'session_id' => $sessionId,
                'code' => $promo->normalizedCode(),
                'original_amount' => $validation->originalPayable,
                'discount_amount' => $validation->discountAmount,
                'final_amount' => $validation->finalPayable,
                'currency' => $validation->currency,
                'status' => PromoRedemptionStatus::Applied,
                'applied_at' => now(),
            ]);

            $this->recalculateBookingBalance($locked->fresh(['fareBreakdown', 'payments']));
            $this->writeAudit($locked, $user, 'booking.promo_applied', [
                'promo_code' => $promo->normalizedCode(),
                'discount_amount' => $validation->discountAmount,
                'final_payable' => $validation->finalPayable,
            ]);

            return new PromoApplyResultData(
                success: true,
                promoCode: $promo,
                redemption: $redemption,
                originalPayable: $validation->originalPayable,
                discountAmount: $validation->discountAmount,
                finalPayable: $validation->finalPayable,
                currency: $validation->currency,
                message: sprintf(
                    'Promo code %s applied. Discount: %s %.2f. New payable: %s %.2f.',
                    $promo->normalizedCode(),
                    $validation->currency,
                    $validation->discountAmount,
                    $validation->currency,
                    $validation->finalPayable,
                ),
            );
        });
    }

    public function removeFromBooking(Booking $booking, ?User $actor = null): void
    {
        DB::transaction(function () use ($booking, $actor): void {
            $locked = Booking::query()->lockForUpdate()->with('fareBreakdown')->findOrFail($booking->id);

            if (! filled($locked->promo_code)) {
                return;
            }

            if ($this->bookingBlocksPromoChange($locked)) {
                throw new \InvalidArgumentException('Promo cannot be removed from a paid, ticketed, or cancelled booking.');
            }

            $code = (string) $locked->promo_code;
            $this->cancelActiveRedemptions($locked);

            $locked->forceFill([
                'promo_code_id' => null,
                'promo_code' => null,
                'promo_discount_amount' => 0,
                'payable_before_promo' => null,
                'payable_after_promo' => null,
                'promo_applied_at' => null,
            ])->save();

            $this->recalculateBookingBalance($locked->fresh(['fareBreakdown', 'payments']));
            $this->writeAudit($locked, $actor, 'booking.promo_removed', ['promo_code' => $code]);
        });
    }

    public function redeemForBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if (! filled($locked->promo_code_id)) {
                return;
            }

            $redemption = PromoRedemption::query()
                ->where('booking_id', $locked->id)
                ->where('promo_code_id', $locked->promo_code_id)
                ->where('status', PromoRedemptionStatus::Applied)
                ->lockForUpdate()
                ->first();

            if ($redemption === null) {
                return;
            }

            if ($redemption->status === PromoRedemptionStatus::Redeemed) {
                return;
            }

            $redemption->forceFill([
                'status' => PromoRedemptionStatus::Redeemed,
                'redeemed_at' => now(),
            ])->save();

            PromoCode::query()
                ->whereKey($locked->promo_code_id)
                ->increment('used_count');
        });
    }

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
        return $this->calculator->calculateDiscount($promo, $payable, $currency);
    }

    protected function findPromoCode(string $code, ?int $agencyId): ?PromoCode
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return null;
        }

        $query = PromoCode::query()->whereRaw('UPPER(code) = ?', [$normalized]);

        if ($agencyId !== null) {
            $query->where(function ($q) use ($agencyId): void {
                $q->where('agency_id', $agencyId)->orWhereNull('agency_id');
            })->orderByDesc('agency_id');
        }

        return $query->first();
    }

    /**
     * @return list<string>
     */
    protected function collectBookingErrors(
        PromoCode $promo,
        Booking $booking,
        ?User $user,
        ?string $sessionId,
        float $originalPayable,
    ): array {
        $errors = [];

        if ($promo->status !== PromoCodeStatus::Active) {
            $errors[] = 'Promo code is not active.';
        }

        $now = Carbon::now();
        if ($promo->starts_at !== null && $promo->starts_at->isFuture()) {
            $errors[] = 'Promo code is not yet valid.';
        }
        if ($promo->ends_at !== null && $promo->ends_at->isPast()) {
            $errors[] = 'Promo code has expired.';
        }

        if ($promo->usage_limit !== null) {
            $redeemedCount = $promo->redemptions()
                ->where('status', PromoRedemptionStatus::Redeemed)
                ->count();
            if ($redeemedCount >= (int) $promo->usage_limit) {
                $errors[] = 'Promo code usage limit reached.';
            }
        }

        if ($promo->min_amount !== null && $originalPayable < (float) $promo->min_amount) {
            $errors[] = 'Order amount does not meet the minimum for this promo code.';
        }

        if ($promo->type === PromoCodeType::Percent) {
            $value = (float) $promo->value;
            if ($value <= 0) {
                $errors[] = 'Promo code value is invalid.';
            }
        } elseif ($promo->type === PromoCodeType::Fixed && (float) $promo->value <= 0) {
            $errors[] = 'Promo code value is invalid.';
        }

        if (! $this->appliesToBooking($promo, $booking)) {
            $errors[] = 'Promo code does not apply to this booking type.';
        }

        if ($this->bookingBlocksPromoChange($booking) && ! filled($booking->promo_code)) {
            $errors[] = 'Promo cannot be applied to this booking.';
        }

        if ($promo->internal_testing_only && ! $this->internalTestingAllowed($booking, $user)) {
            $errors[] = 'Promo code is invalid or not available.';
        }

        if ($promo->per_user_limit !== null) {
            $usageQuery = PromoRedemption::query()
                ->where('promo_code_id', $promo->id)
                ->where('status', PromoRedemptionStatus::Redeemed);

            if ($user !== null) {
                $usageQuery->where('user_id', $user->id);
            } elseif ($sessionId !== null) {
                $usageQuery->where('session_id', $sessionId);
            } else {
                $usageQuery->where('booking_id', $booking->id);
            }

            if ($usageQuery->count() >= (int) $promo->per_user_limit) {
                $errors[] = 'Promo code usage limit reached for this customer.';
            }
        }

        return $errors;
    }

    protected function appliesToBooking(PromoCode $promo, Booking $booking): bool
    {
        return match ($promo->applies_to) {
            PromoCodeAppliesTo::All => true,
            PromoCodeAppliesTo::Flights => true,
            PromoCodeAppliesTo::GroupTicketing => false,
        };
    }

    protected function internalTestingAllowed(Booking $booking, ?User $user): bool
    {
        if (! config('ota.promo.allow_internal_testing_codes', true)) {
            return false;
        }

        if ($user !== null && ($user->isPlatformAdmin() || $user->isAgencyAdmin() || $user->isStaff())) {
            return true;
        }

        return (bool) data_get($booking->meta, 'internal_testing_booking', false);
    }

    protected function bookingBlocksPromoChange(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Cancelled) {
            return true;
        }

        if (in_array((string) ($booking->payment_status ?? ''), ['paid'], true)) {
            return true;
        }

        if (in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)) {
            return true;
        }

        if ($booking->tickets()->exists()) {
            return true;
        }

        return false;
    }

    protected function cancelActiveRedemptions(Booking $booking): void
    {
        PromoRedemption::query()
            ->where('booking_id', $booking->id)
            ->where('status', PromoRedemptionStatus::Applied)
            ->update([
                'status' => PromoRedemptionStatus::Cancelled,
            ]);
    }

    protected function recalculateBookingBalance(Booking $booking): void
    {
        $payable = BookingPayableResolver::customerPayableTotal($booking);
        $verifiedTotal = BookingPayableResolver::verifiedPaidAmount($booking);
        $balanceDue = max(0, round($payable - $verifiedTotal, 2));

        $paymentStatus = 'unpaid';
        if ($verifiedTotal > 0 && $verifiedTotal < $payable) {
            $paymentStatus = 'partial';
        } elseif ($payable > 0 && $verifiedTotal >= $payable) {
            $paymentStatus = 'paid';
        } elseif ($payable <= 0 && $verifiedTotal > 0) {
            $paymentStatus = 'paid';
        }

        $booking->forceFill([
            'balance_due' => $payable > 0 ? $balanceDue : null,
            'payment_status' => $paymentStatus,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    protected function writeAudit(Booking $booking, ?User $actor, string $action, array $newValues): void
    {
        try {
            AuditLog::query()->create([
                'agency_id' => $booking->agency_id,
                'user_id' => $actor?->id,
                'action' => $action,
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'properties' => [
                    'old_values' => [],
                    'new_values' => $newValues,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
