<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException;

class EjmiralAerostSearchService
{
    public function __construct(
        private readonly EjmiralAerostConfigResolver $configResolver,
        private readonly EjmiralAerostClient $client,
        private readonly EjmiralAerostResponseNormalizer $normalizer,
    ) {}

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        $config = $this->configResolver->resolve($connection);
        if (! $config['live_search_enabled'] && ! app()->runningUnitTests()) {
            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::EjmiralAerost,
                offers: [],
                warnings: ['EJMIRAL AEROST live search is disabled.'],
                meta: ['error_code' => 'live_search_disabled', 'connection_id' => $connection->id],
            );
        }

        $payload = $this->buildSearchPayload($request);
        $response = $this->client->post($connection, 'res/flights', $payload, [
            'operation' => 'search',
        ]);

        $offers = $this->normalizer->normalizeSearchOffers($response['body'], (int) $connection->id);

        return new FlightSearchResultData(
            supplier_provider: SupplierProvider::EjmiralAerost,
            offers: $offers,
            warnings: [],
            meta: [
                'connection_id' => $connection->id,
                'offer_count' => count($offers),
                'has_nld' => (($response['body']['NLD'] ?? $response['body']['nld'] ?? '') !== ''),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSearchPayload(FlightSearchRequestData $request): array
    {
        $origin = strtoupper(trim($request->origin));
        $destination = strtoupper(trim($request->destination));
        if (! preg_match('/^[A-Z]{3}$/', $origin) || ! preg_match('/^[A-Z]{3}$/', $destination)) {
            throw new EjmiralAerostValidationException('validation_error', 422, 'Invalid airport code.');
        }
        if ($request->departure_date < now()->toDateString()) {
            throw new EjmiralAerostValidationException('validation_error', 422, 'Departure date cannot be in the past.');
        }

        $trip = strtolower((string) $request->trip_type);
        $isReturn = in_array($trip, ['return', 'roundtrip', 'rt'], true);

        $payload = [
            'adult' => max(1, (int) $request->adults),
            'child' => max(0, (int) $request->children),
            'infant' => max(0, (int) $request->infants),
            'trip_type' => $isReturn ? 'RT' : 'OW',
            'departure_city' => $origin,
            'arrival_city' => $destination,
            'departure_date' => $request->departure_date,
        ];

        if ($isReturn) {
            if ($request->return_date === null || $request->return_date === '') {
                throw new EjmiralAerostValidationException('validation_error', 422, 'Return date is required for RT.');
            }
            $payload['return_date'] = $request->return_date;
        }

        return $payload;
    }
}
