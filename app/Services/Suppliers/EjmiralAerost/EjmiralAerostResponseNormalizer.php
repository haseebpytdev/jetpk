<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException;
use Illuminate\Support\Str;

/**
 * Maps AEROST flight search payloads into JetPakistan normalized offers.
 * NLD and acceptance hashes remain opaque inside signed offer tokens.
 */
class EjmiralAerostResponseNormalizer
{
    public function __construct(
        private readonly EjmiralAerostOfferTokenSigner $offerTokenSigner,
    ) {}

    /**
     * @param  array<string, mixed>  $searchBody
     * @return list<NormalizedFlightOfferData>
     */
    public function normalizeSearchOffers(array $searchBody, int $connectionId): array
    {
        $nld = (string) ($searchBody['NLD'] ?? $searchBody['nld'] ?? '');
        $itineraries = $searchBody['itinerary'] ?? $searchBody['itineraries'] ?? $searchBody['flights'] ?? [];
        if (! is_array($itineraries)) {
            return [];
        }

        $offers = [];
        foreach ($itineraries as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $acceptance = $row['acceptance'] ?? data_get($row, 'classes.0.acceptance') ?? [];
            $acceptanceList = [];
            if (is_array($acceptance)) {
                foreach ($acceptance as $item) {
                    $value = trim((string) $item);
                    if ($value !== '') {
                        $acceptanceList[] = $value;
                    }
                }
            } elseif (trim((string) $acceptance) !== '') {
                $acceptanceList[] = trim((string) $acceptance);
            }
            if ($acceptanceList === []) {
                $fallback = trim((string) ($row['hash'] ?? $row['acceptance_hash'] ?? ''));
                if ($fallback !== '') {
                    $acceptanceList[] = $fallback;
                }
            }

            $total = (float) ($row['total'] ?? $row['fare'] ?? $row['amount'] ?? 0);
            $taxes = (float) ($row['tax'] ?? $row['taxes'] ?? 0);
            $base = (float) ($row['base_fare'] ?? max(0, $total - $taxes));
            $currency = strtoupper((string) ($row['currency'] ?? $searchBody['currency'] ?? 'PKR'));
            $origin = strtoupper((string) ($row['origin'] ?? $row['from'] ?? data_get($row, 'flights.0.origin') ?? ''));
            $destination = strtoupper((string) ($row['destination'] ?? $row['to'] ?? data_get($row, 'flights.0.destination') ?? ''));
            $carrier = strtoupper((string) ($row['operator'] ?? $row['carrier'] ?? data_get($row, 'flights.0.operator') ?? 'XX'));
            $departAt = (string) ($row['departure'] ?? $row['departure_datetime'] ?? data_get($row, 'flights.0.departure') ?? '');
            $arriveAt = (string) ($row['arrival'] ?? $row['arrival_datetime'] ?? data_get($row, 'flights.0.arrival') ?? '');
            $flightNumber = (string) ($row['flight_number'] ?? data_get($row, 'flights.0.flight_number') ?? '');

            $opaque = [
                'nld' => $nld,
                'acceptance' => $acceptanceList,
                'connection_id' => $connectionId,
            ];

            $offerId = 'ejmiral_aerost_'.Str::lower(Str::random(12));
            $signed = $this->offerTokenSigner->sign([
                'supplier' => SupplierProvider::EjmiralAerost->value,
                'offer_id' => $offerId,
                'opaque' => $opaque,
                'expires_at' => time() + 1800,
            ]);

            $segments = [[
                'airline_code' => $carrier,
                'airline_name' => $carrier,
                'operating_airline_code' => $carrier,
                'flight_number' => $flightNumber,
                'origin' => $origin,
                'destination' => $destination,
                'departure_at' => $departAt,
                'arrival_at' => $arriveAt,
            ]];

            $offers[] = new NormalizedFlightOfferData(
                offer_id: $offerId,
                supplier_provider: SupplierProvider::EjmiralAerost->value,
                supplier_connection_id: $connectionId,
                airline_code: $carrier,
                airline_name: $carrier,
                flight_number: $flightNumber !== '' ? $carrier.$flightNumber : null,
                origin: $origin,
                destination: $destination,
                departure_at: $departAt,
                arrival_at: $arriveAt,
                duration_minutes: 0,
                stops: max(0, count($segments) - 1),
                cabin: strtolower((string) ($row['cabin'] ?? 'economy')),
                fare_family: isset($row['class']) ? (string) $row['class'] : null,
                refundable: false,
                seats_left: isset($row['seats']) ? (int) $row['seats'] : null,
                segments: $segments,
                baggage: new BaggageAllowanceData(
                    summary: is_array($row['features'] ?? null) ? implode(', ', array_map('strval', $row['features'])) : null,
                ),
                fare_breakdown: new FareBreakdownData(
                    base_fare: $base,
                    taxes: $taxes,
                    supplier_fees: 0.0,
                    supplier_total: $total,
                    currency: $currency,
                    breakdown_reconciled: true,
                ),
                raw_reference: $offerId,
                raw_payload: [
                    'provider_context' => [
                        'ejmiral_aerost' => [
                            'has_nld' => $nld !== '',
                            'has_acceptance' => $acceptanceList !== [],
                            'offer_token' => $signed,
                            'index' => (int) $index,
                        ],
                    ],
                ],
                marketing_carrier_chain: [$carrier],
                operating_carrier_chain: [$carrier],
                primary_display_carrier: $carrier,
                all_airline_codes: [$carrier],
            );
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $signedPayload
     * @return array{nld: string, acceptance: list<string>}
     */
    public function opaqueFromSignedPayload(array $signedPayload): array
    {
        $opaque = is_array($signedPayload['opaque'] ?? null) ? $signedPayload['opaque'] : [];
        $nld = (string) ($opaque['nld'] ?? '');
        $acceptance = is_array($opaque['acceptance'] ?? null)
            ? array_values(array_map('strval', $opaque['acceptance']))
            : [];
        if ($nld === '' || $acceptance === []) {
            throw new EjmiralAerostValidationException('opaque_missing', 422, 'AEROST NLD/acceptance context missing.');
        }

        return ['nld' => $nld, 'acceptance' => $acceptance];
    }
}
