<?php

namespace App\Support\Sabre\Scenario;

/**
 * Immutable post-revalidation booking offer context for QR unticketed lifecycle (safe fields only).
 */
final class SabreGdsAuthoritativeRevalidatedBookingContext
{
    public const META_KEY = 'sabre_authoritative_revalidated_booking_context';

    public const SOURCE_REVALIDATION_EVIDENCE = 'scenario_revalidation_evidence';

    public const TRANSITION_ACCEPTED = 'authoritative_revalidation_identifier_transition_accepted';

    public const TRANSITION_REJECTED = 'authoritative_revalidation_identifier_transition_rejected';

    /**
     * @param  array<string, mixed>  $normalizedOfferSnapshot
     * @param  array<string, mixed>  $safeDiagnostics
     */
    public function __construct(
        public readonly array $normalizedOfferSnapshot,
        public readonly array $safeDiagnostics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'normalized_offer_snapshot' => $this->normalizedOfferSnapshot,
            'safe_diagnostics' => $this->safeDiagnostics,
        ];
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    public static function fromArray(array $bag): ?self
    {
        $snap = is_array($bag['normalized_offer_snapshot'] ?? null) ? $bag['normalized_offer_snapshot'] : [];
        $diag = is_array($bag['safe_diagnostics'] ?? null) ? $bag['safe_diagnostics'] : [];
        if ($snap === []) {
            return null;
        }

        return new self($snap, $diag);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function mergeIntoBookingMeta(array $meta): array
    {
        $meta[self::META_KEY] = $this->safeDiagnostics;
        $meta['normalized_offer_snapshot'] = $this->normalizedOfferSnapshot;
        $meta['flight_offer_snapshot'] = $this->normalizedOfferSnapshot;
        $meta['validated_offer_snapshot'] = $this->normalizedOfferSnapshot;
        $meta['offer_snapshot'] = $this->normalizedOfferSnapshot;
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $handoff['offer'] = $this->normalizedOfferSnapshot;
        $handoff['ready_for_booking_payload'] = ($this->safeDiagnostics['ready_for_booking_payload'] ?? false) === true;
        if (is_array($this->safeDiagnostics['booking_classes_by_segment'] ?? null)) {
            $handoff['booking_classes_by_segment'] = $this->safeDiagnostics['booking_classes_by_segment'];
        }
        if (is_array($this->safeDiagnostics['fare_basis_codes_by_segment'] ?? null)) {
            $handoff['fare_basis_codes_by_segment'] = $this->safeDiagnostics['fare_basis_codes_by_segment'];
        }
        $meta['sabre_booking_context'] = $handoff;
        $meta['authoritative_revalidation_handoff_applied_at'] = $this->safeDiagnostics['freshness_timestamp'] ?? now()->toIso8601String();

        return $meta;
    }
}
