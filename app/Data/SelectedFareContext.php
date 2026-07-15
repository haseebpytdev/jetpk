<?php

namespace App\Data;

use App\Models\Booking;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;

/**
 * Authoritative selected fare context for search → validation → checkout → booking payload.
 */
final class SelectedFareContext
{
    /**
     * @param  array<string, mixed>|null  $supplierBookingContext
     */
    public function __construct(
        public ?string $supplierProvider = null,
        public ?int $supplierConnectionId = null,
        public ?string $distributionChannel = null,
        public ?string $searchId = null,
        public ?string $offerId = null,
        public ?string $supplierOfferId = null,
        public ?SelectedFareOption $selectedFare = null,
        public ?array $supplierBookingContext = null,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function fromBookingMeta(array $meta, ?array $offerSnapshot = null): self
    {
        $offer = is_array($offerSnapshot) ? $offerSnapshot : (
            is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot']
            : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : [])
        );

        $intent = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : [];

        if ($intent === [] && trim((string) ($meta['fare_option_key'] ?? '')) === '') {
            $intent = FlightOfferDisplayPresenter::buildSyntheticDefaultFareChoiceOption($offer);
            if ($intent !== []) {
                $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($intent, $offer);
            }
        }

        $selected = $intent !== [] ? SelectedFareOption::fromIntentArray($intent, $offer) : null;

        $handoff = is_array($meta['sabre_booking_context'] ?? null)
            ? $meta['sabre_booking_context']
            : (is_array(data_get($offer, 'raw_payload.sabre_booking_context')) ? data_get($offer, 'raw_payload.sabre_booking_context') : null);

        return new self(
            supplierProvider: strtolower(trim((string) ($meta['supplier_provider'] ?? $offer['supplier_provider'] ?? ''))) ?: null,
            supplierConnectionId: (int) ($meta['supplier_connection_id'] ?? $offer['supplier_connection_id'] ?? 0) ?: null,
            distributionChannel: strtolower(trim((string) (
                $meta['distribution_channel']
                ?? data_get($handoff, 'distribution_channel')
                ?? $offer['distribution_channel']
                ?? ''
            ))) ?: null,
            searchId: trim((string) ($meta['search_id'] ?? '')) ?: null,
            offerId: trim((string) ($meta['original_offer_id'] ?? $offer['offer_id'] ?? $offer['id'] ?? '')) ?: null,
            supplierOfferId: trim((string) ($offer['supplier_offer_id'] ?? '')) ?: null,
            selectedFare: $selected,
            supplierBookingContext: $handoff,
        );
    }

    public static function fromBooking(Booking $booking): self
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return self::fromBookingMeta($meta);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeSummary(): array
    {
        $fare = $this->selectedFare;

        return array_filter([
            'supplier_provider' => $this->supplierProvider,
            'supplier_connection_id' => $this->supplierConnectionId,
            'distribution_channel' => $this->distributionChannel,
            'search_id' => $this->searchId,
            'offer_id' => $this->offerId,
            'supplier_offer_id' => $this->supplierOfferId,
            'fare_option_key' => $fare?->fareOptionKey,
            'brand_code' => $fare?->brandCode,
            'brand_name' => $fare?->brandName,
            'selected_price_total' => $fare?->selectedPriceTotal,
            'baggage_summary' => $fare?->baggageSummary,
            'fare_basis_codes_by_segment' => $fare?->fareBasisCodesBySegment,
            'booking_classes_by_segment' => $fare?->bookingClassesBySegment,
            'segment_count' => $fare?->segmentCount,
            'branded_fare_supported' => $fare?->brandedFareSupported,
        ], static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }
}
