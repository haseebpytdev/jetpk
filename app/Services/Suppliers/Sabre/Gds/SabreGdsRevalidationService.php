<?php

namespace App\Services\Suppliers\Sabre\Gds;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;

/**
 * Production-capable GDS revalidation with fare comparison and booking persistence.
 * Wraps {@see SabreBookingService::runRevalidationBeforeBooking()} with Binham/IATI-like payload defaults.
 */
final class SabreGdsRevalidationService
{
    public function __construct(
        private readonly SabreBookingService $sabreBookingService,
        private readonly SabreRevalidationPayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    public function revalidateDraft(
        array $apiDraft,
        SupplierConnection $connection,
        ?string $payloadStyle = null,
        ?int $bookingId = null,
    ): array {
        $style = $payloadStyle ?? $this->defaultRevalidateStyle();
        $outcome = $this->sabreBookingService->runRevalidationBeforeBooking(
            $apiDraft,
            $connection,
            $style,
            null,
            $bookingId,
        );

        return $this->enrichOutcome($outcome, $apiDraft);
    }

    /**
     * @return array<string, mixed>
     */
    public function revalidateForBooking(Booking $booking, SupplierConnection $connection, bool $persist = true): array
    {
        $offer = $this->resolveOfferFromBooking($booking);
        $gate = $this->sabreBookingService->validateNormalizedSabreOffer($offer);
        if (! $gate->success) {
            return [
                'success' => false,
                'reason_code' => 'offer_validation_failed',
                'blockers' => [(string) ($gate->safe_context['reason'] ?? 'validation_failed')],
            ];
        }

        $draft = $this->sabreBookingService->prepareBookingPayload($offer, [
            'passengers' => $this->passengersFromBooking($booking),
        ]);

        if (($draft['_valid'] ?? false) !== true) {
            return [
                'success' => false,
                'reason_code' => (string) ($draft['code'] ?? 'draft_invalid'),
                'blockers' => ['draft_invalid'],
            ];
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);

        $outcome = $this->revalidateDraft($apiDraft, $connection, null, $booking->id);

        if ($persist) {
            $this->persistRevalidationOnBooking($booking, $outcome, $apiDraft);
        }

        return $outcome;
    }

    /**
     * Multi-O&D revalidation using iati_like_bfm_revalidate_v1 style (Binham parity).
     *
     * @param  array<string, mixed>  $apiDraft  Must include segments grouped for multi-city
     * @return array<string, mixed>
     */
    public function revalidateMulticityDraft(
        array $apiDraft,
        SupplierConnection $connection,
        ?int $bookingId = null,
    ): array {
        return $this->revalidateDraft(
            $apiDraft,
            $connection,
            'iati_like_bfm_revalidate_v1',
            $bookingId,
        );
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    private function enrichOutcome(array $outcome, array $apiDraft): array
    {
        $storedTotal = (float) ($apiDraft['total_fare'] ?? $apiDraft['fare_total'] ?? 0);
        $storedCurrency = strtoupper(trim((string) ($apiDraft['currency'] ?? $apiDraft['fare_currency'] ?? '')));
        $linkage = is_array($outcome['linkage'] ?? null) ? $outcome['linkage'] : [];
        $freshTotal = (float) ($linkage['revalidated_fare_total'] ?? $linkage['total_fare'] ?? 0);
        $freshCurrency = strtoupper(trim((string) ($linkage['revalidated_fare_currency'] ?? $linkage['currency'] ?? '')));

        $mismatches = [];
        if ($storedTotal > 0 && $freshTotal > 0 && abs($storedTotal - $freshTotal) > 0.01) {
            $mismatches[] = 'price_change';
        }
        if ($storedCurrency !== '' && $freshCurrency !== '' && $storedCurrency !== $freshCurrency) {
            $mismatches[] = 'currency_change';
        }

        $itineraryCount = (int) data_get($outcome, 'response_structure.itinerary_count', 0);
        if (($outcome['success'] ?? false) && $itineraryCount === 0) {
            $mismatches[] = 'no_pricing';
            $outcome['success'] = false;
            $outcome['reason_code'] = $outcome['reason_code'] ?? 'sabre_revalidation_empty_or_unusable_response';
        }

        $outcome['fare_comparison'] = [
            'stored_total' => $storedTotal > 0 ? $storedTotal : null,
            'stored_currency' => $storedCurrency !== '' ? $storedCurrency : null,
            'fresh_total' => $freshTotal > 0 ? $freshTotal : null,
            'fresh_currency' => $freshCurrency !== '' ? $freshCurrency : null,
            'mismatches' => $mismatches,
        ];

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $apiDraft
     */
    public function persistRevalidationOnBooking(Booking $booking, array $outcome, array $apiDraft): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $linkage = is_array($outcome['linkage'] ?? null) ? $outcome['linkage'] : [];
        $comparison = is_array($outcome['fare_comparison'] ?? null) ? $outcome['fare_comparison'] : [];
        $now = now();

        $payloadDigest = $this->payloadBuilder->revalidationPayloadFreezeFingerprint(
            $this->payloadBuilder->buildPayload($apiDraft, $this->defaultRevalidateStyle()),
            $apiDraft,
        );

        $meta['sabre_revalidation'] = SensitiveDataRedactor::redact([
            'revalidated_at' => $now->toIso8601String(),
            'success' => ($outcome['success'] ?? false) === true,
            'reason_code' => (string) ($outcome['reason_code'] ?? ''),
            'payload_style' => (string) ($outcome['payload_style'] ?? ''),
            'endpoint_path' => (string) ($outcome['endpoint_path'] ?? ''),
            'payload_digest' => $payloadDigest,
            'response_digest' => md5(json_encode([
                'http_status' => $outcome['http_status'] ?? null,
                'linkage_digest' => $outcome['linkage_digest'] ?? [],
                'itinerary_count' => data_get($outcome, 'response_structure.itinerary_count'),
            ])),
            'validating_carrier' => $linkage['validating_carrier'] ?? null,
            'booking_codes' => $linkage['booking_codes'] ?? null,
            'seats_available' => $linkage['seats_available'] ?? null,
            'ticketing_limit' => $linkage['ticketing_limit'] ?? null,
            'mismatches' => $comparison['mismatches'] ?? [],
            'supplier_status' => ($outcome['success'] ?? false) ? 'revalidated' : 'failed',
        ]);

        $booking->meta = $meta;
        $booking->fare_revalidated_at = ($outcome['success'] ?? false) ? $now : $booking->fare_revalidated_at;

        $freshTotal = (float) ($comparison['fresh_total'] ?? 0);
        if ($freshTotal > 0) {
            $booking->revalidated_fare_total = $freshTotal;
        }

        $booking->save();

        Log::info('sabre.gds_revalidation.persisted', [
            'booking_id' => $booking->id,
            'success' => ($outcome['success'] ?? false) === true,
            'reason_code' => (string) ($outcome['reason_code'] ?? ''),
        ]);
    }

    private function defaultRevalidateStyle(): string
    {
        $configured = trim((string) config('suppliers.sabre.revalidate_payload_style', ''));

        return $configured !== '' ? $configured : 'iati_like_bfm_revalidate_v1';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOfferFromBooking(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['offer_snapshot'] ?? null) ? $meta['offer_snapshot'] : [];
        if ($snapshot !== []) {
            return $snapshot;
        }

        $context = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];

        return is_array($context['offer'] ?? null) ? $context['offer'] : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function passengersFromBooking(Booking $booking): array
    {
        $booking->loadMissing('passengers');
        $rows = [];
        foreach ($booking->passengers as $passenger) {
            $rows[] = [
                'type' => (string) ($passenger->passenger_type ?? 'ADT'),
                'first_name' => (string) ($passenger->first_name ?? ''),
                'last_name' => (string) ($passenger->last_name ?? ''),
            ];
        }

        if ($rows === []) {
            $rows[] = ['type' => 'ADT', 'first_name' => 'TEST', 'last_name' => 'PASSENGER'];
        }

        return $rows;
    }
}
