<?php

namespace App\Support\Sabre;

use App\Models\Booking;

/**
 * Blocks automatic same-offer retry when a definitive host sell rejection fingerprint matches.
 */
final class SabreHostSellRetryGuard
{
    /**
     * @param  array<string, mixed>  $offer
     * @return array{blocked: bool, message: string, fingerprint_hash: ?string, occurrence_count: ?int}
     */
    public static function evaluateSameOfferRetry(Booking $booking, array $offer): array
    {
        $segmentStatus = self::latestSegmentStatus($booking);
        $prior = SabreHostSellFingerprint::findPriorRejectionForOffer($booking, $offer, $segmentStatus);

        if ($prior === null) {
            return [
                'blocked' => false,
                'message' => '',
                'fingerprint_hash' => null,
                'occurrence_count' => null,
            ];
        }

        $safeReason = strtolower(trim((string) ($prior['latest_safe_reason_code'] ?? $prior['safe_reason_code'] ?? '')));
        if (! SabreHostSellClassifier::isDefinitiveSameOfferRejection($safeReason, $segmentStatus)) {
            return [
                'blocked' => false,
                'message' => '',
                'fingerprint_hash' => (string) ($prior['fingerprint_hash'] ?? null),
                'occurrence_count' => (int) ($prior['occurrence_count'] ?? 1),
            ];
        }

        return [
            'blocked' => true,
            'message' => SabreHostSellClassifier::CUSTOMER_SAME_OFFER_BLOCKED_MESSAGE,
            'fingerprint_hash' => (string) ($prior['fingerprint_hash'] ?? ''),
            'occurrence_count' => (int) ($prior['occurrence_count'] ?? 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    public static function shouldBlockFromDiagnostics(array $diagnostics): bool
    {
        $safeReason = strtolower(trim((string) ($diagnostics['safe_reason_code'] ?? '')));
        $status = is_array($diagnostics['airline_segment_statuses'] ?? null)
            ? (string) ($diagnostics['airline_segment_statuses'][0] ?? '')
            : null;

        return SabreHostSellClassifier::isDefinitiveSameOfferRejection($safeReason, $status)
            && (int) ($diagnostics['occurrence_count'] ?? 1) >= 1;
    }

    protected static function latestSegmentStatus(Booking $booking): ?string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $diag = is_array($meta['sabre_host_sell_diagnostics'] ?? null) ? $meta['sabre_host_sell_diagnostics'] : [];
        $statuses = is_array($diag['airline_segment_statuses'] ?? null) ? $diag['airline_segment_statuses'] : [];
        if ($statuses !== []) {
            return strtoupper(trim((string) $statuses[0]));
        }

        $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
        $fromCheckout = strtoupper(trim((string) ($checkout['airline_segment_status'] ?? '')));

        return $fromCheckout !== '' ? $fromCheckout : null;
    }
}
