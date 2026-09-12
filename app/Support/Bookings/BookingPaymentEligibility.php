<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Payments\BookingPayableResolver;

/**
 * Authoritative payment eligibility and email payable presentation for bookings.
 */
final class BookingPaymentEligibility
{
    /** @var list<string> */
    private const VALID_VALIDATION_STATUSES = [
        'valid',
        'validated',
        'ok',
        'pass',
        'fresh',
        'success',
        'accepted',
        'changed',
    ];

    /** @var list<string> */
    private const FAILED_VALIDATION_STATUSES = [
        'invalid',
        'failed',
        'expired',
        'rejected',
        'error',
    ];

    /**
     * @return array{
     *     eligible: bool,
     *     reasons: list<string>,
     *     validation_state: string,
     *     validation_label: string,
     *     final_payable_status: string,
     *     final_payable_amount: ?string,
     *     payment_state_label: string,
     *     has_authoritative_payable: bool
     * }
     */
    public static function evaluate(Booking $booking): array
    {
        $booking->loadMissing(['fareBreakdown', 'payments']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $reasons = [];

        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
            $reasons[] = 'booking_cancelled';
        }

        if ($booking->status === BookingStatus::Expired) {
            $reasons[] = 'booking_expired';
        }

        $paymentStatus = strtolower(trim((string) ($booking->payment_status ?? 'unpaid')));
        if ($paymentStatus === 'paid' || $booking->status === BookingStatus::Paid) {
            $reasons[] = 'already_paid';
        }

        $validation = self::resolveValidationState($booking, $meta);
        if ($validation['state'] === 'pending') {
            $reasons[] = 'offer_validation_pending';
        } elseif ($validation['state'] === 'failed') {
            $reasons[] = 'offer_validation_failed';
        }

        $hasBrandedSelection = self::hasSelectedFareFamily($meta);
        $authoritativePayable = self::hasAuthoritativePayable($booking, $meta, $hasBrandedSelection);
        if ($hasBrandedSelection && ! $authoritativePayable && $validation['state'] === 'valid') {
            $reasons[] = 'payable_not_authoritative';
        }

        $payableAmount = BookingPayableResolver::balanceDue($booking);
        if ($payableAmount <= 0 && ! BookingPayableResolver::allowsZeroPayableCheckout($booking)) {
            $reasons[] = 'no_payable_balance';
        }

        $presentation = self::buildPresentation($booking, $validation, $authoritativePayable, $payableAmount, $hasBrandedSelection);
        $reasons = array_values(array_unique($reasons));

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'validation_state' => $validation['state'],
            'validation_label' => $presentation['validation_label'],
            'final_payable_status' => $presentation['final_payable_status'],
            'final_payable_amount' => $presentation['final_payable_amount'],
            'payment_state_label' => $presentation['payment_state_label'],
            'has_authoritative_payable' => $authoritativePayable,
        ];
    }

    public static function allowsPayment(Booking $booking): bool
    {
        return self::evaluate($booking)['eligible'];
    }

    public static function denialMessage(Booking $booking): ?string
    {
        $evaluation = self::evaluate($booking);
        if ($evaluation['eligible']) {
            return null;
        }

        return match ($evaluation['reasons'][0] ?? '') {
            'booking_cancelled' => 'Payment is not available for cancelled bookings.',
            'booking_expired' => 'This booking has expired and payment is no longer available.',
            'already_paid' => 'This booking has already been paid.',
            'offer_validation_pending' => 'Fare validation must complete before payment is available.',
            'offer_validation_failed' => 'Fare validation failed; payment is not available.',
            'payable_not_authoritative' => 'Final payable amount is not yet confirmed.',
            'no_payable_balance' => 'No payment balance is due on this booking.',
            default => 'Payment is not currently available for this booking.',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{state: string, raw_status: string}
     */
    private static function resolveValidationState(Booking $booking, array $meta): array
    {
        $raw = strtolower(trim((string) ($meta['offer_validation_status'] ?? '')));
        $hasSnapshot = isset($meta['validated_offer_snapshot']) || isset($meta['normalized_offer_snapshot']);
        $hasValidatedAt = trim((string) ($meta['offer_validated_at'] ?? '')) !== '';

        if (in_array($raw, self::FAILED_VALIDATION_STATUSES, true)) {
            return ['state' => 'failed', 'raw_status' => $raw];
        }

        if (in_array($raw, self::VALID_VALIDATION_STATUSES, true)) {
            return ['state' => 'valid', 'raw_status' => $raw];
        }

        if ($raw === '' && $hasSnapshot && $hasValidatedAt) {
            return ['state' => 'valid', 'raw_status' => 'validated_at'];
        }

        if ($raw === 'pending' || $raw === 'unknown' || $raw === '') {
            if (self::hasSelectedFareFamily($meta)) {
                return ['state' => 'pending', 'raw_status' => $raw !== '' ? $raw : 'pending'];
            }

            if ($raw === '' && $hasSnapshot) {
                return ['state' => 'valid', 'raw_status' => 'snapshot'];
            }

            return ['state' => 'valid', 'raw_status' => 'not_required'];
        }

        return ['state' => 'pending', 'raw_status' => $raw];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function hasSelectedFareFamily(array $meta): bool
    {
        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];

        return $family !== [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function hasAuthoritativePayable(Booking $booking, array $meta, bool $hasBrandedSelection): bool
    {
        if (! $hasBrandedSelection) {
            return BookingPayableResolver::fareTotal($booking) > 0;
        }

        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        if (! empty($family['authoritative_after_revalidation'])) {
            return true;
        }

        if (empty($family['price_is_approximate']) && empty($family['is_price_approximate'])) {
            $displayed = isset($family['displayed_price']) && is_numeric($family['displayed_price'])
                ? (float) $family['displayed_price']
                : 0.0;

            return $displayed > 0 || BookingPayableResolver::fareTotal($booking) > 0;
        }

        return BookingPayableResolver::fareTotal($booking) > 0
            && trim((string) ($meta['offer_validated_at'] ?? '')) !== '';
    }

    /**
     * @param  array{state: string, raw_status: string}  $validation
     * @return array{
     *     validation_label: string,
     *     final_payable_status: string,
     *     final_payable_amount: ?string,
     *     payment_state_label: string
     * }
     */
    private static function buildPresentation(
        Booking $booking,
        array $validation,
        bool $authoritativePayable,
        float $payableAmount,
        bool $hasBrandedSelection,
    ): array {
        $currency = strtoupper(trim((string) ($booking->currency ?? $booking->fareBreakdown?->currency ?? 'PKR')));
        $paymentStatus = strtolower(trim((string) ($booking->payment_status ?? 'unpaid')));

        $validationLabel = match ($validation['state']) {
            'valid' => 'Validated',
            'failed' => 'Failed / requires attention',
            default => 'Pending',
        };

        if ($validation['state'] === 'failed') {
            return [
                'validation_label' => $validationLabel,
                'final_payable_status' => 'Not available',
                'final_payable_amount' => null,
                'payment_state_label' => 'Not available',
            ];
        }

        if ($validation['state'] === 'pending') {
            return [
                'validation_label' => $validationLabel,
                'final_payable_status' => 'Awaiting fare validation',
                'final_payable_amount' => null,
                'payment_state_label' => 'Not available',
            ];
        }

        if ($hasBrandedSelection && ! $authoritativePayable) {
            return [
                'validation_label' => $validationLabel,
                'final_payable_status' => 'Awaiting fare validation',
                'final_payable_amount' => null,
                'payment_state_label' => 'Not available',
            ];
        }

        $amountLabel = $payableAmount > 0
            ? $currency.' '.number_format($payableAmount, 0, '.', ',')
            : ($authoritativePayable && BookingPayableResolver::fareTotal($booking) > 0
                ? $currency.' '.number_format(BookingPayableResolver::fareTotal($booking), 0, '.', ',')
                : null);

        $paymentStateLabel = match (true) {
            $paymentStatus === 'paid' => 'Paid',
            in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Refunded], true) => 'Not available',
            $booking->status === BookingStatus::Expired => 'Not available',
            default => 'Pending',
        };

        return [
            'validation_label' => $validationLabel,
            'final_payable_status' => $amountLabel ?? 'Not available',
            'final_payable_amount' => $amountLabel,
            'payment_state_label' => $paymentStateLabel,
        ];
    }
}
