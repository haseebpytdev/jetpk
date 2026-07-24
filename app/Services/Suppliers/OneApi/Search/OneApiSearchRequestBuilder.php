<?php

namespace App\Services\Suppliers\OneApi\Search;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Models\SupplierConnection;

class OneApiSearchRequestBuilder
{
    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $config = $this->configResolver->resolve($connection);
        $this->validateRequest($request);

        $cabinCode = $this->mapCabin($request->cabin);
        $departureDate = $request->departure_date;
        $returnDate = $request->return_date;
        $isReturn = $request->trip_type === 'return' && $returnDate !== null && $returnDate !== '';

        $origin = strtoupper($request->origin);
        $destination = strtoupper($request->destination);

        $searchOnds = [[
            'origin' => ['code' => $origin, 'locationType' => 'AIRPORT'],
            'destination' => ['code' => $destination, 'locationType' => 'AIRPORT'],
            'searchStartDate' => $departureDate,
            'searchEndDate' => $departureDate,
            'preferredDate' => $departureDate,
            'bookingType' => 'NORMAL',
            'cabinClass' => $cabinCode,
            'ondRef' => $origin.'/'.$destination,
            'interlineQuoteDetails' => null,
        ]];

        if ($isReturn) {
            $searchOnds[] = [
                'origin' => ['code' => $destination, 'locationType' => 'AIRPORT'],
                'destination' => ['code' => $origin, 'locationType' => 'AIRPORT'],
                'searchStartDate' => $returnDate,
                'searchEndDate' => $returnDate,
                'preferredDate' => $returnDate,
                'bookingType' => 'NORMAL',
                'cabinClass' => $cabinCode,
                'ondRef' => $destination.'/'.$origin,
                'interlineQuoteDetails' => null,
            ];
        }

        return [
            'searchOnds' => $searchOnds,
            'paxCounts' => [
                ['paxType' => 'ADT', 'count' => $request->adults],
                ['paxType' => 'CHD', 'count' => $request->children],
                ['paxType' => 'INF', 'count' => $request->infants],
            ],
            'isReturn' => $isReturn,
            'currencyCode' => (string) $config['agent_preferred_currency'],
            'cabinClass' => $cabinCode,
            'metaData' => [
                'agentCode' => (string) $config['agent_code'],
                'country' => (string) $config['pos_country'],
                'station' => (string) $config['pos_station'],
                'salesChannel' => (string) $config['sales_channel'],
                'otherMetaData' => [
                    ['key' => 'SKIP_OND_MERGE', 'value' => 'false'],
                ],
            ],
        ];
    }

    private function validateRequest(FlightSearchRequestData $request): void
    {
        if ($request->adults < 1) {
            throw new OneApiValidationException('validation_error', 422, 'At least one adult is required.');
        }
        if ($request->infants > $request->adults) {
            throw new OneApiValidationException('validation_error', 422, 'Infants cannot exceed adults.');
        }
        if ($request->trip_type === 'multi_city') {
            throw new OneApiValidationException('validation_error', 422, 'Multi-city is not supported for One API.');
        }
        if ($request->trip_type === 'return') {
            if ($request->return_date === null || $request->return_date === '') {
                throw new OneApiValidationException('validation_error', 422, 'Return date is required.');
            }
            if ($request->return_date < $request->departure_date) {
                throw new OneApiValidationException('validation_error', 422, 'Return date must be on or after departure.');
            }
        }
        foreach ([$request->origin, $request->destination] as $code) {
            if (! preg_match('/^[A-Z]{3}$/', strtoupper($code))) {
                throw new OneApiValidationException('validation_error', 422, 'Invalid airport code.');
            }
        }
        if ($request->departure_date < now()->toDateString()) {
            throw new OneApiValidationException('validation_error', 422, 'Departure date cannot be in the past.');
        }
    }

    private function mapCabin(string $cabin): string
    {
        return match (strtolower(trim($cabin))) {
            'business', 'c', 'j' => 'C',
            'first', 'f' => 'F',
            default => 'Y',
        };
    }
}
