<?php

namespace App\Support\GroupTicketing;

use App\Services\Suppliers\AlHaider\AlHaiderPackageNormalizer;

/**
 * CLI-only sanitized field summary for Al-Haider group inventory payloads.
 */
class AlHaiderProviderPayloadInspector
{
    /** @var list<string> */
    private const MEAL_KEYS = ['meal', 'food', 'refreshment', 'meal_included'];

    /** @var list<string> */
    private const DEPARTURE_TIME_KEYS = ['dept_time', 'departure_time'];

    /** @var list<string> */
    private const ARRIVAL_TIME_KEYS = ['arv_time', 'arrival_time'];

    /** @var list<string> */
    private const ARRIVAL_DATE_KEYS = ['arv_date', 'return_date'];

    /** @var list<string> */
    private const SEAT_KEYS = [
        'available_no_of_pax',
        'available_pax',
        'available_seats',
        'remaining_seats',
        'seats',
    ];

    /** @var list<string> */
    private const PRICE_KEYS = ['price', 'price_adult'];

    /** @var list<string> */
    private const NORMALIZER_EXPECTED = [
        'id',
        'sector',
        'dept_date',
        'price',
        'available_no_of_pax',
        'meal',
        'baggage',
        'details',
    ];

    public function __construct(
        private readonly AlHaiderPackageNormalizer $normalizer,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     top_level_keys: array<string, int>,
     *     field_matrix: array<string, array{present: bool, example: ?string}>,
     *     missing_expected: list<string>,
     *     normalized_samples: list<array<string, mixed>>
     * }
     */
    public function inspect(array $rows, int $limit = 5): array
    {
        $sample = array_slice($rows, 0, max(1, $limit));
        $topLevelKeys = [];

        foreach ($sample as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $key) {
                if (is_string($key)) {
                    $topLevelKeys[$key] = ($topLevelKeys[$key] ?? 0) + 1;
                }
            }
        }

        ksort($topLevelKeys);

        $airlineMap = [];
        $fieldMatrix = [
            'departure_time' => $this->probeDepartureTime($sample),
            'arrival_time' => $this->probeArrivalTime($sample),
            'arrival_date' => $this->probeArrivalDate($sample),
            'meal' => $this->probeMeal($sample),
            'flight_number' => $this->probeFlightNumber($sample),
            'airline' => $this->probeAirline($sample),
            'origin_destination' => $this->probeOriginDestination($sample),
            'baggage' => $this->probeBaggage($sample),
            'fare_price' => $this->probePrice($sample),
            'available_seats' => $this->probeSeats($sample),
            'package_id' => $this->probePackageId($sample),
        ];

        $missingExpected = [];
        foreach (self::NORMALIZER_EXPECTED as $expectedKey) {
            if (($topLevelKeys[$expectedKey] ?? 0) === 0 && ! $this->keyPresentInNested($sample, $expectedKey)) {
                $missingExpected[] = $expectedKey;
            }
        }

        $normalizedSamples = [];
        foreach ($sample as $row) {
            if (! is_array($row)) {
                continue;
            }
            $package = $this->normalizer->normalize($row, $airlineMap);
            $normalizedSamples[] = $this->sanitizeNormalizedSample($package->toArray());
        }

        return [
            'top_level_keys' => $topLevelKeys,
            'field_matrix' => $fieldMatrix,
            'missing_expected' => $missingExpected,
            'normalized_samples' => $normalizedSamples,
        ];
    }

    /**
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    public function sanitizeNormalizedSample(array $sample): array
    {
        unset($sample['airline_logo_url']);

        return $sample;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeDepartureTime(array $rows): array
    {
        foreach ($rows as $row) {
            foreach (self::DEPARTURE_TIME_KEYS as $key) {
                $value = $this->stringValue($row[$key] ?? null);
                if ($value !== null) {
                    return ['present' => true, 'example' => $this->sanitizeExample($value)];
                }
            }
            foreach ($this->legRows($row) as $leg) {
                foreach (self::DEPARTURE_TIME_KEYS as $key) {
                    $value = $this->stringValue($leg[$key] ?? null);
                    if ($value !== null) {
                        return ['present' => true, 'example' => $this->sanitizeExample($value)];
                    }
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeArrivalTime(array $rows): array
    {
        foreach ($rows as $row) {
            foreach (self::ARRIVAL_TIME_KEYS as $key) {
                $value = $this->stringValue($row[$key] ?? null);
                if ($value !== null) {
                    return ['present' => true, 'example' => $this->sanitizeExample($value)];
                }
            }
            foreach ($this->legRows($row) as $leg) {
                foreach (self::ARRIVAL_TIME_KEYS as $key) {
                    $value = $this->stringValue($leg[$key] ?? null);
                    if ($value !== null) {
                        return ['present' => true, 'example' => $this->sanitizeExample($value)];
                    }
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeArrivalDate(array $rows): array
    {
        foreach ($rows as $row) {
            foreach (self::ARRIVAL_DATE_KEYS as $key) {
                $value = $this->stringValue($row[$key] ?? null);
                if ($value !== null) {
                    return ['present' => true, 'example' => $this->sanitizeExample($value)];
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeMeal(array $rows): array
    {
        foreach ($rows as $row) {
            foreach (self::MEAL_KEYS as $key) {
                $value = $this->stringValue($row[$key] ?? null);
                if ($value !== null) {
                    return ['present' => true, 'example' => $this->sanitizeExample($value)];
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeFlightNumber(array $rows): array
    {
        foreach ($rows as $row) {
            foreach ($this->legRows($row) as $leg) {
                $value = $this->stringValue($leg['flight_no'] ?? null);
                if ($value !== null) {
                    return ['present' => true, 'example' => $this->sanitizeExample($value)];
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeAirline(array $rows): array
    {
        foreach ($rows as $row) {
            if (isset($row['airline']) && is_array($row['airline'])) {
                $name = $this->stringValue($row['airline']['airline_name'] ?? $row['airline']['name'] ?? null);

                return ['present' => true, 'example' => $name ?? '(nested airline object)'];
            }
            if (isset($row['airline_id']) && is_numeric($row['airline_id'])) {
                return ['present' => true, 'example' => 'airline_id='.(int) $row['airline_id']];
            }
            $value = $this->stringValue($row['airline'] ?? null);
            if ($value !== null) {
                return ['present' => true, 'example' => $this->sanitizeExample($value)];
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeOriginDestination(array $rows): array
    {
        foreach ($rows as $row) {
            $sector = $this->stringValue($row['sector'] ?? null);
            if ($sector !== null) {
                return ['present' => true, 'example' => $this->sanitizeExample($sector)];
            }
            foreach ($this->legRows($row) as $leg) {
                $origin = $this->stringValue($leg['origin'] ?? $leg['from'] ?? null);
                $dest = $this->stringValue($leg['destination'] ?? $leg['to'] ?? null);
                if ($origin !== null || $dest !== null) {
                    return ['present' => true, 'example' => trim(($origin ?? '?').' → '.($dest ?? '?'))];
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeBaggage(array $rows): array
    {
        foreach ($rows as $row) {
            $value = $this->stringValue($row['baggage'] ?? null);
            if ($value !== null) {
                return ['present' => true, 'example' => $this->sanitizeExample($value)];
            }
            foreach ($this->legRows($row) as $leg) {
                $value = $this->stringValue($leg['baggage'] ?? null);
                if ($value !== null) {
                    return ['present' => true, 'example' => $this->sanitizeExample($value)];
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probePrice(array $rows): array
    {
        foreach ($rows as $row) {
            foreach (self::PRICE_KEYS as $key) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    return ['present' => true, 'example' => (string) $row[$key]];
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probeSeats(array $rows): array
    {
        foreach ($rows as $row) {
            foreach (self::SEAT_KEYS as $key) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    return ['present' => true, 'example' => (string) (int) $row[$key]];
                }
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probePackageId(array $rows): array
    {
        foreach ($rows as $row) {
            $value = $this->stringValue($row['id'] ?? null);
            if ($value !== null) {
                return ['present' => true, 'example' => $this->sanitizeExample($value)];
            }
        }

        return ['present' => false, 'example' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function keyPresentInNested(array $rows, string $key): bool
    {
        if ($key === 'details') {
            foreach ($rows as $row) {
                if (is_array($row['details'] ?? null) && $row['details'] !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function legRows(array $row): array
    {
        $details = $row['details'] ?? [];
        if (! is_array($details)) {
            return [];
        }

        $legs = [];
        foreach ($details as $leg) {
            if (is_array($leg)) {
                $legs[] = $leg;
            }
        }

        return $legs;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function sanitizeExample(string $value): string
    {
        if (strlen($value) > 80) {
            return substr($value, 0, 77).'...';
        }

        return $value;
    }
}
