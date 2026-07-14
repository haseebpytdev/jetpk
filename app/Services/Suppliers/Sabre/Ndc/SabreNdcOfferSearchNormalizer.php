<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Data\FlightSearchRequestData;
use App\Data\NormalizedFlightOfferData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Support\Suppliers\SabreNdcGroupedItineraryDiagnostics;

/**
 * Normalize Sabre NDC v5 shop responses into OTA offers with {@code distribution_channel=ndc}.
 *
 * Delegates GIR parsing to the GDS normalizer but strips full supplier JSON from public-facing payloads.
 */
final class SabreNdcOfferSearchNormalizer
{
    public function __construct(
        private readonly SabreFlightSearchNormalizer $gdsNormalizer,
        private readonly SabreNdcGroupedItineraryDiagnostics $groupedItineraryDiagnostics,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     * @return list<NormalizedFlightOfferData>
     */
    public function normalize(
        array $response,
        SupplierConnection $connection,
        FlightSearchRequestData $request,
    ): array {
        $offers = $this->gdsNormalizer->normalize($response, $connection, $request);
        $normalized = [];

        foreach ($offers as $offer) {
            $normalized[] = $this->applyNdcChannel($offer);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function responseShapeSummary(array $response): array
    {
        return $this->groupedItineraryDiagnostics->summarize($response);
    }

    private function applyNdcChannel(NormalizedFlightOfferData $offer): NormalizedFlightOfferData
    {
        $arr = $offer->toArray();
        $arr['distribution_channel'] = 'ndc';

        $raw = is_array($arr['raw_payload'] ?? null) ? $arr['raw_payload'] : [];
        unset($raw['groupedItineraryResponse'], $raw['ndc_raw_response']);

        $shopContext = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $identifiers = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];

        $raw['sabre_ndc_context'] = array_filter([
            'distribution_channel' => 'ndc',
            'offer_id' => $offer->offer_id,
            'owner_code' => $offer->validating_carrier ?? $offer->airline_code,
            'offer_item_id' => trim((string) ($identifiers['offer_item_id'] ?? $shopContext['offer_item_id'] ?? '')),
            'fare_reference' => trim((string) ($identifiers['fare_reference'] ?? $shopContext['fare_reference'] ?? '')),
            'offer_expiry' => $offer->expires_at,
            'shop_endpoint_path' => (string) config('suppliers.sabre.ndc.offer_shop_path', '/v5/offers/shop'),
        ], static fn ($v) => $v !== null && $v !== '');

        $arr['raw_payload'] = $raw;

        return NormalizedFlightOfferData::fromArray($arr);
    }
}
