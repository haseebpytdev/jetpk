<?php

namespace App\Services\Suppliers\OneApi\Search;

use App\Services\Suppliers\OneApi\Support\OneApiCarrierFilter;

/**
 * Parses One API REST search JSON into normalized option structures.
 */
class OneApiSearchResponseParser
{
    public function __construct(
        private readonly OneApiCarrierFilter $carrierFilter,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    public function parse(array $response, array $config, string $correlationId): array
    {
        $combinations = $response['ondWiseFlightCombinations'] ?? [];
        if (! is_array($combinations)) {
            return [];
        }

        $options = [];
        foreach ($combinations as $ondRef => $ondData) {
            if (! is_array($ondData)) {
                continue;
            }
            $dateWise = $ondData['dateWiseFlightCombinations'] ?? [];
            if (! is_array($dateWise)) {
                continue;
            }
            foreach ($dateWise as $date => $dateBlock) {
                if (! is_array($dateBlock)) {
                    continue;
                }
                $flightOptions = $dateBlock['flightOptions'] ?? [];
                if (! is_array($flightOptions)) {
                    continue;
                }
                foreach ($flightOptions as $optionIndex => $flightOption) {
                    if (! is_array($flightOption)) {
                        continue;
                    }
                    $availability = strtoupper((string) ($flightOption['availabilityStatus'] ?? ''));
                    $cabinPrices = $flightOption['cabinPrices'] ?? [];
                    if ($availability === 'NOT_AVAILABLE' || ! is_array($cabinPrices) || $cabinPrices === []) {
                        continue;
                    }

                    $segments = $this->parseSegments($flightOption['flightSegments'] ?? [], (string) $ondRef);
                    if ($segments === []) {
                        continue;
                    }
                    if (! $this->carrierFilter->itineraryPermitted($segments, $config)) {
                        continue;
                    }

                    $options[] = [
                        'ond_ref' => (string) $ondRef,
                        'travel_date' => (string) $date,
                        'option_index' => (int) $optionIndex,
                        'segments' => $segments,
                        'cabin_prices' => $cabinPrices,
                        'correlation_id' => $correlationId,
                    ];
                }
            }
        }

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSegments(mixed $rawSegments, string $ondRef): array
    {
        if (! is_array($rawSegments)) {
            return [];
        }

        $out = [];
        foreach ($rawSegments as $index => $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $flightNumber = trim((string) ($segment['flightNumber'] ?? ''));
            $origin = (string) data_get($segment, 'origin.airportCode', data_get($segment, 'origin.code', ''));
            $destination = (string) data_get($segment, 'destination.airportCode', data_get($segment, 'destination.code', ''));
            $marketing = strtoupper(trim((string) ($segment['marketingCarrier'] ?? $segment['marketing_carrier'] ?? '')));
            $operating = strtoupper(trim((string) ($segment['operatingCarrier'] ?? $segment['operating_carrier'] ?? $marketing)));

            $out[] = [
                'segment_index' => (int) $index,
                'ond_ref' => $ondRef,
                'flight_number' => $flightNumber,
                'marketing_carrier' => $marketing,
                'operating_carrier' => $operating,
                'origin' => strtoupper($origin),
                'destination' => strtoupper($destination),
                'departure_local' => (string) ($segment['departureDateTimeLocal'] ?? ''),
                'arrival_local' => (string) ($segment['arrivalDateTimeLocal'] ?? ''),
                'departure_zulu' => (string) ($segment['departureDateTimeZulu'] ?? ''),
                'arrival_zulu' => (string) ($segment['arrivalDateTimeZulu'] ?? ''),
                'segment_code' => (string) ($segment['segmentCode'] ?? ''),
                'flight_segment_id' => (string) ($segment['flightSegmentId'] ?? ''),
                'aircraft_model' => (string) ($segment['modelName'] ?? $segment['aircraftModel'] ?? ''),
                'transport_mode' => (string) ($segment['transportMode'] ?? 'FLIGHT'),
            ];
        }

        return $out;
    }
}
