<?php

namespace Tests\Support\Sabre;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationGate;

/**
 * Test double that always reports successful scenario revalidation.
 */
class AlwaysSuccessfulScenarioRevalidationGate extends SabreGdsLiveScenarioRevalidationGate
{
    public function __construct()
    {
        // Skip production dependencies for tests.
    }

    /**
     * @param  array<string, mixed>  $offerSnap
     * @param  array{passenger: array<string, mixed>, contact: array<string, mixed>}  $passengerBundle
     * @return array<string, mixed>
     */
    public function revalidateSelectedOffer(
        SupplierConnection $connection,
        array $offerSnap,
        array $passengerBundle,
        float $selectedTotal,
        ?int $bookingId = null,
        array $continuity = [],
    ): array {
        return $this->successEvidence($selectedTotal, $continuity);
    }

    /**
     * @return array<string, mixed>
     */
    public function revalidateForBooking(Booking $booking, SupplierConnection $connection): array
    {
        return $this->successEvidence((float) ($booking->selected_fare_total ?? 0));
    }

    public function shouldProceed(array $evidence): bool
    {
        return true;
    }

    public function persistOnBooking(Booking $booking, array $evidence): void
    {
        // no-op in tests
    }

    /**
     * @return array<string, mixed>
     */
    private function successEvidence(float $selectedTotal, array $continuity = []): array
    {
        $selectedCurrency = is_string($continuity['selected_currency'] ?? null)
            ? strtoupper(trim($continuity['selected_currency']))
            : 'PKR';

        return [
            'revalidation_attempted' => true,
            'revalidation_success' => true,
            'freshness_satisfied' => true,
            'selected_total' => $selectedTotal > 0 ? $selectedTotal : null,
            'selected_currency' => $selectedCurrency,
            'revalidated_total' => $selectedTotal > 0 ? $selectedTotal : null,
            'revalidated_currency' => $selectedCurrency,
            'fare_changed' => false,
            'revalidation_at' => now()->toIso8601String(),
            'selected_offer_fingerprint' => $continuity['expected_fingerprint'] ?? null,
            'revalidation_linkage_ready' => ($continuity['revalidation_linkage_ready'] ?? true) === true,
        ];
    }
}
