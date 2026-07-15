<?php

namespace App\Support\Suppliers;

use App\Data\FlightSegmentData;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Validates Sabre itinerary segments for airport continuity and chronological consistency.
 * Safe for logging (counts and IATA codes only from segment endpoints).
 */
final class SabreItineraryTimingValidator
{
    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{
     *   ok: bool,
     *   airport_continuity_ok: bool,
     *   chronology_ok: bool,
     *   failed_time_link_count: int,
     *   invalid_segment_duration_count: int,
     *   first_segment_origin: string,
     *   last_segment_destination: string
     * }
     */
    public static function analyzeSegmentArrays(array $segments): array
    {
        $normalized = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $normalized[] = [
                'origin' => strtoupper(trim((string) ($seg['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($seg['destination'] ?? ''))),
                'departure_at' => trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '')),
                'arrival_at' => trim((string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? '')),
            ];
        }

        return self::analyzeNormalizedRows($normalized);
    }

    /**
     * @param  list<FlightSegmentData>  $models
     * @return array{
     *   ok: bool,
     *   airport_continuity_ok: bool,
     *   chronology_ok: bool,
     *   failed_time_link_count: int,
     *   invalid_segment_duration_count: int,
     *   first_segment_origin: string,
     *   last_segment_destination: string
     * }
     */
    public static function analyzeFlightSegmentModels(array $models): array
    {
        $rows = [];
        foreach ($models as $m) {
            if (! $m instanceof FlightSegmentData) {
                continue;
            }
            $rows[] = [
                'origin' => strtoupper(trim($m->origin)),
                'destination' => strtoupper(trim($m->destination)),
                'departure_at' => trim((string) $m->departure_at),
                'arrival_at' => trim((string) $m->arrival_at),
            ];
        }

        return self::analyzeNormalizedRows($rows);
    }

    /**
     * @param  list<array{origin: string, destination: string, departure_at: string, arrival_at: string}>  $rows
     * @return array{
     *   ok: bool,
     *   airport_continuity_ok: bool,
     *   chronology_ok: bool,
     *   failed_time_link_count: int,
     *   invalid_segment_duration_count: int,
     *   first_segment_origin: string,
     *   last_segment_destination: string
     * }
     */
    protected static function analyzeNormalizedRows(array $rows): array
    {
        $n = count($rows);
        $firstO = $n > 0 ? $rows[0]['origin'] : '';
        $lastD = $n > 0 ? $rows[$n - 1]['destination'] : '';

        if ($n === 0) {
            return [
                'ok' => false,
                'airport_continuity_ok' => false,
                'chronology_ok' => false,
                'failed_time_link_count' => 0,
                'invalid_segment_duration_count' => 0,
                'first_segment_origin' => '',
                'last_segment_destination' => '',
            ];
        }

        $airportOk = true;
        for ($i = 0; $i < $n - 1; $i++) {
            $d = $rows[$i]['destination'];
            $nextO = $rows[$i + 1]['origin'];
            if ($d === '' || $nextO === '' || $d !== $nextO) {
                $airportOk = false;
            }
        }

        $invalidDur = 0;
        foreach ($rows as $row) {
            $dep = $row['departure_at'];
            $arr = $row['arrival_at'];
            if ($dep === '' || $arr === '') {
                $invalidDur++;

                continue;
            }
            $d1 = self::parseInstant($dep);
            $d2 = self::parseInstant($arr);
            if ($d1 === null || $d2 === null || $d2 <= $d1) {
                $invalidDur++;
            }
        }

        $failedLinks = 0;
        for ($i = 0; $i < $n - 1; $i++) {
            $a = $rows[$i]['arrival_at'];
            $b = $rows[$i + 1]['departure_at'];
            if ($a === '' || $b === '') {
                $failedLinks++;

                continue;
            }
            $ta = self::parseInstant($a);
            $tb = self::parseInstant($b);
            if ($ta === null || $tb === null) {
                $failedLinks++;

                continue;
            }
            if ($ta > $tb) {
                $failedLinks++;
            }
        }

        $chronoOk = $failedLinks === 0 && $invalidDur === 0;

        return [
            'ok' => $airportOk && $chronoOk,
            'airport_continuity_ok' => $airportOk,
            'chronology_ok' => $chronoOk,
            'failed_time_link_count' => $failedLinks,
            'invalid_segment_duration_count' => $invalidDur,
            'first_segment_origin' => $firstO,
            'last_segment_destination' => $lastD,
        ];
    }

    protected static function parseInstant(string $iso): ?DateTimeInterface
    {
        if (trim($iso) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($iso);
        } catch (\Throwable) {
            return null;
        }
    }
}
