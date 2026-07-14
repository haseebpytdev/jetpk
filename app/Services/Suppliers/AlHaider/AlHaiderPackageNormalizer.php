<?php

namespace App\Services\Suppliers\AlHaider;

use App\Data\UmrahGroupPackageData;

/**
 * Maps Al-Haider group inventory rows to normalized public package DTOs.
 */
class AlHaiderPackageNormalizer
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $airlineMap
     */
    public function normalize(array $row, array $airlineMap = []): UmrahGroupPackageData
    {
        $rawId = trim((string) ($row['id'] ?? ''));
        $sector = trim((string) ($row['sector'] ?? ''));
        [$departureCity, $destination] = $this->parseSector($sector);

        $departureDate = $this->firstNonEmpty(
            $row['dept_date'] ?? null,
            $row['departure_date'] ?? null,
        );
        $returnDate = $this->firstNonEmpty(
            $row['arv_date'] ?? null,
            $row['return_date'] ?? null,
        );

        $legs = $this->normalizeLegs($row, $departureDate, $returnDate);
        $seats = $this->normalizeSeatCount($row, $legs);
        $airlineInfo = $this->resolveAirlineInfo($row, $airlineMap);

        $durationDays = $this->durationDays($departureDate, $returnDate);
        $packageType = trim((string) ($row['type'] ?? ''));
        $title = $this->buildTitle($packageType, $sector, $departureCity, $destination);

        $price = (float) ($row['price'] ?? $row['price_adult'] ?? 0);
        $priceChild = isset($row['price_child']) ? (float) $row['price_child'] : null;
        $priceInfant = isset($row['price_infant']) ? (float) $row['price_infant'] : null;
        $currency = trim((string) ($row['currency'] ?? 'PKR')) ?: 'PKR';

        return new UmrahGroupPackageData(
            supplier: 'alhaider',
            supplier_package_id: $rawId,
            public_id: $rawId !== '' ? 'ALH-'.$rawId : '',
            title: $title,
            departure_city: $departureCity,
            destination: $destination,
            sector: $sector !== '' ? $sector : null,
            departure_date: $departureDate,
            return_date: $returnDate,
            duration_days: $durationDays,
            airline: $airlineInfo['name'],
            airline_logo_url: $airlineInfo['logo'],
            package_type: $packageType !== '' ? $packageType : null,
            price: $price,
            price_child: $priceChild,
            price_infant: $priceInfant,
            currency: $currency,
            availability_status: $seats > 0 ? 'available' : 'limited',
            seats_available: $seats,
            baggage: $this->firstNonEmpty($row['baggage'] ?? null, $legs[0]['baggage'] ?? null),
            meal: $this->normalizeMeal($row['meal'] ?? null),
            legs: $legs,
            makkah_hotel: null,
            madinah_hotel: null,
            included_services: [],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $airlineMap
     * @return list<UmrahGroupPackageData>
     */
    public function normalizeMany(array $rows, array $airlineMap = []): array
    {
        $packages = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $packages[] = $this->normalize($row, $airlineMap);
        }

        return $packages;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<int, array<string, mixed>>
     */
    public function airlineMapFromResponse(array $response): array
    {
        $map = [];
        $airlines = $response['airlines'] ?? [];
        if (! is_array($airlines)) {
            return $map;
        }

        foreach ($airlines as $airline) {
            if (! is_array($airline) || ! isset($airline['id'])) {
                continue;
            }
            $id = (int) $airline['id'];
            $map[$id] = [
                'name' => (string) ($airline['airline_name'] ?? $airline['name'] ?? $airline['short_name'] ?? 'Airline'),
                'short_name' => (string) ($airline['short_name'] ?? ''),
                'logo' => isset($airline['logo_url']) ? (string) $airline['logo_url'] : null,
            ];
        }

        return $map;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseSector(string $sector): array
    {
        $sector = trim($sector);
        if ($sector === '') {
            return [null, null];
        }

        foreach (['-', '–', ' to ', ' TO ', '/'] as $separator) {
            if (str_contains($sector, $separator)) {
                $parts = array_values(array_filter(array_map('trim', explode($separator, $sector))));
                if (count($parts) >= 2) {
                    return [$parts[0], $parts[1]];
                }
            }
        }

        return [$sector, null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{name: ?string, logo: ?string}
     */
    private function resolveAirlineInfo(array $row, array $airlineMap): array
    {
        if (isset($row['airline']) && is_array($row['airline'])) {
            return [
                'name' => (string) ($row['airline']['airline_name'] ?? $row['airline']['name'] ?? $row['airline']['short_name'] ?? 'Airline'),
                'logo' => isset($row['airline']['logo_url']) ? (string) $row['airline']['logo_url'] : null,
            ];
        }

        $airlineId = (int) ($row['airline_id'] ?? 0);
        if ($airlineId > 0 && isset($airlineMap[$airlineId])) {
            return [
                'name' => (string) ($airlineMap[$airlineId]['name'] ?? 'Airline'),
                'logo' => $airlineMap[$airlineId]['logo'] ?? null,
            ];
        }

        if (is_string($row['airline'] ?? null) && trim($row['airline']) !== '') {
            return ['name' => trim($row['airline']), 'logo' => null];
        }

        return ['name' => null, 'logo' => null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function normalizeLegs(array $row, ?string $departureDate, ?string $returnDate): array
    {
        $rawDetails = $row['details'] ?? [];
        if (! is_array($rawDetails)) {
            return [];
        }

        $legs = [];
        foreach ($rawDetails as $index => $leg) {
            if (! is_array($leg)) {
                continue;
            }

            $type = $this->normalizeLegType($row, $leg, (int) $index, $departureDate, $returnDate);
            $date = $this->firstNonEmpty(
                $leg['flight_date'] ?? null,
                $leg['departure_date'] ?? null,
                $leg['date'] ?? null,
                $departureDate,
            );
            $origin = $this->firstNonEmpty($leg['origin'] ?? null, $leg['departure_code'] ?? null, $leg['from'] ?? null);
            $destination = $this->firstNonEmpty($leg['destination'] ?? null, $leg['arrival_code'] ?? null, $leg['to'] ?? null);

            $legs[] = [
                'flight_no' => (string) ($leg['flight_no'] ?? ''),
                'origin' => $origin,
                'destination' => $destination,
                'departure_time' => $this->normalizeTime($this->firstNonEmpty($leg['dept_time'] ?? null, $leg['departure_time'] ?? null)),
                'arrival_time' => $this->normalizeTime($this->firstNonEmpty($leg['arv_time'] ?? null, $leg['arrival_time'] ?? null)),
                'date' => $date,
                'type' => $type,
                'label' => $this->legLabel($type, (int) $index),
                'baggage' => isset($leg['baggage']) ? (string) $leg['baggage'] : null,
            ];
        }

        usort($legs, function (array $a, array $b): int {
            $typeOrder = ['outbound' => 0, 'inbound' => 1];
            $left = $typeOrder[$a['type'] ?? ''] ?? 2;
            $right = $typeOrder[$b['type'] ?? ''] ?? 2;

            return [$left, $a['date'] ?? ''] <=> [$right, $b['date'] ?? ''];
        });

        return $legs;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $leg
     */
    private function normalizeLegType(array $row, array $leg, int $index, ?string $departureDate, ?string $returnDate): string
    {
        $type = strtolower(trim((string) ($leg['type'] ?? $leg['segment_type'] ?? '')));
        if (in_array($type, ['outbound', 'inbound'], true)) {
            return $type;
        }

        $legDate = $this->firstNonEmpty($leg['flight_date'] ?? null, $leg['departure_date'] ?? null, $leg['date'] ?? null);
        if ($returnDate && $legDate && $departureDate && $returnDate !== $departureDate && $returnDate === $legDate) {
            return 'inbound';
        }

        return $index === 0 ? 'outbound' : 'outbound';
    }

    private function legLabel(string $type, int $index): string
    {
        if ($type === 'outbound') {
            return $index === 0 ? 'Outbound' : 'Outbound leg '.($index + 1);
        }
        if ($type === 'inbound') {
            return 'Return';
        }

        return 'Leg '.($index + 1);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $legs
     */
    private function normalizeSeatCount(array $row, array $legs): int
    {
        $direct = $this->firstNonEmpty(
            $row['available_no_of_pax'] ?? null,
            $row['available_pax'] ?? null,
            $row['available_seats'] ?? null,
            $row['remaining_seats'] ?? null,
            $row['seats'] ?? null,
        );

        if ($direct !== null && is_numeric($direct)) {
            return max(0, (int) $direct);
        }

        $legSeats = [];
        foreach ($legs as $leg) {
            if (isset($leg['seats']) && is_numeric($leg['seats'])) {
                $legSeats[] = (int) $leg['seats'];
            }
        }

        return $legSeats === [] ? 0 : min($legSeats);
    }

    private function durationDays(?string $departureDate, ?string $returnDate): ?int
    {
        if ($departureDate === null || $returnDate === null) {
            return null;
        }

        $start = strtotime($departureDate);
        $end = strtotime($returnDate);
        if ($start === false || $end === false) {
            return null;
        }

        return max(0, (int) floor(($end - $start) / 86400));
    }

    private function buildTitle(string $packageType, string $sector, ?string $departureCity, ?string $destination): string
    {
        $label = $packageType !== '' ? $packageType : 'Umrah Group';
        if ($sector !== '') {
            return $label.' — '.$sector;
        }
        if ($departureCity && $destination) {
            return $label.' — '.$departureCity.' to '.$destination;
        }

        return $label.' Package';
    }

    private function normalizeMeal(mixed $meal): ?string
    {
        if ($meal === null) {
            return null;
        }

        $value = strtolower(trim((string) $meal));
        if ($value === '') {
            return null;
        }

        if (in_array($value, ['yes', 'included', '1', 'true'], true)) {
            return 'Included';
        }

        return 'Not included';
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}/', $value)) {
            return substr($value, 0, 5);
        }
        if (preg_match('/^\d{3,4}$/', $value)) {
            return str_pad(substr($value, 0, -2), 2, '0', STR_PAD_LEFT).':'.substr($value, -2);
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('H:i', $timestamp) : $value;
    }

    private function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            $string = trim((string) $value);
            if ($string !== '') {
                return $string;
            }
        }

        return null;
    }
}
