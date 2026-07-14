<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use Carbon\Carbon;

/**
 * Sprint 11K-I: Match selected Sabre offers against recent persisted host-rejection fingerprints (bounded query, no live Sabre).
 */
final class SabreHostRejectionFingerprintMatcher
{
    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $bookingMeta
     * @return array<string, mixed>
     */
    public function applyMatchToBookingMeta(array $offer, ?int $agencyId = null, ?array $bookingMeta = null): array
    {
        $bookingMeta = is_array($bookingMeta) ? $bookingMeta : [];
        $match = $this->findMatchForOffer($offer, $agencyId);
        if ($match === null) {
            unset($bookingMeta['persisted_host_rejection_for_offer'], $bookingMeta['host_rejection_fingerprint_match']);

            return $bookingMeta;
        }

        return array_merge($bookingMeta, [
            'persisted_host_rejection_for_offer' => true,
            'host_rejection_fingerprint_match' => $match,
        ]);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null Safe scalar match slice for meta/admin (no raw payloads)
     */
    public function findMatchForOffer(array $offer, ?int $agencyId = null): ?array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), SupplierProvider::Sabre->value) !== 0) {
            return null;
        }

        $offerFields = SabreHostRejectionFingerprint::extractMatchFieldsFromOffer($offer);
        if ($offerFields['origin'] === '' || $offerFields['destination'] === '') {
            return null;
        }

        $hash = SabreHostRejectionFingerprint::computeFingerprintHash($offerFields);
        $lookbackDays = max(1, (int) config('ota.host_rejection_fingerprint.lookback_days', 30));
        $maxScan = max(1, min(200, (int) config('ota.host_rejection_fingerprint.max_bookings_scan', 40)));

        $query = Booking::query()
            ->where('supplier', SupplierProvider::Sabre->value)
            ->where('created_at', '>=', Carbon::now()->subDays($lookbackDays))
            ->where('meta->sabre_checkout_outcome->sabre_host_rejection_fingerprint->fingerprint_hash', $hash)
            ->orderByDesc('id')
            ->limit($maxScan);

        if ($agencyId !== null && $agencyId > 0) {
            $query->where('agency_id', $agencyId);
        }

        $routeNeedle = $offerFields['origin'].' → '.$offerFields['destination'];
        $query->where(function ($builder) use ($routeNeedle, $offerFields): void {
            $builder->where('route', $routeNeedle)
                ->orWhere('route', 'like', '%'.$offerFields['origin'].'%'.$offerFields['destination'].'%');
        });

        /** @var Booking|null $hit */
        $hit = $query->first(['id', 'meta', 'created_at']);
        if ($hit === null) {
            return null;
        }

        $stored = data_get($hit->meta, 'sabre_checkout_outcome.sabre_host_rejection_fingerprint');
        if (! is_array($stored) || ! SabreHostRejectionFingerprint::offerMatchesStoredFingerprint($stored, $offerFields)) {
            return null;
        }

        return [
            'fingerprint_match' => true,
            'matched_fingerprint_hash' => $hash,
            'matched_host_error_family' => (string) ($stored['host_error_family'] ?? ''),
            'matched_safe_reason_code' => (string) ($stored['safe_reason_code'] ?? ''),
            'retry_policy' => (string) ($stored['retry_policy'] ?? SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER),
            'recommended_admin_action' => (string) ($stored['recommended_admin_action'] ?? ''),
            'matched_recorded_at' => (string) ($stored['recorded_at'] ?? ''),
            'matched_source_booking_id' => $hit->id,
            'matched_source_layer' => (string) ($stored['source_layer'] ?? ''),
        ];
    }
}
