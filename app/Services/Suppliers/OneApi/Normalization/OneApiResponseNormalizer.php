<?php

namespace App\Services\Suppliers\OneApi\Normalization;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\Airline;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Search\OneApiSearchResponseParser;
use Illuminate\Support\Str;

class OneApiResponseNormalizer
{
    public function __construct(
        private readonly OneApiSearchResponseParser $searchResponseParser,
        private readonly OneApiOfferTokenSigner $offerTokenSigner,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     * @return list<NormalizedFlightOfferData>
     */
    public function normalizeSearchResponse(
        array $response,
        SupplierConnection $connection,
        array $config,
        string $correlationId,
        int $adults,
        int $children,
        int $infants,
        string $tripType,
    ): array {
        $parsed = $this->searchResponseParser->parse($response, $config, $correlationId);
        $offers = [];
        $max = (int) ($config['max_search_results'] ?? 200);

        foreach ($parsed as $option) {
            $offer = $this->buildOffer($option, $connection, $config, $adults, $children, $infants, $tripType);
            if ($offer !== null) {
                $offers[] = $offer;
            }
            if (count($offers) >= $max) {
                break;
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $option
     */
    private function buildOffer(
        array $option,
        SupplierConnection $connection,
        array $config,
        int $adults,
        int $children,
        int $infants,
        string $tripType,
    ): ?NormalizedFlightOfferData {
        $segments = $option['segments'] ?? [];
        if (! is_array($segments) || $segments === []) {
            return null;
        }

        $first = $segments[0];
        $last = $segments[array_key_last($segments)];
        $marketing = strtoupper((string) ($first['marketing_carrier'] ?? 'G9'));
        $airline = Airline::query()->where('iata_code', $marketing)->first();
        $airlineName = $airline?->name ?? $this->defaultAirlineName($marketing);

        $cabinPrices = is_array($option['cabin_prices'] ?? null) ? $option['cabin_prices'] : [];
        $priceRow = $cabinPrices[0] ?? [];
        $currency = (string) ($priceRow['currency'] ?? $config['agent_preferred_currency'] ?? 'AED');
        $amount = (float) ($priceRow['price'] ?? 0);
        $fareFamily = (string) ($priceRow['fareFamily'] ?? '');
        $cabin = strtolower((string) ($priceRow['cabinClass'] ?? 'Y')) === 'c' ? 'business' : 'economy';

        $offerId = 'one_api_'.Str::lower(Str::random(12));
        $expiresAt = now()->addMinutes(30)->toIso8601String();

        $tokenPayload = [
            'supplier' => SupplierProvider::OneApi->value,
            'connection_id' => $connection->id,
            'segments' => $segments,
            'cabin_prices' => $cabinPrices,
            'pax' => ['adt' => $adults, 'chd' => $children, 'inf' => $infants],
            'currency' => $currency,
            'trip_type' => $tripType,
            'ond_ref' => $option['ond_ref'] ?? '',
            'expires_at' => strtotime($expiresAt),
        ];
        $signedToken = $this->offerTokenSigner->sign($tokenPayload);

        $normalizedSegments = [];
        foreach ($segments as $seg) {
            $normalizedSegments[] = [
                'origin' => $seg['origin'] ?? '',
                'destination' => $seg['destination'] ?? '',
                'departure_at' => $seg['departure_local'] ?? '',
                'arrival_at' => $seg['arrival_local'] ?? '',
                'flight_number' => $seg['flight_number'] ?? '',
                'marketing_carrier' => $seg['marketing_carrier'] ?? '',
                'operating_carrier' => $seg['operating_carrier'] ?? '',
            ];
        }

        return new NormalizedFlightOfferData(
            offer_id: $offerId,
            supplier_provider: SupplierProvider::OneApi->value,
            supplier_connection_id: $connection->id,
            airline_code: $marketing,
            airline_name: $airlineName,
            flight_number: (string) ($first['flight_number'] ?? null),
            origin: (string) ($first['origin'] ?? ''),
            destination: (string) ($last['destination'] ?? ''),
            departure_at: (string) ($first['departure_local'] ?? ''),
            arrival_at: (string) ($last['arrival_local'] ?? ''),
            duration_minutes: 0,
            stops: max(0, count($segments) - 1),
            cabin: $cabin,
            fare_family: $fareFamily !== '' ? $fareFamily : null,
            refundable: false,
            seats_left: null,
            segments: $normalizedSegments,
            baggage: new BaggageAllowanceData(summary: null),
            fare_breakdown: new FareBreakdownData(
                base_fare: $amount,
                taxes: 0,
                supplier_fees: 0,
                supplier_total: $amount,
                currency: $currency,
                passenger_counts: ['adults' => $adults, 'children' => $children, 'infants' => $infants],
            ),
            expires_at: $expiresAt,
            raw_payload: [
                'provider_context' => [
                    'signed_offer_token' => $signedToken,
                    'search_option' => $option,
                    'price_confirmed' => false,
                    'indicative_search_total' => $amount,
                ],
                'customer_display_fields' => [
                    'airline_logo_path' => $airline?->logo_path,
                ],
            ],
            marketing_carrier_chain: array_values(array_unique(array_column($segments, 'marketing_carrier'))),
            operating_carrier_chain: array_values(array_unique(array_column($segments, 'operating_carrier'))),
            primary_display_carrier: $marketing,
        );
    }

    private function defaultAirlineName(string $code): string
    {
        return match ($code) {
            'G9' => 'Air Arabia',
            '3L', '9P' => 'Fly Jinnah',
            default => $code,
        };
    }
}
