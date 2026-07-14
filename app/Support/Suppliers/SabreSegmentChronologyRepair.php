<?php

namespace App\Support\Suppliers;

use App\Data\FlightSegmentData;
use DateTimeImmutable;

/**
 * Repairs Sabre-derived segment timestamps after route order correction so airport-valid
 * chains are not rejected solely for calendar mis-assignment. Does not log raw payloads.
 */
final class SabreSegmentChronologyRepair
{
    public const MAX_CONNECTION_DAY_SLIDE = 3;

    /**
     * @param  list<FlightSegmentData>  $segments
     * @return array{
     *   segments: list<FlightSegmentData>,
     *   diagnostics: array{
     *     date_repair_attempted: bool,
     *     date_repair_applied: bool,
     *     repaired_segment_count: int,
     *     segment_order_corrected: bool,
     *     requested_departure_date_present: bool,
     *     requested_return_date_present: bool
     *   }
     * }
     */
    public static function repair(
        array $segments,
        ?string $requestDepartureYmd,
        bool $segmentOrderCorrected,
        ?string $requestReturnYmd = null,
        ?string $searchOrigin = null,
        ?string $searchDestination = null,
    ): array {
        $requestedDepYmd = self::validYmd($requestDepartureYmd);
        $requestedReturnYmd = self::validYmd($requestReturnYmd);
        $diag = [
            'date_repair_attempted' => $segments !== [],
            'date_repair_applied' => false,
            'repaired_segment_count' => 0,
            'segment_order_corrected' => $segmentOrderCorrected,
            'requested_departure_date_present' => $requestedDepYmd !== null,
            'requested_return_date_present' => $requestedReturnYmd !== null,
        ];

        if ($segments === []) {
            return ['segments' => [], 'diagnostics' => $diag];
        }

        $rows = self::cloneList($segments);
        $repaired = 0;

        if ($requestedDepYmd !== null) {
            $repaired += self::anchorFirstDepartureToRequestDate($rows, $requestedDepYmd);
        }

        if ($requestedReturnYmd !== null) {
            $returnStart = self::findReturnLegStartIndex(
                $rows,
                strtoupper(trim((string) $searchOrigin)),
                strtoupper(trim((string) $searchDestination)),
            );
            if ($returnStart !== null) {
                $repaired += self::anchorReturnLegFirstDepartureToRequestDate($rows, $returnStart, $requestedReturnYmd);
            }
        }

        $repaired += self::repairLegArrivalsFromElapsed($rows);
        $repaired += self::slideConnectionDepartures($rows, self::MAX_CONNECTION_DAY_SLIDE);
        $repaired += self::repairLegArrivalsFromElapsed($rows);
        $repaired += self::snapArrivalsToElapsedWhenSpuriousExtraWallTime($rows);

        $diag['repaired_segment_count'] = $repaired;
        $diag['date_repair_applied'] = $repaired > 0;

        return ['segments' => $rows, 'diagnostics' => $diag];
    }

    /**
     * @param  list<FlightSegmentData>  $rows
     */
    protected static function findReturnLegStartIndex(array $rows, string $reqO, string $reqD): ?int
    {
        $n = count($rows);
        if ($n < 2 || $reqO === '' || $reqD === '') {
            return null;
        }

        for ($i = 1; $i < $n; $i++) {
            $seg = $rows[$i];
            if (! $seg instanceof FlightSegmentData) {
                continue;
            }
            if (strtoupper(trim($seg->origin)) !== $reqD) {
                continue;
            }
            for ($j = 0; $j < $i; $j++) {
                $prior = $rows[$j];
                if (! $prior instanceof FlightSegmentData) {
                    continue;
                }
                $po = strtoupper(trim($prior->origin));
                $pd = strtoupper(trim($prior->destination));
                if ($po === $reqO || $pd === $reqD) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<FlightSegmentData>  $rows
     */
    protected static function anchorReturnLegFirstDepartureToRequestDate(array &$rows, int $startIdx, string $returnYmd): int
    {
        if (! isset($rows[$startIdx]) || ! $rows[$startIdx] instanceof FlightSegmentData) {
            return 0;
        }

        $s = $rows[$startIdx];
        $dep = self::parse($s->departure_at);
        if ($dep === null) {
            return 0;
        }

        if ($dep->format('Y-m-d') >= $returnYmd) {
            return 0;
        }

        $candidate = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $returnYmd.'T'.$dep->format('H:i:s'));
        if ($candidate === false) {
            return 0;
        }

        $newDep = $candidate->format('Y-m-d\TH:i:s');
        $arrAt = $s->arrival_at;
        $arr = self::parse($arrAt);
        if ($arr !== null && $arr < $candidate) {
            $dur = max(0, $s->duration_minutes);
            if ($dur > 0) {
                $arrAt = $candidate->modify('+'.$dur.' minutes')->format('Y-m-d\TH:i:s');
            } else {
                $arrAt = $candidate->modify('+90 minutes')->format('Y-m-d\TH:i:s');
            }
        }

        $rows[$startIdx] = self::withTimes($s, $newDep, $arrAt);

        return 1;
    }

    /**
     * @param  list<FlightSegmentData>  $rows
     */
    protected static function cloneList(array $rows): array
    {
        $out = [];
        foreach ($rows as $s) {
            if (! $s instanceof FlightSegmentData) {
                continue;
            }
            $out[] = self::cloneSegment($s);
        }

        return $out;
    }

    protected static function cloneSegment(FlightSegmentData $s): FlightSegmentData
    {
        return new FlightSegmentData(
            origin: $s->origin,
            destination: $s->destination,
            departure_at: $s->departure_at,
            arrival_at: $s->arrival_at,
            flight_number: $s->flight_number,
            airline_code: $s->airline_code,
            airline_name: $s->airline_name,
            duration_minutes: max(0, $s->duration_minutes),
            operating_airline_code: $s->operating_airline_code,
            operating_airline_name: $s->operating_airline_name,
            booking_class: $s->booking_class,
            fare_basis_code: $s->fare_basis_code,
            segment_cabin_code: $s->segment_cabin_code,
        );
    }

    /**
     * @param  list<FlightSegmentData>  $rows
     */
    protected static function anchorFirstDepartureToRequestDate(array &$rows, string $ymd): int
    {
        if ($rows === []) {
            return 0;
        }
        $s0 = $rows[0];
        if (! $s0 instanceof FlightSegmentData) {
            return 0;
        }
        $dep = self::parse($s0->departure_at);
        if ($dep === null) {
            return 0;
        }
        $candidate = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $ymd.'T'.$dep->format('H:i:s'));
        if ($candidate === false) {
            return 0;
        }
        if ($candidate->format('Y-m-d\TH:i:s') === $dep->format('Y-m-d\TH:i:s')) {
            return 0;
        }
        $rows[0] = self::withTimes($s0, $candidate->format('Y-m-d\TH:i:s'), $s0->arrival_at);

        return 1;
    }

    /**
     * @param  list<FlightSegmentData>  $rows
     */
    protected static function repairLegArrivalsFromElapsed(array &$rows): int
    {
        $n = 0;
        foreach ($rows as $i => $s) {
            if (! $s instanceof FlightSegmentData) {
                continue;
            }
            $dur = max(0, $s->duration_minutes);
            if ($dur <= 0) {
                continue;
            }
            $dep = self::parse($s->departure_at);
            $arr = self::parse($s->arrival_at);
            if ($dep === null || $arr === null) {
                continue;
            }
            if ($arr > $dep) {
                continue;
            }
            $newArr = $dep->modify('+'.$dur.' minutes');
            $rows[$i] = self::withTimes($s, $s->departure_at, $newArr->format('Y-m-d\TH:i:s'));
            $n++;
        }

        return $n;
    }

    /**
     * When a short leg's wall time exceeds elapsedTime by many hours (often a spurious +1 calendar day on
     * arrival while the marketing block is only E minutes), snap arrival to departure+E so layover
     * carries multi-day connection waits (PK/KHI → DXB pattern).
     *
     * @param  list<FlightSegmentData>  $rows
     */
    protected static function snapArrivalsToElapsedWhenSpuriousExtraWallTime(array &$rows): int
    {
        $n = count($rows);
        $fixes = 0;
        for ($i = 0; $i < $n; $i++) {
            $s = $rows[$i];
            if (! $s instanceof FlightSegmentData) {
                continue;
            }
            $e = max(0, $s->duration_minutes);
            $d = self::parse($s->departure_at);
            $a = self::parse($s->arrival_at);
            if ($d === null || $a === null) {
                continue;
            }
            $wallMin = (int) round(($a->getTimestamp() - $d->getTimestamp()) / 60);
            if ($e > 600 && $e === $wallMin && $wallMin <= 1560) {
                $r = $wallMin % 1440;
                if ($r >= 30 && $r <= 600) {
                    $e = $r;
                }
            }
            if ($e <= 0 || $e > 600) {
                continue;
            }
            $excess = $wallMin - $e;
            if ($excess < 720 || $wallMin <= $e + 360) {
                continue;
            }
            try {
                $canonical = $d->modify('+'.$e.' minutes');
            } catch (\Throwable) {
                continue;
            }
            if ($canonical <= $d) {
                continue;
            }
            if ($i < $n - 1) {
                $nd = self::parse($rows[$i + 1]->departure_at);
                if ($nd !== null && $canonical > $nd) {
                    continue;
                }
            }
            $newArrStr = $canonical->format('Y-m-d\TH:i:s');
            $rows[$i] = self::withTimes($s, $s->departure_at, $newArrStr);
            $fixes++;
        }

        return $fixes;
    }

    /**
     * @param  list<FlightSegmentData>  $rows
     */
    protected static function slideConnectionDepartures(array &$rows, int $maxDaySlide): int
    {
        $count = count($rows);
        if ($count < 2) {
            return 0;
        }
        $repairs = 0;
        $guard = 0;
        while ($guard < ($count * 8)) {
            $guard++;
            $changed = false;
            for ($i = 0; $i < $count - 1; $i++) {
                $prev = $rows[$i];
                $next = $rows[$i + 1];
                if (! $prev instanceof FlightSegmentData || ! $next instanceof FlightSegmentData) {
                    continue;
                }
                $aEnd = self::parse($prev->arrival_at);
                $bDep = self::parse($next->departure_at);
                if ($aEnd === null || $bDep === null) {
                    continue;
                }
                if ($bDep >= $aEnd) {
                    continue;
                }
                $gapDays = (int) $aEnd->diff($bDep)->days;
                if ($gapDays > $maxDaySlide) {
                    continue;
                }
                $bDepNew = $bDep;
                for ($step = 0; $step < $maxDaySlide; $step++) {
                    $bDepNew = $bDepNew->modify('+1 day');
                    if ($bDepNew >= $aEnd) {
                        break;
                    }
                }
                if ($bDepNew < $aEnd) {
                    continue;
                }
                $durNext = max(0, $next->duration_minutes);
                $bDepStr = $bDepNew->format('Y-m-d\TH:i:s');
                if ($durNext > 0) {
                    $bArrNew = $bDepNew->modify('+'.$durNext.' minutes')->format('Y-m-d\TH:i:s');
                } else {
                    $bArrCur = self::parse($next->arrival_at);
                    if ($bArrCur !== null && $bArrCur > $bDepNew) {
                        $bArrNew = $next->arrival_at;
                    } else {
                        $bArrNew = $bDepNew->modify('+90 minutes')->format('Y-m-d\TH:i:s');
                    }
                }
                $rows[$i + 1] = self::withTimes($next, $bDepStr, $bArrNew);
                $repairs++;
                $changed = true;
            }
            if (! $changed) {
                break;
            }
        }

        return $repairs;
    }

    protected static function withTimes(FlightSegmentData $s, string $depAt, string $arrAt): FlightSegmentData
    {
        $dep = self::parse($depAt);
        $arr = self::parse($arrAt);
        $dur = $s->duration_minutes;
        if ($dep !== null && $arr !== null && $arr > $dep) {
            $dur = max($dur, (int) round(($arr->getTimestamp() - $dep->getTimestamp()) / 60));
        }

        return new FlightSegmentData(
            origin: $s->origin,
            destination: $s->destination,
            departure_at: $depAt,
            arrival_at: $arrAt,
            flight_number: $s->flight_number,
            airline_code: $s->airline_code,
            airline_name: $s->airline_name,
            duration_minutes: max(0, $dur),
            operating_airline_code: $s->operating_airline_code,
            operating_airline_name: $s->operating_airline_name,
            booking_class: $s->booking_class,
            fare_basis_code: $s->fare_basis_code,
            segment_cabin_code: $s->segment_cabin_code,
        );
    }

    protected static function parse(string $iso): ?DateTimeImmutable
    {
        $iso = trim($iso);
        if ($iso === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $p) {
            $dt = DateTimeImmutable::createFromFormat($p, $iso);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }
        try {
            return new DateTimeImmutable($iso);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function validYmd(?string $d): ?string
    {
        if ($d === null || trim($d) === '') {
            return null;
        }
        $d = trim($d);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $d);

        return ($dt !== false && $dt->format('Y-m-d') === $d) ? $d : null;
    }
}
