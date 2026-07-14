<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;

/**
 * BF6-FIX6: When public auto-PNR and ticketing are OFF, Sabre booking validation failures
 * must not block pay-after-confirmation manual booking requests.
 */
final class SabreBookingValidationManualRequestPolicy
{
    public const LOG_EVENT = 'sabre_booking_validation_non_blocking_manual_request';

    public static function publicAutoPnrEnabled(): bool
    {
        return (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false)
            || (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
    }

    public static function ticketingEnabled(): bool
    {
        return (bool) config('suppliers.sabre.ticketing_enabled', false);
    }

    public static function isPayAfterConfirmationRequest(string $confirmationMethod): bool
    {
        return $confirmationMethod === 'pay_later_booking_request';
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    public static function allowsNonBlockingValidationFailure(Booking $booking, array $outcome): bool
    {
        if (self::publicAutoPnrEnabled() || self::ticketingEnabled()) {
            return false;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $confirmationMethod = (string) ($meta['confirmation_method'] ?? $meta['booking_method'] ?? '');
        if (! self::isPayAfterConfirmationRequest($confirmationMethod)) {
            return false;
        }

        if (self::outcomeHasPnrOrSupplierReference($outcome)) {
            return false;
        }

        $code = (string) ($outcome['error_code'] ?? '');
        $statusOut = (string) ($outcome['status'] ?? '');

        $isValidationFailure = $code === 'sabre_booking_validation_failed'
            || ($statusOut === 'validation_failed' && $code !== 'sabre_booking_forbidden');

        if (! $isValidationFailure) {
            return false;
        }

        if (($outcome['live_call_attempted'] ?? false) === true) {
            return ! self::outcomeHasPnrOrSupplierReference($outcome);
        }

        return $statusOut === 'validation_failed';
    }

    public static function customerNotice(): string
    {
        return (string) __('This fare will be reviewed and confirmed by our team before ticketing.');
    }

    public static function customerSafeMessage(?string $raw): string
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return (string) __('This Sabre fare could not be validated for submission. Please contact our team for assistance.');
        }

        if (self::messageLooksLikeInternalSabreValidation($text)) {
            return (string) __('This Sabre fare could not be validated for submission. Please contact our team for assistance.');
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    public static function logNonBlocking(Booking $booking, array $outcome): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $selectedFare = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : [];

        Log::warning(self::LOG_EVENT, [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'brand_name' => trim((string) ($selectedFare['name'] ?? $selectedFare['brand_name'] ?? '')),
            'brand_code' => trim((string) ($selectedFare['brand_code'] ?? '')),
            'validation_failure_class' => (string) ($outcome['status'] ?? ''),
            'validation_error_code' => (string) ($outcome['error_code'] ?? ''),
            'pointer_summary' => self::pointerSummaryFromOutcome($outcome),
            'public_auto_pnr_enabled' => self::publicAutoPnrEnabled(),
            'ticketing_enabled' => self::ticketingEnabled(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    public static function persistDeferManualReviewMeta(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['defer_supplier_booking_to_manual_review'] = true;
        $meta['sabre_validation_non_blocking_manual_request'] = true;
        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected static function outcomeHasPnrOrSupplierReference(array $outcome): bool
    {
        $pnr = trim((string) ($outcome['pnr'] ?? ''));
        $providerBookingId = trim((string) ($outcome['provider_booking_id'] ?? ''));

        return $pnr !== '' || $providerBookingId !== '';
    }

    protected static function messageLooksLikeInternalSabreValidation(string $text): bool
    {
        $lower = strtolower($text);
        $needles = [
            'pointer',
            'createpassengernamerecordrq',
            '/airprice/',
            'object instance has properties',
            'sabre booking validation failed:',
            'json schema',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected static function pointerSummaryFromOutcome(array $outcome): string
    {
        $paths = is_array($outcome['response_error_paths'] ?? null)
            ? $outcome['response_error_paths']
            : [];
        foreach ($paths as $path) {
            $token = trim((string) $path);
            if ($token !== '') {
                return substr($token, 0, 200);
            }
        }

        $excerpts = is_array($outcome['safe_validation_excerpts'] ?? null)
            ? $outcome['safe_validation_excerpts']
            : [];
        foreach ($excerpts as $excerpt) {
            $token = trim((string) $excerpt);
            if ($token !== '' && (str_contains(strtolower($token), 'pointer') || str_contains($token, '/CreatePassengerNameRecordRQ'))) {
                return substr($token, 0, 200);
            }
        }

        foreach ($excerpts as $excerpt) {
            $token = trim((string) $excerpt);
            if ($token !== '') {
                return substr($token, 0, 200);
            }
        }

        return '';
    }
}
