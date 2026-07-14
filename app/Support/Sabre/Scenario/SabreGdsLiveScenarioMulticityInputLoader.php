<?php

namespace App\Support\Sabre\Scenario;

use InvalidArgumentException;

/**
 * Loads and validates operator multicity JSON for {@see SabreGdsLiveScenarioRunner} plan mode.
 *
 * @phpstan-type MulticitySlice array{origin: string, destination: string, departure_date: string}
 * @phpstan-type MulticityInput array{
 *     slices: list<MulticitySlice>,
 *     adult_count: int,
 *     child_count: int,
 *     infant_count: int,
 *     cabin: string,
 *     cabin_app: string
 * }
 */
final class SabreGdsLiveScenarioMulticityInputLoader
{
    public const MIN_SLICES = 2;

    public const MAX_SLICES = 6;

    /**
     * @return MulticityInput
     */
    public function load(string $pathOrJson): array
    {
        $decoded = $this->decode($pathOrJson);

        return $this->normalize($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(string $pathOrJson): array
    {
        $trimmed = trim($pathOrJson);
        if ($trimmed === '') {
            throw new InvalidArgumentException('multicity_json_empty');
        }

        if ($trimmed[0] === '{') {
            $decoded = json_decode($trimmed, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('multicity_json_invalid');
            }

            return $decoded;
        }

        if (! is_file($trimmed)) {
            throw new InvalidArgumentException('multicity_json_file_not_found');
        }

        $raw = file_get_contents($trimmed);
        if ($raw === false || trim($raw) === '') {
            throw new InvalidArgumentException('multicity_json_file_empty');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('multicity_json_invalid');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return MulticityInput
     */
    public function normalize(array $decoded): array
    {
        $rawSlices = $decoded['slices'] ?? null;
        if (! is_array($rawSlices) || count($rawSlices) < self::MIN_SLICES) {
            throw new InvalidArgumentException('multicity_slices_invalid');
        }
        if (count($rawSlices) > self::MAX_SLICES) {
            throw new InvalidArgumentException('multicity_slices_too_many');
        }

        $slices = [];
        foreach ($rawSlices as $idx => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('multicity_slice_invalid');
            }
            $origin = strtoupper(trim((string) ($row['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($row['destination'] ?? '')));
            $departureDate = trim((string) ($row['departure_date'] ?? ''));
            if ($origin === '' || strlen($origin) !== 3) {
                throw new InvalidArgumentException('multicity_slice_origin_invalid');
            }
            if ($destination === '' || strlen($destination) !== 3) {
                throw new InvalidArgumentException('multicity_slice_destination_invalid');
            }
            if ($origin === $destination) {
                throw new InvalidArgumentException('multicity_slice_same_origin_destination');
            }
            if (! $this->isValidDate($departureDate)) {
                throw new InvalidArgumentException('multicity_slice_departure_date_invalid');
            }
            $slices[] = [
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
            ];
        }

        $adults = max(0, (int) ($decoded['adult_count'] ?? $decoded['adults'] ?? 1));
        $children = max(0, (int) ($decoded['child_count'] ?? $decoded['children'] ?? 0));
        $infants = max(0, (int) ($decoded['infant_count'] ?? $decoded['infants'] ?? 0));
        if ($adults < 1) {
            throw new InvalidArgumentException('multicity_adult_count_required');
        }

        $cabinRaw = strtoupper(trim((string) ($decoded['cabin'] ?? 'Y')));

        return [
            'slices' => $slices,
            'adult_count' => $adults,
            'child_count' => $children,
            'infant_count' => $infants,
            'cabin' => $cabinRaw,
            'cabin_app' => $this->mapSabreCabinToApp($cabinRaw),
        ];
    }

    protected function isValidDate(string $date): bool
    {
        if ($date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    protected function mapSabreCabinToApp(string $cabin): string
    {
        return match (strtoupper(trim($cabin))) {
            'Y' => 'economy',
            'S' => 'premium_economy',
            'C' => 'business',
            'F' => 'first',
            default => strtolower(trim($cabin)) !== '' ? strtolower(trim($cabin)) : 'economy',
        };
    }
}
