<?php

namespace App\Support\FlightSearch;

use App\Enums\SupplierProvider;
use App\Models\Airport;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;
use App\Support\Bookings\PiaNdcSelectedFareReadinessService;
use App\Support\Suppliers\SabreItineraryTimingValidator;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Human-readable schedule labels for public flight cards and JSON (no raw ISO in UI fields).
 * Also builds journey overview, segment timeline rows, layover waiting labels, fare summary,
 * and optional branded fare-family option cards when structured supplier data exists on the offer.
 */
class FlightOfferDisplayPresenter
{
    public const SELECTED_FARE_VALIDATION_NOTE = 'Final fare family and price will be confirmed during airline price validation.';

    public const SELECTED_FARE_PAYABLE_DISCLAIMER = 'Final payable amount will be confirmed before ticketing or payment.';

    /**
     * @param  list<string>  $iataCodes
     * @return array<string, string>
     */
    public static function airportCityMap(array $iataCodes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn (mixed $c): string => strtoupper(trim((string) $c)),
            $iataCodes
        ))));
        if ($codes === []) {
            return [];
        }

        $rows = Airport::query()
            ->whereIn(DB::raw('UPPER(TRIM(iata_code))'), $codes)
            ->get(['iata_code', 'city']);

        $map = [];
        foreach ($rows as $row) {
            $map[strtoupper(trim((string) $row->iata_code))] = trim((string) ($row->city ?? ''));
        }

        return $map;
    }

    public static function shouldPreserveOfferSegmentOrder(array $offer): bool
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'sabre') === 0) {
            return true;
        }

        return data_get($offer, 'raw_payload.sabre_segment_order.segment_order_corrected') === true;
    }

    /**
     * Wall-clock journey length from ordered segment endpoints (minutes).
     *
     * @param  list<array<string, mixed>>  $segments
     */
    public static function journeyTimelineMinutesFromOrderedSegments(array $segments): int
    {
        $n = count($segments);
        if ($n === 0) {
            return 0;
        }
        $first = $segments[0];
        $last = $segments[$n - 1];
        if (! is_array($first) || ! is_array($last)) {
            return 0;
        }
        $dep = trim((string) ($first['departure_at'] ?? ''));
        $arr = trim((string) ($last['arrival_at'] ?? ''));

        return self::wallMinutesBetweenIsoStrings($dep, $arr);
    }

    public static function journeyTimelineMinutesFromOffer(array $offer): int
    {
        $segs = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        if ($segs === []) {
            $dep = trim((string) ($offer['departure_at'] ?? $offer['depart_at'] ?? ''));
            $arr = trim((string) ($offer['arrival_at'] ?? $offer['arrive_at'] ?? ''));

            return self::wallMinutesBetweenIsoStrings($dep, $arr);
        }
        $ordered = self::shouldPreserveOfferSegmentOrder($offer)
            ? array_values($segs)
            : self::sortSegmentArraysByDeparture($segs);

        return self::journeyTimelineMinutesFromOrderedSegments($ordered);
    }

    /**
     * True when a Sabre checkout snapshot disagrees with the segment timeline beyond tolerance
     * or has broken segment chronology.
     */
    public static function selectedItineraryTimelineInvalid(array $offer): bool
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'sabre') !== 0) {
            return false;
        }
        $segs = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        $ordered = self::shouldPreserveOfferSegmentOrder($offer)
            ? array_values($segs)
            : self::sortSegmentArraysByDeparture($segs);
        $chrono = SabreItineraryTimingValidator::analyzeSegmentArrays($ordered);
        if ($ordered !== [] && ! $chrono['ok']) {
            return true;
        }
        $tl = self::journeyTimelineMinutesFromOffer($offer);
        $dm = (int) ($offer['duration_minutes'] ?? 0);
        if ($tl <= 0 || $dm <= 0) {
            return false;
        }

        return abs($tl - $dm) > 15;
    }

    /**
     * Public alias so cards, segments, and layovers share one formatter (day + hour rules).
     */
    public static function formatItineraryBlockDuration(int $minutes): string
    {
        return self::formatDurationMinutesExtended($minutes);
    }

    /**
     * Parses itinerary timestamps as naive wall-clock (no timezone shift). Trailing "Z" is ignored
     * so Sabre-style local datetimes are not shifted vs connection legs.
     */
    protected static function parseItineraryWallInstant(string $iso): ?DateTimeImmutable
    {
        $iso = trim($iso);
        if ($iso === '') {
            return null;
        }
        $iso = preg_replace('/\.\d+/', '', $iso);
        $iso = preg_replace('/Z$/i', '', $iso);
        foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $iso);
            if ($dt instanceof DateTimeImmutable) {
                $errs = DateTimeImmutable::getLastErrors();
                if (is_array($errs) && (($errs['warning_count'] ?? 0) > 0 || ($errs['error_count'] ?? 0) > 0)) {
                    continue;
                }

                return $dt;
            }
        }
        try {
            return new DateTimeImmutable($iso);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Whole minutes from start ISO to end ISO (end after start), using wall-clock instants only.
     */
    protected static function wallMinutesBetweenIsoStrings(string $startIso, string $endIso): int
    {
        $a = self::parseItineraryWallInstant($startIso);
        $b = self::parseItineraryWallInstant($endIso);
        if (! $a instanceof DateTimeImmutable || ! $b instanceof DateTimeImmutable) {
            return 0;
        }
        if ($b < $a) {
            return 0;
        }

        return max(0, (int) round(($b->getTimestamp() - $a->getTimestamp()) / 60));
    }

    protected static function extractLeadingYmd(string $iso): ?string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', trim($iso), $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * When wall parsing yields 0 but both strings share the same calendar date, derive minutes from clock only.
     */
    protected static function sameCalendarDayClockMinutes(string $depIso, string $arrIso): int
    {
        $ymd1 = self::extractLeadingYmd($depIso);
        $ymd2 = self::extractLeadingYmd($arrIso);
        if ($ymd1 === null || $ymd1 !== $ymd2) {
            return 0;
        }
        $t1 = self::clockMinutesFromMidnight($depIso);
        $t2 = self::clockMinutesFromMidnight($arrIso);
        if ($t1 < 0 || $t2 < 0) {
            return 0;
        }
        $d = $t2 - $t1;

        return $d > 0 ? $d : 0;
    }

    protected static function clockMinutesFromMidnight(string $iso): int
    {
        if (preg_match('/T(\d{2}):(\d{2})(?::(\d{2}))?/', $iso, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;

            return $h * 60 + $min + (int) round($s / 60);
        }
        if (preg_match('/(\d{2}):(\d{2})(?::(\d{2}))?$/', trim($iso), $m)) {
            return (int) $m[1] * 60 + (int) $m[2] + (isset($m[3]) ? (int) round((int) $m[3] / 60) : 0);
        }

        return -1;
    }

    /**
     * In-air block for one segment: wall dep→arr; never use multi-day stored Sabre minutes when same-day wall applies.
     *
     * @param  int  $storedFallbackMinutes  Supplier-reported leg minutes (used only when wall is unknown and plausible).
     */
    protected static function segmentFlightMinutesForDisplay(string $depIso, string $arrIso, int $storedFallbackMinutes): int
    {
        $w = self::wallMinutesBetweenIsoStrings($depIso, $arrIso);
        if ($w > 0) {
            return $w;
        }
        $sameDay = self::sameCalendarDayClockMinutes($depIso, $arrIso);
        if ($sameDay > 0) {
            return $sameDay;
        }
        $stored = max(0, $storedFallbackMinutes);
        if ($stored > 0 && $stored <= 1440) {
            return $stored;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<string>
     */
    public static function collectIataCodes(array $offer): array
    {
        $codes = [];
        $codes[] = (string) ($offer['origin'] ?? '');
        $codes[] = (string) ($offer['destination'] ?? '');
        foreach (is_array($offer['segments'] ?? null) ? $offer['segments'] : [] as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $codes[] = (string) ($seg['origin'] ?? '');
            $codes[] = (string) ($seg['destination'] ?? '');
        }

        return $codes;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    protected static function sortSegmentArraysByDeparture(array $segments): array
    {
        if (count($segments) <= 1) {
            return array_values($segments);
        }
        usort($segments, function (array $a, array $b): int {
            $da = (string) ($a['departure_at'] ?? '');
            $db = (string) ($b['departure_at'] ?? '');
            if ($da === '' && $db !== '') {
                return 1;
            }
            if ($db === '' && $da !== '') {
                return -1;
            }
            $cmp = strcmp($da, $db);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp2 = strcmp((string) ($a['arrival_at'] ?? ''), (string) ($b['arrival_at'] ?? ''));
            if ($cmp2 !== 0) {
                return $cmp2;
            }
            $oa = strtoupper(trim((string) ($a['origin'] ?? '')));
            $ob = strtoupper(trim((string) ($b['origin'] ?? '')));
            if ($oa !== $ob) {
                return strcmp($oa, $ob);
            }

            return strcmp(
                strtoupper(trim((string) ($a['destination'] ?? ''))),
                strtoupper(trim((string) ($b['destination'] ?? '')))
            );
        });

        return array_values($segments);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string>  $cityMap
     * @param  array<string, string>  $airlineNameMap  Uppercase IATA => display name
     * @return array<string, mixed>
     */
    public static function buildPresentation(array $offer, array $criteria, array $cityMap, array $airlineNameMap = []): array
    {
        $depIso = (string) ($offer['depart_at'] ?? $offer['departure_at'] ?? '');
        $arrIso = (string) ($offer['arrive_at'] ?? $offer['arrival_at'] ?? '');
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        if (self::shouldPreserveOfferSegmentOrder($offer)) {
            $segments = array_values($segments);
        } else {
            $segments = self::sortSegmentArraysByDeparture($segments);
        }

        $firstSeg = $segments[0] ?? null;
        $lastSeg = $segments !== [] ? $segments[count($segments) - 1] : null;

        $depCode = strtoupper(trim((string) ($offer['origin'] ?? '')));
        if ($depCode === '') {
            $depCode = strtoupper(trim((string) (
                is_array($firstSeg)
                    ? ($firstSeg['origin'] ?? '')
                    : ''
            )));
        }
        if ($depCode === '') {
            $depCode = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        }

        $arrCode = strtoupper(trim((string) ($offer['destination'] ?? '')));
        if ($arrCode === '') {
            $arrCode = strtoupper(trim((string) (
                is_array($lastSeg)
                    ? ($lastSeg['destination'] ?? '')
                    : ''
            )));
        }
        if ($arrCode === '') {
            $arrCode = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        }

        if ($depCode === '') {
            $depCode = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        }
        if ($arrCode === '') {
            $arrCode = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        }

        $depCity = $cityMap[$depCode] ?? '';
        $arrCity = $cityMap[$arrCode] ?? '';

        $depCarbon = self::carbonForDisplay($depIso);
        $arrCarbon = self::carbonForDisplay($arrIso);

        $depTimeDisplay = $depCarbon ? $depCarbon->format('H:i') : self::clockDisplayFromIso($depIso);
        $arrTimeDisplay = $arrCarbon ? $arrCarbon->format('H:i') : self::clockDisplayFromIso($arrIso);
        $depDateDisplay = $depCarbon ? $depCarbon->format('D, j M') : '';
        $arrDateDisplay = $arrCarbon ? $arrCarbon->format('D, j M') : '';

        $arrivalDayOffset = null;
        if ($depCarbon && $arrCarbon) {
            $depDay = $depCarbon->copy()->startOfDay();
            $arrDay = $arrCarbon->copy()->startOfDay();
            if (! $arrDay->lt($depDay)) {
                $calDays = (int) $depDay->diffInDays($arrDay, true);
                if ($calDays > 0) {
                    $arrivalDayOffset = $calDays === 1 ? '+1 day' : '+'.$calDays.' days';
                }
            }
        }

        $segmentAirportContinuous = self::segmentsChainContinuous($segments);
        $segmentChronoOk = SabreItineraryTimingValidator::analyzeSegmentArrays($segments)['ok'];
        $segmentContinuityOk = $segmentAirportContinuous && $segmentChronoOk;
        $nSeg = count($segments);
        $connectionDetailsUnavailable = $nSeg >= 2 && ! $segmentContinuityOk;

        $totalDurMin = (int) ($offer['duration_minutes'] ?? 0);
        $timelineFromSegs = self::journeyTimelineMinutesFromOrderedSegments($segments);
        if ($segmentContinuityOk && $timelineFromSegs > 0) {
            if ($totalDurMin > 0 && abs($totalDurMin - $timelineFromSegs) > 15) {
                Log::warning('offer_duration_mismatch_using_timeline', [
                    'component' => 'flight_offer_display',
                    'offer_duration_minutes' => $totalDurMin,
                    'timeline_duration_minutes' => $timelineFromSegs,
                    'supplier_provider' => (string) ($offer['supplier_provider'] ?? ''),
                ]);
            }
            $totalDurMin = $timelineFromSegs;
        }
        $itineraryDurationDisplay = self::formatItineraryBlockDuration($totalDurMin);
        $totalJourneyDurationDisplay = $totalDurMin > 0
            ? 'Total duration: '.$itineraryDurationDisplay
            : '';

        if ($connectionDetailsUnavailable) {
            $firstO = $nSeg > 0 ? strtoupper(trim((string) (($segments[0] ?? [])['origin'] ?? ''))) : '';
            $lastD = $nSeg > 0 ? strtoupper(trim((string) (($segments[$nSeg - 1] ?? [])['destination'] ?? ''))) : '';
            Log::warning('route_continuity_failed', [
                'component' => 'flight_offer_display',
                'segment_count' => $nSeg,
                'first_segment_origin' => $firstO,
                'last_segment_destination' => $lastD,
                'route_continuity_ok' => $segmentAirportContinuous,
                'segment_datetime_continuity_ok' => $segmentChronoOk,
            ]);
        }

        $finalSegDest = $lastSeg !== null && is_array($lastSeg)
            ? strtoupper(trim((string) ($lastSeg['destination'] ?? '')))
            : $arrCode;

        $layoversAfterIndex = [];
        for ($gi = 0; $gi < max(0, $nSeg - 1); $gi++) {
            $layoversAfterIndex[$gi] = $segmentContinuityOk
                ? self::layoverBetweenSegmentsLabel($segments, $gi, $finalSegDest)
                : null;
        }

        $formattedSegments = [];
        for ($si = 0; $si < count($segments); $si++) {
            $seg = $segments[$si];
            if (! is_array($seg)) {
                continue;
            }
            $depIsoSeg = (string) ($seg['departure_at'] ?? '');
            $arrIsoSeg = (string) ($seg['arrival_at'] ?? '');
            $sDep = self::carbonForDisplay($depIsoSeg);
            $sArr = self::carbonForDisplay($arrIsoSeg);
            $o = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $d = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $durMin = self::segmentFlightMinutesForDisplay(
                $depIsoSeg,
                $arrIsoSeg,
                max(0, (int) ($seg['duration_minutes'] ?? 0))
            );
            $flightDurLabel = self::formatItineraryBlockDuration($durMin);
            $layoverMinsAfter = null;
            if ($segmentContinuityOk && $si < $nSeg - 1) {
                $nextSeg = $segments[$si + 1] ?? null;
                if (is_array($nextSeg)) {
                    $layoverMinsAfter = self::wallMinutesBetweenIsoStrings(
                        trim((string) ($seg['arrival_at'] ?? '')),
                        trim((string) ($nextSeg['departure_at'] ?? ''))
                    );
                }
            }
            $segAirlineCode = strtoupper(trim((string) ($seg['airline_code'] ?? '')));
            $segAirlineName = trim((string) ($seg['airline_name'] ?? ''));
            $segDisplayName = AirlineDisplayNameResolver::resolve(
                $segAirlineCode,
                $segAirlineName,
                $airlineNameMap
            );
            $opCode = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            $opNameRaw = trim((string) ($seg['operating_airline_name'] ?? ''));
            $opDisplayName = $opCode !== ''
                ? AirlineDisplayNameResolver::resolve($opCode, $opNameRaw, $airlineNameMap)
                : $opNameRaw;

            $segCabinCode = strtoupper(trim((string) ($seg['segment_cabin_code'] ?? '')));
            $offerCabin = trim((string) ($offer['cabin'] ?? ''));
            $cabinDisplay = $segCabinCode !== '' ? $segCabinCode : ($offerCabin !== '' ? $offerCabin : null);
            $aircraftRaw = trim((string) ($seg['aircraft'] ?? $seg['aircraft_type'] ?? ''));

            $formattedSegments[] = [
                'segment_number' => $si + 1,
                'origin' => $o,
                'destination' => $d,
                'origin_city' => $cityMap[$o] ?? '',
                'destination_city' => $cityMap[$d] ?? '',
                'departure_time_display' => $sDep ? $sDep->format('H:i') : self::clockDisplayFromIso($depIsoSeg),
                'departure_date_display' => $sDep ? $sDep->format('D, j M') : '',
                'arrival_time_display' => $sArr ? $sArr->format('H:i') : self::clockDisplayFromIso($arrIsoSeg),
                'arrival_date_display' => $sArr ? $sArr->format('D, j M') : '',
                'duration_display' => $flightDurLabel,
                'flight_time_display' => $flightDurLabel !== '' && $flightDurLabel !== '0h 00m'
                    ? 'Flight time: '.$flightDurLabel
                    : '',
                'layover_after_display' => $layoversAfterIndex[$si] ?? null,
                'segment_duration_minutes' => $durMin,
                'layover_duration_minutes_after' => $layoverMinsAfter,
                'flight_number' => (string) ($seg['flight_number'] ?? ''),
                'airline_code' => $segAirlineCode,
                'airline_name' => $segDisplayName,
                'operating_airline_code' => $opCode,
                'operating_airline_name' => $opDisplayName,
                'cabin_display' => $cabinDisplay,
                'aircraft_display' => $aircraftRaw !== '' ? $aircraftRaw : null,
            ];
        }

        $baggage = $offer['baggage'] ?? null;
        $resolvedBaggage = OfferBaggageResolver::resolveFromOffer($offer);
        if (is_array($baggage)) {
            $bagChecked = $resolvedBaggage['checked'] ?? BaggageDisplayNormalizer::normalizeLabel(trim((string) ($baggage['checked'] ?? '')));
            $bagCabin = $resolvedBaggage['cabin'] ?? BaggageDisplayNormalizer::normalizeLabel(trim((string) ($baggage['cabin'] ?? '')));
            $summaryOnly = $resolvedBaggage['summary'] ?? BaggageDisplayNormalizer::normalizeLabel(trim((string) ($baggage['summary'] ?? '')));
        } else {
            $bagChecked = $resolvedBaggage['checked'] ?? BaggageDisplayNormalizer::normalizeLabel(trim((string) ($offer['baggage_checked'] ?? '')));
            $bagCabin = $resolvedBaggage['cabin'] ?? BaggageDisplayNormalizer::normalizeLabel(trim((string) ($offer['baggage_cabin'] ?? '')));
            $summaryOnly = $resolvedBaggage['summary'] ?? (is_string($baggage)
                ? BaggageDisplayNormalizer::normalizeLabel(trim($baggage))
                : null);
        }

        $stopsCount = $nSeg >= 2 ? $nSeg - 1 : max(0, (int) ($offer['stops'] ?? 0));

        self::maybeLogDurationBreakdownMismatch($offer, $segments, $nSeg, $segmentContinuityOk, $totalDurMin);

        $mcc = $offer['marketing_carrier_chain'] ?? null;
        $chainDisp = is_array($mcc) && $mcc !== [] ? implode(' + ', array_map('strval', $mcc)) : '';
        $valC = strtoupper(trim((string) ($offer['validating_carrier'] ?? '')));

        $primaryDisplay = strtoupper(trim((string) ($offer['primary_display_carrier'] ?? '')));
        if ($primaryDisplay === '' && $segments !== []) {
            $s0 = $segments[0] ?? null;
            if (is_array($s0)) {
                $primaryDisplay = strtoupper(trim((string) ($s0['airline_code'] ?? '')));
            }
        }
        if ($primaryDisplay === '') {
            $primaryDisplay = strtoupper(trim((string) ($offer['airline_code'] ?? '')));
        }

        $primaryDisplayName = AirlineDisplayNameResolver::resolveForOffer($offer, $airlineNameMap);

        $stopsDisplay = $stopsCount === 0 ? 'Direct' : ($stopsCount === 1 ? '1 stop' : $stopsCount.' stops');

        $journeyOverview = [
            'origin_code' => $depCode,
            'origin_city' => $depCity,
            'destination_code' => $arrCode,
            'destination_city' => $arrCity,
            'total_duration_display' => $itineraryDurationDisplay,
            'stops_count' => $stopsCount,
            'stops_display' => $stopsDisplay,
            'departure_time_display' => $depTimeDisplay,
            'departure_date_display' => $depDateDisplay,
            'arrival_time_display' => $arrTimeDisplay,
            'arrival_date_display' => $arrDateDisplay,
            'arrival_day_offset' => $arrivalDayOffset,
        ];

        $layoversDisplay = self::buildLayoversDisplay($formattedSegments, $connectionDetailsUnavailable);
        $fareSummaryDisplay = self::buildFareSummaryDisplay($offer, $primaryDisplayName, $bagChecked, $bagCabin, $summaryOnly);
        $fareFamilyOptionsDisplay = self::buildFareFamilyOptionsDisplay($offer);
        $brandedFaresPresentation = self::buildBrandedFaresPresentationFields($fareFamilyOptionsDisplay, $offer);
        $fareFamilyOptionsDisplay = $brandedFaresPresentation['fare_family_options_display'];

        $preserveOrder = self::shouldPreserveOfferSegmentOrder($offer);
        $tripType = (string) ($criteria['trip_type'] ?? 'one_way');
        $journeyDisplay = self::tryBuildRoundTripJourneysDisplay(
            $segments,
            $criteria,
            $cityMap,
            $airlineNameMap,
            $offer,
            $preserveOrder,
        );
        if ($tripType === 'multi_city') {
            $journeyDisplay = self::tryBuildMultiCityJourneysDisplay(
                $segments,
                $criteria,
                $cityMap,
                $airlineNameMap,
                $offer,
                $preserveOrder,
            );
        }

        $fallbackPresentation = FlightOfferFallbackDetailsPresenter::buildForOffer($offer, [
            'departure_time_display' => $depTimeDisplay,
            'departure_date_display' => $depDateDisplay,
            'departure_airport_code' => $depCode,
            'departure_city' => $depCity,
            'arrival_time_display' => $arrTimeDisplay,
            'arrival_date_display' => $arrDateDisplay,
            'arrival_airport_code' => $arrCode,
            'arrival_city' => $arrCity,
            'itinerary_duration_display' => $itineraryDurationDisplay,
            'stops_display' => $stopsDisplay,
            'baggage_checked_display' => $bagChecked !== '' ? $bagChecked : null,
            'baggage_cabin_display' => $bagCabin !== '' ? $bagCabin : null,
            'baggage_summary_display' => $summaryOnly !== '' ? $summaryOnly : null,
            'segments_display' => $formattedSegments,
            'layovers_display' => $layoversDisplay,
            'journeys_display' => $journeyDisplay['journeys_display'],
            'fare_summary_display' => $fareSummaryDisplay,
        ]);

        return [
            'departure_time_display' => $depTimeDisplay,
            'departure_date_display' => $depDateDisplay,
            'departure_airport_code' => $depCode,
            'departure_city' => $depCity,
            'arrival_time_display' => $arrTimeDisplay,
            'arrival_date_display' => $arrDateDisplay,
            'arrival_airport_code' => $arrCode,
            'arrival_city' => $arrCity,
            'arrival_day_offset' => $arrivalDayOffset,
            'total_duration_minutes' => $totalDurMin,
            'itinerary_duration_display' => $itineraryDurationDisplay,
            'total_journey_duration_display' => $totalJourneyDurationDisplay,
            'stops_count' => $stopsCount,
            'stops_display' => $stopsDisplay,
            'journey_overview_display' => $journeyOverview,
            'layover_summary' => self::buildLayoverTooltipLines($formattedSegments, $connectionDetailsUnavailable),
            'layovers_display' => $layoversDisplay,
            'connection_details_unavailable' => $connectionDetailsUnavailable,
            'segments_display' => $formattedSegments,
            'fare_summary_display' => $fareSummaryDisplay,
            'fare_family_options_display' => $fareFamilyOptionsDisplay,
            'branded_fares_display_enabled' => $brandedFaresPresentation['branded_fares_display_enabled'],
            'branded_fares_selection_enabled' => $brandedFaresPresentation['branded_fares_selection_enabled'],
            'branded_fares_selection_active' => $brandedFaresPresentation['branded_fares_selection_active'],
            'has_branded_fares' => $brandedFaresPresentation['has_branded_fares'],
            'has_fare_choice_options' => $brandedFaresPresentation['has_fare_choice_options'],
            'has_multiple_fare_choices' => $brandedFaresPresentation['has_multiple_fare_choices'],
            'has_grouped_fare_options' => (bool) ($offer['has_grouped_fare_options'] ?? data_get($offer, 'itinerary_fare_group.is_consolidated_parent', false)),
            'grouped_fare_options_count' => (int) data_get($offer, 'itinerary_fare_group.grouped_offer_count', 0),
            'itinerary_fare_group_signature_hash' => self::nullableTrimmedString(data_get($offer, 'itinerary_fare_group.signature_hash')),
            'has_synthetic_default_fare' => $brandedFaresPresentation['has_synthetic_default_fare'],
            'universal_fare_selection_active' => $brandedFaresPresentation['universal_fare_selection_active'],
            'single_direct_fare_on_card' => (bool) ($brandedFaresPresentation['single_direct_fare_on_card'] ?? false),
            'branded_fares_display_options' => $brandedFaresPresentation['branded_fares_display_options'],
            'branded_fares_more_count' => $brandedFaresPresentation['branded_fares_more_count'],
            'branded_fares_display_label' => $brandedFaresPresentation['branded_fares_display_label'],
            'baggage_checked_display' => $bagChecked !== '' ? $bagChecked : null,
            'baggage_cabin_display' => $bagCabin !== '' ? $bagCabin : null,
            'baggage_summary_display' => $summaryOnly !== '' ? $summaryOnly : null,
            'mixed_carrier' => (bool) ($offer['mixed_carrier'] ?? false),
            'has_fallback_details' => $fallbackPresentation['has_fallback_details'],
            'fallback_detail_sections_present' => $fallbackPresentation['fallback_detail_sections_present'],
            'fallback_details' => $fallbackPresentation['fallback_details'],
            'marketing_carrier_chain_display' => $chainDisp !== '' ? $chainDisp : null,
            'validating_carrier' => $valC !== '' ? $valC : null,
            'all_airline_codes' => is_array($offer['all_airline_codes'] ?? null) ? $offer['all_airline_codes'] : [],
            'primary_display_carrier' => $primaryDisplay,
            'primary_display_carrier_name' => $primaryDisplayName,
            'journeys_display' => $journeyDisplay['journeys_display'],
            'journey_grouping_unavailable' => $journeyDisplay['journey_grouping_unavailable'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  list<array{origin?: string, destination?: string, departure_date?: string}>  $requestedLegs
     * @return list<list<array<string, mixed>>>|null
     */
    public static function splitMultiCitySegments(array $segments, array $requestedLegs): ?array
    {
        $segments = array_values($segments);
        $legs = self::normalizeRequestedLegs($requestedLegs);
        if (count($legs) < 2 || $segments === []) {
            return null;
        }

        $legGroups = [];
        $startIdx = 0;
        $n = count($segments);

        foreach ($legs as $legIdx => $leg) {
            if ($startIdx >= $n) {
                return null;
            }

            $legO = $leg['origin'];
            $legD = $leg['destination'];
            $scanFrom = $startIdx;

            if ($legIdx === 0) {
                $firstO = strtoupper(trim((string) ($segments[0]['origin'] ?? '')));
                if ($firstO !== $legO) {
                    return null;
                }
                $scanFrom = 0;
            } else {
                $segO = strtoupper(trim((string) ($segments[$startIdx]['origin'] ?? '')));
                if ($segO !== $legO) {
                    return null;
                }
            }

            $splitAt = null;
            for ($i = $scanFrom; $i < $n; $i++) {
                if (strtoupper(trim((string) ($segments[$i]['destination'] ?? ''))) === $legD) {
                    $splitAt = $i;
                    break;
                }
            }
            if ($splitAt === null) {
                return null;
            }

            $group = array_slice($segments, $scanFrom, $splitAt - $scanFrom + 1);
            $groupFirstO = strtoupper(trim((string) ($group[0]['origin'] ?? '')));
            $groupLastD = strtoupper(trim((string) ($group[count($group) - 1]['destination'] ?? '')));
            if ($groupFirstO !== $legO || $groupLastD !== $legD || ! self::segmentsChainContinuous($group)) {
                return null;
            }

            $legGroups[] = $group;
            $startIdx = $splitAt + 1;
        }

        if ($startIdx !== $n) {
            return null;
        }

        return $legGroups;
    }

    /**
     * Dashboard/checkout route label from search criteria (no dates).
     *
     * @param  array<string, mixed>  $criteria
     */
    public static function formatCriteriaRouteLabel(array $criteria): string
    {
        $tripType = (string) ($criteria['trip_type'] ?? 'one_way');

        if ($tripType === 'multi_city') {
            $segments = is_array($criteria['segments'] ?? null) ? $criteria['segments'] : [];
            $parts = [];
            foreach ($segments as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $o = strtoupper(trim((string) ($seg['origin'] ?? '')));
                $d = strtoupper(trim((string) ($seg['destination'] ?? '')));
                if ($o !== '' && $d !== '') {
                    $parts[] = "{$o} → {$d}";
                }
            }

            return implode(' · ', $parts);
        }

        $from = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $to = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        if ($from === '' || $to === '') {
            return '';
        }

        if ($tripType === 'round_trip') {
            return "{$from} ⇄ {$to}";
        }

        return "{$from} → {$to}";
    }

    public static function formatCriteriaTripTypeLabel(string $tripType): string
    {
        return match ($tripType) {
            'round_trip' => 'Return',
            'multi_city' => 'Multi-city',
            default => 'One-way',
        };
    }

    /**
     * Merge cached search criteria over checkout form criteria (preserves multi-city legs).
     *
     * @param  array<string, mixed>  $fromForm
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function mergeStoredSearchCriteria(array $fromForm, ?array $stored): array
    {
        if ($stored === null || $stored === []) {
            return $fromForm;
        }

        $merged = array_merge($stored, $fromForm);

        foreach (['trip_type', 'return_date', 'segments', 'origin', 'destination', 'depart_date', 'cabin', 'adults', 'children', 'infants'] as $key) {
            if (! array_key_exists($key, $stored)) {
                continue;
            }
            $storedVal = $stored[$key];
            if ($key === 'segments') {
                if (is_array($storedVal) && $storedVal !== []) {
                    $merged['segments'] = $storedVal;
                }

                continue;
            }
            if ($storedVal !== null && $storedVal !== '') {
                $merged[$key] = $storedVal;
            }
        }

        $formOrigin = strtoupper(trim((string) ($fromForm['origin'] ?? '')));
        $formDest = strtoupper(trim((string) ($fromForm['destination'] ?? '')));
        $formDepart = trim((string) ($fromForm['depart_date'] ?? ''));
        if ($formOrigin !== '') {
            $merged['origin'] = $formOrigin;
        }
        if ($formDest !== '') {
            $merged['destination'] = $formDest;
        }
        if ($formDepart !== '') {
            $merged['depart_date'] = $formDepart;
        }

        return $merged;
    }

    /**
     * Attach display-only journey groups to an offer snapshot for booking meta.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    public static function enrichOfferSnapshotForBooking(array $offer, array $criteria): array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'sabre') === 0) {
            $offer = app(SabreFlightSearchNormalizer::class)->ensureSabreBookingContextOnCachedOffer($offer);
            $handoff = data_get($offer, 'raw_payload.sabre_booking_context');
            if (! is_array($handoff) || $handoff === []) {
                $handoff = is_array($offer['sabre_booking_context'] ?? null) ? $offer['sabre_booking_context'] : [];
            }
            if ($handoff !== []) {
                $offer['sabre_booking_context'] = $handoff;
            }
        }

        $cityMap = self::airportCityMap(self::collectIataCodes($offer));
        $presentation = self::buildPresentation($offer, $criteria, $cityMap);
        $journeys = is_array($presentation['journeys_display'] ?? null) ? $presentation['journeys_display'] : [];
        if ($journeys !== []) {
            $offer['journeys_display'] = $journeys;
        }

        return $offer;
    }

    /**
     * Collapsed-card / details section title for one multi-city leg (e.g. Leg 1: LHE → DXB).
     */
    public static function formatMultiCityLegLabel(int $legNumber, string $origin, string $destination): string
    {
        $origin = strtoupper(trim($origin));
        $destination = strtoupper(trim($destination));

        return 'Leg '.$legNumber.': '.$origin.' → '.$destination;
    }

    /**
     * @param  list<array{origin?: string, destination?: string, departure_date?: string}>  $requestedLegs
     * @return list<array{origin: string, destination: string}>
     */
    protected static function normalizeRequestedLegs(array $requestedLegs): array
    {
        $legs = [];
        foreach ($requestedLegs as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $o = strtoupper(trim((string) ($raw['origin'] ?? '')));
            $d = strtoupper(trim((string) ($raw['destination'] ?? '')));
            if ($o !== '' && $d !== '') {
                $legs[] = ['origin' => $o, 'destination' => $d];
            }
        }

        return $legs;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{outbound: list<array<string, mixed>>, return: list<array<string, mixed>>}|null
     */
    public static function splitRoundTripSegments(array $segments, string $searchOrigin, string $searchDestination): ?array
    {
        $reqO = strtoupper(trim($searchOrigin));
        $reqD = strtoupper(trim($searchDestination));
        if ($reqO === '' || $reqD === '' || count($segments) < 2) {
            return null;
        }

        $segments = array_values($segments);
        $firstO = strtoupper(trim((string) ($segments[0]['origin'] ?? '')));
        $lastD = strtoupper(trim((string) ($segments[count($segments) - 1]['destination'] ?? '')));
        if ($firstO !== $reqO || $lastD !== $reqO || ! self::segmentsChainContinuous($segments)) {
            return null;
        }

        $splitAt = null;
        for ($i = 0; $i < count($segments); $i++) {
            if (strtoupper(trim((string) ($segments[$i]['destination'] ?? ''))) === $reqD) {
                $splitAt = $i;
                break;
            }
        }
        if ($splitAt === null || $splitAt >= count($segments) - 1) {
            return null;
        }

        $outbound = array_slice($segments, 0, $splitAt + 1);
        $returnSegs = array_slice($segments, $splitAt + 1);
        $outLast = strtoupper(trim((string) ($outbound[count($outbound) - 1]['destination'] ?? '')));
        $retLast = strtoupper(trim((string) ($returnSegs[count($returnSegs) - 1]['destination'] ?? '')));
        if ($outLast !== $reqD || $retLast !== $reqO) {
            return null;
        }
        if (! self::segmentsChainContinuous($outbound) || ! self::segmentsChainContinuous($returnSegs)) {
            return null;
        }

        return ['outbound' => $outbound, 'return' => $returnSegs];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string>  $cityMap
     * @param  array<string, string>  $airlineNameMap
     * @param  array<string, mixed>  $offer
     * @return array{journeys_display: list<array<string, mixed>>, journey_grouping_unavailable: bool}
     */
    protected static function tryBuildRoundTripJourneysDisplay(
        array $segments,
        array $criteria,
        array $cityMap,
        array $airlineNameMap,
        array $offer,
        bool $preserveOrder,
    ): array {
        if ((string) ($criteria['trip_type'] ?? 'one_way') !== 'round_trip') {
            return ['journeys_display' => [], 'journey_grouping_unavailable' => false];
        }

        $reqO = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $reqD = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        if ($reqO === '' || $reqD === '' || count($segments) < 2) {
            return ['journeys_display' => [], 'journey_grouping_unavailable' => true];
        }

        $ordered = $preserveOrder ? array_values($segments) : self::sortSegmentArraysByDeparture($segments);
        $split = self::splitRoundTripSegments($ordered, $reqO, $reqD);
        if ($split === null) {
            return ['journeys_display' => [], 'journey_grouping_unavailable' => true];
        }

        return [
            'journeys_display' => [
                self::buildSingleJourneyDisplay($split['outbound'], 'outbound', 'Outbound', $reqO, $reqD, $cityMap, $airlineNameMap, $offer),
                self::buildSingleJourneyDisplay($split['return'], 'return', 'Return', $reqD, $reqO, $cityMap, $airlineNameMap, $offer),
            ],
            'journey_grouping_unavailable' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string>  $cityMap
     * @param  array<string, string>  $airlineNameMap
     * @param  array<string, mixed>  $offer
     * @return array{journeys_display: list<array<string, mixed>>, journey_grouping_unavailable: bool}
     */
    protected static function tryBuildMultiCityJourneysDisplay(
        array $segments,
        array $criteria,
        array $cityMap,
        array $airlineNameMap,
        array $offer,
        bool $preserveOrder,
    ): array {
        if ((string) ($criteria['trip_type'] ?? 'one_way') !== 'multi_city') {
            return ['journeys_display' => [], 'journey_grouping_unavailable' => false];
        }

        $requestedLegs = is_array($criteria['segments'] ?? null) ? $criteria['segments'] : [];
        $legs = self::normalizeRequestedLegs($requestedLegs);
        if (count($legs) < 2 || count($segments) < 2) {
            return ['journeys_display' => [], 'journey_grouping_unavailable' => true];
        }

        $ordered = $preserveOrder ? array_values($segments) : self::sortSegmentArraysByDeparture($segments);
        $split = self::splitMultiCitySegments($ordered, $requestedLegs);
        if ($split === null) {
            return ['journeys_display' => [], 'journey_grouping_unavailable' => true];
        }

        $journeys = [];
        foreach ($split as $idx => $legSegments) {
            $leg = $legs[$idx];
            $journeys[] = self::buildSingleJourneyDisplay(
                $legSegments,
                'multi_city',
                self::formatMultiCityLegLabel($idx + 1, $leg['origin'], $leg['destination']),
                $leg['origin'],
                $leg['destination'],
                $cityMap,
                $airlineNameMap,
                $offer,
            );
        }

        return [
            'journeys_display' => $journeys,
            'journey_grouping_unavailable' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, string>  $cityMap
     * @param  array<string, string>  $airlineNameMap
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected static function buildSingleJourneyDisplay(
        array $segments,
        string $type,
        string $label,
        string $originCode,
        string $destinationCode,
        array $cityMap,
        array $airlineNameMap,
        array $offer,
    ): array {
        $originCode = strtoupper(trim($originCode));
        $destinationCode = strtoupper(trim($destinationCode));
        $nSeg = count($segments);
        $firstSeg = $segments[0] ?? null;
        $lastSeg = $nSeg > 0 ? $segments[$nSeg - 1] : null;

        $depIso = is_array($firstSeg) ? trim((string) ($firstSeg['departure_at'] ?? '')) : '';
        $arrIso = is_array($lastSeg) ? trim((string) ($lastSeg['arrival_at'] ?? '')) : '';
        $depCarbon = self::carbonForDisplay($depIso);
        $arrCarbon = self::carbonForDisplay($arrIso);
        $depTimeDisplay = $depCarbon ? $depCarbon->format('H:i') : self::clockDisplayFromIso($depIso);
        $arrTimeDisplay = $arrCarbon ? $arrCarbon->format('H:i') : self::clockDisplayFromIso($arrIso);
        $depDateDisplay = $depCarbon ? $depCarbon->format('D, j M') : '';
        $arrDateDisplay = $arrCarbon ? $arrCarbon->format('D, j M') : '';

        $arrivalDayOffset = null;
        if ($depCarbon && $arrCarbon) {
            $depDay = $depCarbon->copy()->startOfDay();
            $arrDay = $arrCarbon->copy()->startOfDay();
            if (! $arrDay->lt($depDay)) {
                $calDays = (int) $depDay->diffInDays($arrDay, true);
                if ($calDays > 0) {
                    $arrivalDayOffset = $calDays === 1 ? '+1 day' : '+'.$calDays.' days';
                }
            }
        }

        $segmentContinuityOk = self::segmentsChainContinuous($segments)
            && SabreItineraryTimingValidator::analyzeSegmentArrays($segments)['ok'];
        $connectionDetailsUnavailable = $nSeg >= 2 && ! $segmentContinuityOk;
        $durMin = self::journeyTimelineMinutesFromOrderedSegments($segments);
        $durationDisplay = self::formatItineraryBlockDuration($durMin);
        $stopsCount = $nSeg >= 2 ? $nSeg - 1 : 0;
        $stopsDisplay = $stopsCount === 0 ? 'Direct' : ($stopsCount === 1 ? '1 stop' : $stopsCount.' stops');

        $formattedSegments = self::formatSegmentsDisplayRows(
            $segments,
            $offer,
            $cityMap,
            $airlineNameMap,
            $destinationCode,
            $segmentContinuityOk,
        );

        return [
            'type' => $type,
            'label' => $label,
            'origin' => $originCode,
            'destination' => $destinationCode,
            'origin_city' => $cityMap[$originCode] ?? '',
            'destination_city' => $cityMap[$destinationCode] ?? '',
            'departure_time_display' => $depTimeDisplay,
            'departure_date_display' => $depDateDisplay,
            'arrival_time_display' => $arrTimeDisplay,
            'arrival_date_display' => $arrDateDisplay,
            'arrival_day_offset' => $arrivalDayOffset,
            'duration_display' => $durationDisplay,
            'stops_count' => $stopsCount,
            'stops_display' => $stopsDisplay,
            'layover_summary' => self::buildLayoverTooltipLines($formattedSegments, $connectionDetailsUnavailable),
            'layovers_display' => self::buildLayoversDisplay($formattedSegments, $connectionDetailsUnavailable),
            'connection_details_unavailable' => $connectionDetailsUnavailable,
            'segments_display' => $formattedSegments,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $offer
     * @param  array<string, string>  $cityMap
     * @param  array<string, string>  $airlineNameMap
     * @return list<array<string, mixed>>
     */
    protected static function formatSegmentsDisplayRows(
        array $segments,
        array $offer,
        array $cityMap,
        array $airlineNameMap,
        string $journeyDestinationCode,
        bool $segmentContinuityOk,
    ): array {
        $nSeg = count($segments);
        $finalSegDest = strtoupper(trim($journeyDestinationCode));
        $layoversAfterIndex = [];
        for ($gi = 0; $gi < max(0, $nSeg - 1); $gi++) {
            $layoversAfterIndex[$gi] = $segmentContinuityOk
                ? self::layoverBetweenSegmentsLabel($segments, $gi, $finalSegDest)
                : null;
        }

        $formattedSegments = [];
        for ($si = 0; $si < $nSeg; $si++) {
            $seg = $segments[$si];
            if (! is_array($seg)) {
                continue;
            }
            $depIsoSeg = (string) ($seg['departure_at'] ?? '');
            $arrIsoSeg = (string) ($seg['arrival_at'] ?? '');
            $sDep = self::carbonForDisplay($depIsoSeg);
            $sArr = self::carbonForDisplay($arrIsoSeg);
            $o = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $d = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $durMin = self::segmentFlightMinutesForDisplay(
                $depIsoSeg,
                $arrIsoSeg,
                max(0, (int) ($seg['duration_minutes'] ?? 0))
            );
            $flightDurLabel = self::formatItineraryBlockDuration($durMin);
            $layoverMinsAfter = null;
            if ($segmentContinuityOk && $si < $nSeg - 1) {
                $nextSeg = $segments[$si + 1] ?? null;
                if (is_array($nextSeg)) {
                    $layoverMinsAfter = self::wallMinutesBetweenIsoStrings(
                        trim((string) ($seg['arrival_at'] ?? '')),
                        trim((string) ($nextSeg['departure_at'] ?? ''))
                    );
                }
            }
            $segAirlineCode = strtoupper(trim((string) ($seg['airline_code'] ?? '')));
            $segDisplayName = AirlineDisplayNameResolver::resolve(
                $segAirlineCode,
                trim((string) ($seg['airline_name'] ?? '')),
                $airlineNameMap
            );
            $opCode = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            $opNameRaw = trim((string) ($seg['operating_airline_name'] ?? ''));
            $opDisplayName = $opCode !== ''
                ? AirlineDisplayNameResolver::resolve($opCode, $opNameRaw, $airlineNameMap)
                : $opNameRaw;
            $segCabinCode = strtoupper(trim((string) ($seg['segment_cabin_code'] ?? '')));
            $offerCabin = trim((string) ($offer['cabin'] ?? ''));
            $cabinDisplay = $segCabinCode !== '' ? $segCabinCode : ($offerCabin !== '' ? $offerCabin : null);
            $aircraftRaw = trim((string) ($seg['aircraft'] ?? $seg['aircraft_type'] ?? ''));

            $formattedSegments[] = [
                'segment_number' => $si + 1,
                'origin' => $o,
                'destination' => $d,
                'origin_city' => $cityMap[$o] ?? '',
                'destination_city' => $cityMap[$d] ?? '',
                'departure_time_display' => $sDep ? $sDep->format('H:i') : self::clockDisplayFromIso($depIsoSeg),
                'departure_date_display' => $sDep ? $sDep->format('D, j M') : '',
                'arrival_time_display' => $sArr ? $sArr->format('H:i') : self::clockDisplayFromIso($arrIsoSeg),
                'arrival_date_display' => $sArr ? $sArr->format('D, j M') : '',
                'duration_display' => $flightDurLabel,
                'flight_time_display' => $flightDurLabel !== '' && $flightDurLabel !== '0h 00m'
                    ? 'Flight time: '.$flightDurLabel
                    : '',
                'layover_after_display' => $layoversAfterIndex[$si] ?? null,
                'segment_duration_minutes' => $durMin,
                'layover_duration_minutes_after' => $layoverMinsAfter,
                'flight_number' => (string) ($seg['flight_number'] ?? ''),
                'airline_code' => $segAirlineCode,
                'airline_name' => $segDisplayName,
                'operating_airline_code' => $opCode,
                'operating_airline_name' => $opDisplayName,
                'cabin_display' => $cabinDisplay,
                'aircraft_display' => $aircraftRaw !== '' ? $aircraftRaw : null,
            ];
        }

        return $formattedSegments;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    protected static function segmentsChainContinuous(array $segments): bool
    {
        $n = count($segments);
        if ($n < 2) {
            return true;
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $a = $segments[$i] ?? null;
            $b = $segments[$i + 1] ?? null;
            if (! is_array($a) || ! is_array($b)) {
                return false;
            }
            $d = strtoupper(trim((string) ($a['destination'] ?? '')));
            $nextO = strtoupper(trim((string) ($b['origin'] ?? '')));
            if ($d === '' || $nextO === '' || $d !== $nextO) {
                return false;
            }
        }

        return true;
    }

    /**
     * Layover label between segment at $i and $i + 1 (connection airport only).
     *
     * @param  list<array<string, mixed>>  $segments
     */
    protected static function layoverBetweenSegmentsLabel(array $segments, int $i, string $finalDestinationCode): ?string
    {
        $n = count($segments);
        if ($i < 0 || $i >= $n - 1) {
            return null;
        }
        $a = $segments[$i];
        $b = $segments[$i + 1];
        if (! is_array($a) || ! is_array($b)) {
            return null;
        }
        $airport = strtoupper(trim((string) ($a['destination'] ?? '')));
        $nextOrigin = strtoupper(trim((string) ($b['origin'] ?? '')));
        if ($airport === '' || $nextOrigin === '' || $airport !== $nextOrigin) {
            return null;
        }

        $finalDestinationCode = strtoupper(trim($finalDestinationCode));
        if ($airport === $finalDestinationCode && ! self::hasLaterSegmentDepartingFromAirport($segments, $airport, $i + 1)) {
            return null;
        }

        $arrIso = trim((string) ($a['arrival_at'] ?? ''));
        $depIso = trim((string) ($b['departure_at'] ?? ''));
        $arrW = self::parseItineraryWallInstant($arrIso);
        $depW = self::parseItineraryWallInstant($depIso);
        if (! $arrW instanceof DateTimeImmutable || ! $depW instanceof DateTimeImmutable) {
            return 'Layover time unavailable in '.$airport;
        }
        if ($depW < $arrW) {
            return 'Layover time unavailable in '.$airport;
        }
        $mins = self::wallMinutesBetweenIsoStrings($arrIso, $depIso);

        return 'Layover: '.self::formatItineraryBlockDuration(max(0, $mins)).' in '.$airport;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    protected static function hasLaterSegmentDepartingFromAirport(array $segments, string $airport, int $afterSegmentIndex): bool
    {
        $airport = strtoupper(trim($airport));
        $n = count($segments);
        for ($k = $afterSegmentIndex + 1; $k < $n; $k++) {
            $row = $segments[$k] ?? null;
            if (! is_array($row)) {
                continue;
            }
            if (strtoupper(trim((string) ($row['origin'] ?? ''))) === $airport) {
                return true;
            }
        }

        return false;
    }

    protected static function carbonForDisplay(string $iso): ?Carbon
    {
        $wall = self::parseItineraryWallInstant($iso);
        if ($wall instanceof DateTimeImmutable) {
            return Carbon::instance($wall);
        }

        return self::safeCarbon($iso);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    protected static function maybeLogDurationBreakdownMismatch(
        array $offer,
        array $segments,
        int $nSeg,
        bool $segmentContinuityOk,
        int $totalDurMinutes,
    ): void {
        if (! $segmentContinuityOk || $nSeg < 1 || $totalDurMinutes <= 0) {
            return;
        }
        $sumSeg = 0;
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $sumSeg += self::segmentFlightMinutesForDisplay(
                trim((string) ($seg['departure_at'] ?? '')),
                trim((string) ($seg['arrival_at'] ?? '')),
                max(0, (int) ($seg['duration_minutes'] ?? 0))
            );
        }
        $sumLay = 0;
        for ($i = 0; $i < $nSeg - 1; $i++) {
            $a = $segments[$i] ?? null;
            $b = $segments[$i + 1] ?? null;
            if (! is_array($a) || ! is_array($b)) {
                continue;
            }
            $sumLay += self::wallMinutesBetweenIsoStrings(
                trim((string) ($a['arrival_at'] ?? '')),
                trim((string) ($b['departure_at'] ?? ''))
            );
        }
        $expected = $sumSeg + $sumLay;
        $mismatch = abs($totalDurMinutes - $expected);
        if ($mismatch <= 20) {
            return;
        }
        Log::warning('duration_breakdown_mismatch', [
            'total_duration_minutes' => $totalDurMinutes,
            'segment_duration_sum' => $sumSeg,
            'layover_duration_sum' => $sumLay,
            'mismatch_minutes' => $mismatch,
            'offer_id' => (string) ($offer['offer_id'] ?? $offer['id'] ?? ''),
            'provider' => (string) ($offer['supplier_provider'] ?? ''),
        ]);
    }

    protected static function safeCarbon(string $iso): ?Carbon
    {
        if (trim($iso) === '') {
            return null;
        }
        try {
            return Carbon::parse($iso);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function clockDisplayFromIso(string $iso): string
    {
        $iso = trim($iso);
        if ($iso === '') {
            return '';
        }
        $c = self::safeCarbon($iso);
        if ($c) {
            return $c->format('H:i');
        }
        if (preg_match('/T(\d{2}:\d{2})(?::\d{2})?/', $iso, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d{2}:\d{2})(?::\d{2})?/', $iso, $m)) {
            return $m[1];
        }

        return '';
    }

    protected static function formatDurationMinutes(int $minutes): string
    {
        return self::formatDurationMinutesExtended($minutes);
    }

    /**
     * Formats block times as e.g. 1h 30m, 10h 25m, or 1d 3h 20m (no fractional days).
     */
    public static function formatDurationMinutesExtended(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h 00m';
        }
        $days = intdiv($minutes, 1440);
        $rem = $minutes % 1440;
        $h = intdiv($rem, 60);
        $m = $rem % 60;
        $hm = $h.'h '.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'m';

        return $days > 0 ? $days.'d '.$hm : $hm;
    }

    /**
     * Compact layover duration for result-card tooltips (e.g. 45m, 3h, 3h 55m).
     */
    public static function formatLayoverTooltipDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m.'m';
        }
        if ($m === 0) {
            return $h.'h';
        }

        return $h.'h '.$m.'m';
    }

    /**
     * Airport label for layover tooltips: City (CODE), CODE, or fallback.
     */
    public static function formatLayoverTooltipAirport(string $city, string $code): string
    {
        $city = trim($city);
        $code = strtoupper(trim($code));
        if ($city !== '' && $code !== '') {
            return $city.' ('.$code.')';
        }
        if ($code !== '') {
            return $code;
        }

        return 'Layover airport unavailable';
    }

    /**
     * @param  list<array<string, mixed>>  $formattedSegments
     * @return list<string>|null
     */
    public static function buildLayoverTooltipLines(array $formattedSegments, bool $connectionDetailsUnavailable): ?array
    {
        if ($connectionDetailsUnavailable || count($formattedSegments) < 2) {
            return null;
        }

        $lines = [];
        $n = count($formattedSegments);
        for ($i = 0; $i < $n - 1; $i++) {
            $seg = $formattedSegments[$i];
            $minsRaw = $seg['layover_duration_minutes_after'] ?? null;
            $mins = is_numeric($minsRaw) ? max(0, (int) $minsRaw) : null;
            $city = trim((string) ($seg['destination_city'] ?? ''));
            $code = strtoupper(trim((string) ($seg['destination'] ?? '')));

            if ($mins === null || $mins <= 0) {
                $lines[] = ($code !== '' || $city !== '')
                    ? 'Layover · '.self::formatLayoverTooltipAirport($city, $code)
                    : 'Layover details unavailable';

                continue;
            }

            $lines[] = self::formatLayoverTooltipDuration($mins).' layover · '.self::formatLayoverTooltipAirport($city, $code);
        }

        return $lines === [] ? null : $lines;
    }

    /**
     * Layover rows for the public details panel (between segment timeline cards).
     *
     * @param  list<array<string, mixed>>  $formattedSegments
     * @return list<array<string, mixed>>
     */
    public static function buildLayoversDisplay(array $formattedSegments, bool $connectionDetailsUnavailable): array
    {
        if ($connectionDetailsUnavailable || count($formattedSegments) < 2) {
            return [];
        }

        $rows = [];
        $n = count($formattedSegments);
        for ($i = 0; $i < $n - 1; $i++) {
            $seg = $formattedSegments[$i];
            $minsRaw = $seg['layover_duration_minutes_after'] ?? null;
            $mins = is_numeric($minsRaw) ? max(0, (int) $minsRaw) : null;
            $city = trim((string) ($seg['destination_city'] ?? ''));
            $code = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $airport = self::formatLayoverTooltipAirport($city, $code);

            if ($mins === null || $mins <= 0) {
                $label = $airport !== 'Layover airport unavailable'
                    ? 'Waiting duration · '.$airport
                    : 'Waiting duration · details unavailable';
            } else {
                $label = 'Waiting duration · '.self::formatItineraryBlockDuration($mins).' in '.$airport;
            }

            $rows[] = [
                'after_segment_index' => $i,
                'duration_minutes' => $mins,
                'airport_code' => $code !== '' ? $code : null,
                'airport_city' => $city !== '' ? $city : null,
                'label' => $label,
            ];
        }

        return $rows;
    }

    /**
     * Grouped fare / baggage fields for the details panel (customer-safe only).
     *
     * @return array<string, mixed>
     */
    public static function buildFareSummaryDisplay(
        array $offer,
        string $primaryDisplayName,
        ?string $bagChecked,
        ?string $bagCabin,
        ?string $bagSummary,
    ): array {
        $bagChecked = BaggageDisplayNormalizer::forDisplay($bagChecked);
        $bagCabin = BaggageDisplayNormalizer::forDisplay($bagCabin);
        $bagSummaryRaw = $bagSummary !== null ? trim($bagSummary) : '';
        $airlineCode = strtoupper(trim((string) ($offer['airline_code'] ?? $offer['primary_display_carrier'] ?? '')));
        $flightNumber = trim((string) ($offer['flight_number'] ?? ''));
        $cabin = trim((string) ($offer['cabin'] ?? ''));
        $fareFamily = trim((string) ($offer['fare_family'] ?? ''));
        $refundable = (bool) ($offer['refundable'] ?? false);
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        $providerLabel = match ($provider) {
            'sabre' => 'Sabre',
            'duffel' => 'Duffel',
            'iati' => 'IATI',
            default => '',
        };

        $airlineLine = $primaryDisplayName !== ''
            ? ($airlineCode !== '' ? $primaryDisplayName.' ('.$airlineCode.')' : $primaryDisplayName)
            : ($airlineCode !== '' ? $airlineCode : null);

        $baggageLines = [];
        if ($bagSummaryRaw !== '') {
            $baggageLines[] = BaggageDisplayNormalizer::forDisplay($bagSummary);
        } else {
            $baggageLines[] = 'Checked: '.$bagChecked;
            $baggageLines[] = 'Carry-on: '.$bagCabin;
        }

        return [
            'airline' => $airlineLine,
            'flight_numbers' => $flightNumber !== '' ? $flightNumber : null,
            'cabin' => $cabin !== '' ? $cabin : null,
            'refund_status' => $refundable ? 'Refundable' : 'Non-refundable',
            'fare_family' => $fareFamily !== '' ? $fareFamily : null,
            'baggage_lines' => $baggageLines,
            'provider_label' => $providerLabel !== '' ? $providerLabel : null,
            'mixed_carrier' => (bool) ($offer['mixed_carrier'] ?? false),
            'marketing_carrier_chain' => is_array($offer['marketing_carrier_chain'] ?? null)
                ? implode(' + ', array_map('strval', $offer['marketing_carrier_chain']))
                : null,
            'validating_carrier' => isset($offer['validating_carrier']) && trim((string) $offer['validating_carrier']) !== ''
                ? strtoupper(trim((string) $offer['validating_carrier']))
                : null,
        ];
    }

    /**
     * BF5/BF6: branded fare chips on search cards (display-only or selectable when selection gate is on).
     *
     * @param  list<array<string, mixed>>  $fareFamilyOptionsDisplay
     * @param  array<string, mixed>  $offer
     * @return array{
     *     branded_fares_display_enabled: bool,
     *     branded_fares_selection_enabled: bool,
     *     branded_fares_selection_active: bool,
     *     has_branded_fares: bool,
     *     has_fare_choice_options: bool,
     *     has_multiple_fare_choices: bool,
     *     has_synthetic_default_fare: bool,
     *     universal_fare_selection_active: bool,
     *     branded_fares_display_options: list<array<string, mixed>>,
     *     branded_fares_more_count: int,
     *     branded_fares_display_label: string|null,
     *     fare_family_options_display: list<array<string, mixed>>
     * }
     */
    public static function buildBrandedFaresPresentationFields(array $fareFamilyOptionsDisplay, array $offer = []): array
    {
        if (PiaNdcFareFamilyPolicy::appliesToOffer($offer)) {
            return self::buildPiaNdcProviderBackedFarePresentation($offer);
        }

        $displayEnabled = self::brandedFaresDisplayEnabledForOffer($offer);
        $selectionEnabled = self::brandedFaresSelectionEnabledForOffer($offer);
        $selectionActive = $displayEnabled && $selectionEnabled;
        $allOptions = self::applyFareFamilySelectionGate(
            self::enrichFareFamilyOptionsWithDisplayPrices($fareFamilyOptionsDisplay, $offer),
            $selectionActive,
        );
        $hasBrandedFares = count($allOptions) >= 2;
        $universalActive = self::universalFareChoiceEnabledForOffer($offer);

        $emptyBase = [
            'branded_fares_display_enabled' => $displayEnabled,
            'branded_fares_selection_enabled' => $selectionEnabled,
            'branded_fares_selection_active' => $selectionActive,
            'has_branded_fares' => false,
            'has_fare_choice_options' => false,
            'has_multiple_fare_choices' => false,
            'has_synthetic_default_fare' => false,
            'universal_fare_selection_active' => false,
            'single_direct_fare_on_card' => false,
            'branded_fares_display_options' => [],
            'branded_fares_more_count' => 0,
            'branded_fares_display_label' => null,
            'fare_family_options_display' => [],
        ];

        if ($hasBrandedFares && $displayEnabled) {
            $visible = $allOptions;
            $cardOptions = [];
            foreach ($visible as $option) {
                $cardOptions[] = self::mapBrandedFareCardDisplayOption($option, $selectionActive);
            }

            return [
                'branded_fares_display_enabled' => true,
                'branded_fares_selection_enabled' => $selectionEnabled,
                'branded_fares_selection_active' => $selectionActive,
                'has_branded_fares' => true,
                'has_fare_choice_options' => true,
                'has_multiple_fare_choices' => true,
                'has_synthetic_default_fare' => false,
                'universal_fare_selection_active' => $universalActive,
                'branded_fares_display_options' => $cardOptions,
                'branded_fares_more_count' => 0,
                'branded_fares_display_label' => $selectionActive ? 'Choose fare family' : 'Fare family preview',
                'fare_family_options_display' => $allOptions,
            ];
        }

        if ($hasBrandedFares && ! $displayEnabled) {
            return [
                ...$emptyBase,
                'has_branded_fares' => true,
            ];
        }

        if (! $universalActive) {
            return $emptyBase;
        }

        $defaultOption = self::buildSyntheticDefaultFareChoiceOption($offer);
        if ($defaultOption === null) {
            return $emptyBase;
        }

        $defaultOptions = self::applyFareFamilySelectionGate(
            self::enrichFareFamilyOptionsWithDisplayPrices([$defaultOption], $offer),
            true,
        );

        return [
            'branded_fares_display_enabled' => true,
            'branded_fares_selection_enabled' => true,
            'branded_fares_selection_active' => true,
            'has_branded_fares' => false,
            'has_fare_choice_options' => true,
            'has_multiple_fare_choices' => false,
            'has_synthetic_default_fare' => true,
            'universal_fare_selection_active' => true,
            'single_direct_fare_on_card' => true,
            'branded_fares_display_options' => [
                self::mapBrandedFareCardDisplayOption($defaultOptions[0], true),
            ],
            'branded_fares_more_count' => 0,
            'branded_fares_display_label' => 'Select fare option',
            'fare_family_options_display' => $defaultOptions,
        ];
    }

    public static function universalFareChoiceEnabledForOffer(array $offer = []): bool
    {
        if (PiaNdcFareFamilyPolicy::appliesToOffer($offer)) {
            return false;
        }

        return (bool) config('ota.universal_fare_choice_enabled', true);
    }

    /**
     * PIA NDC: single provider-backed fare family only (no synthetic branded options).
     *
     * @param  array<string, mixed>  $offer
     * @return array{
     *     branded_fares_display_enabled: bool,
     *     branded_fares_selection_enabled: bool,
     *     branded_fares_selection_active: bool,
     *     has_branded_fares: bool,
     *     has_fare_choice_options: bool,
     *     has_multiple_fare_choices: bool,
     *     has_synthetic_default_fare: bool,
     *     universal_fare_selection_active: bool,
     *     branded_fares_display_options: list<array<string, mixed>>,
     *     branded_fares_more_count: int,
     *     branded_fares_display_label: string|null,
     *     fare_family_options_display: list<array<string, mixed>>
     * }
     */
    protected static function buildPiaNdcProviderBackedFarePresentation(array $offer): array
    {
        $allOptions = PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer);
        if ($allOptions === []) {
            return [
                'branded_fares_display_enabled' => false,
                'branded_fares_selection_enabled' => false,
                'branded_fares_selection_active' => false,
                'has_branded_fares' => false,
                'has_fare_choice_options' => false,
                'has_multiple_fare_choices' => false,
                'has_synthetic_default_fare' => false,
                'universal_fare_selection_active' => false,
                'branded_fares_display_options' => [],
                'branded_fares_more_count' => 0,
                'branded_fares_display_label' => null,
                'fare_family_options_display' => [],
            ];
        }

        $enriched = self::enrichFareFamilyOptionsWithDisplayPrices($allOptions, $offer);
        $readinessService = app(PiaNdcSelectedFareReadinessService::class);
        foreach ($enriched as $idx => $option) {
            $memberOffer = null;
            $sourceId = trim((string) ($option['source_offer_id'] ?? ''));
            if ($sourceId !== '') {
                $members = data_get($offer, 'itinerary_fare_group.members_by_id');
                if (is_array($members) && is_array($members[$sourceId] ?? null)) {
                    $memberOffer = $members[$sourceId];
                }
            }
            $enriched[$idx] = OfferBaggageResolver::enrichFareOptionRow($option, $memberOffer ?? $offer);
            $optionReady = $readinessService->isOptionStructurallyReady($enriched[$idx], $offer);
            $enriched[$idx]['pia_ndc_pnr_ready'] = $optionReady;
        }
        $selectionEnabled = count(array_filter(
            $enriched,
            static fn (array $row): bool => (bool) ($row['pia_ndc_pnr_ready'] ?? false),
        )) >= 2;
        $selectionActive = $selectionEnabled;
        $visible = $enriched;
        $cardOptions = [];
        foreach ($visible as $option) {
            $optionReady = (bool) ($option['pia_ndc_pnr_ready'] ?? false);
            $option['selectable'] = $selectionActive && $optionReady;
            $option['display_only'] = ! $option['selectable'];
            $cardOptions[] = self::mapBrandedFareCardDisplayOption($option, $selectionActive && $optionReady);
        }

        foreach ($enriched as $idx => $option) {
            $optionReady = (bool) ($option['pia_ndc_pnr_ready'] ?? false);
            $enriched[$idx]['selectable'] = $selectionActive && $optionReady;
            $enriched[$idx]['display_only'] = ! $enriched[$idx]['selectable'];
        }

        $singleDirect = count($enriched) === 1;

        return [
            'branded_fares_display_enabled' => true,
            'branded_fares_selection_enabled' => $selectionEnabled,
            'branded_fares_selection_active' => $selectionActive,
            'has_branded_fares' => $selectionEnabled,
            'has_fare_choice_options' => true,
            'has_multiple_fare_choices' => $selectionEnabled,
            'has_synthetic_default_fare' => false,
            'universal_fare_selection_active' => false,
            'single_direct_fare_on_card' => $singleDirect,
            'branded_fares_display_options' => $cardOptions,
            'branded_fares_more_count' => 0,
            'branded_fares_display_label' => $selectionEnabled ? 'Choose fare family' : 'Fare family',
            'fare_family_options_display' => $enriched,
        ];
    }

    /**
     * Supplier total for synthetic default fare cards (matches results API pricing fields).
     *
     * @param  array<string, mixed>  $offer
     */
    public static function resolveOfferSupplierTotalForSyntheticFare(array $offer): float
    {
        $total = (float) ($offer['supplier_total_source'] ?? $offer['supplier_total'] ?? data_get($offer, 'fare_breakdown.supplier_total', 0));
        if ($total <= 0) {
            $total = (float) (($offer['base_fare'] ?? 0) + ($offer['taxes'] ?? 0));
        }

        return $total > 0 ? $total : 0.0;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null
     */
    public static function buildSyntheticDefaultFareChoiceOption(array $offer): ?array
    {
        $offerId = substr(hash('sha256', (string) ($offer['offer_id'] ?? $offer['id'] ?? 'offer')), 0, 8);
        $cabin = strtolower(trim((string) ($offer['cabin'] ?? '')));
        $name = match (true) {
            $cabin !== '' && $cabin !== 'economy' => ucfirst($cabin).' Fare',
            $cabin === 'economy' => 'Economy Fare',
            default => 'Standard Fare',
        };

        $baggageResolved = OfferBaggageResolver::resolveFromOffer($offer);
        $carryOn = BaggageDisplayNormalizer::forDisplay($baggageResolved['cabin']);
        $checked = BaggageDisplayNormalizer::forDisplay($baggageResolved['checked']);
        $baggageSummary = BaggageDisplayNormalizer::normalizeLabel($baggageResolved['summary']);
        if ($baggageSummary === null) {
            $baggageSummary = BaggageDisplayNormalizer::formatAllowance(
                $checked !== BaggageDisplayNormalizer::NOT_PROVIDED ? $checked : null,
                $carryOn !== BaggageDisplayNormalizer::NOT_PROVIDED ? $carryOn : null,
            )['summary'];
        }

        $priceTotal = self::resolveOfferSupplierTotalForSyntheticFare($offer);
        $currency = strtoupper(trim((string) ($offer['supplier_currency'] ?? $offer['pricing_currency'] ?? $offer['currency'] ?? data_get($offer, 'fare_breakdown.currency', 'PKR'))));

        $row = [
            'name' => $name,
            'brand_name' => $name,
            'price_total' => $priceTotal > 0 ? $priceTotal : null,
            'currency' => $currency !== '' ? $currency : null,
            'carry_on_summary' => $carryOn !== BaggageDisplayNormalizer::NOT_PROVIDED ? $carryOn : null,
            'check_in_summary' => $checked !== BaggageDisplayNormalizer::NOT_PROVIDED ? $checked : null,
            'baggage_summary' => $baggageSummary,
            'refundable_display' => self::nullableTrimmedString($offer['refund_rule'] ?? null),
            'modification_rule' => self::nullableTrimmedString($offer['change_rule'] ?? null),
            'cancellation_rule' => self::nullableTrimmedString($offer['cancellation_rule'] ?? null),
            'cabin' => $cabin !== '' ? $cabin : null,
            'is_default' => true,
            'is_synthetic_default' => true,
            'option_key' => 'standard-fare-'.$offerId,
        ];

        $mapped = self::mapFareFamilyOptionRow($row, 0);
        if ($mapped === null) {
            return null;
        }

        $mapped['option_key'] = 'standard-fare-'.$offerId;
        $mapped['is_default'] = true;
        $mapped['is_synthetic_default'] = true;
        $mapped['branded_fare_supported'] = false;
        $mapped['selectable'] = true;
        $mapped['display_only'] = false;
        $mapped = array_merge($mapped, self::deriveBrandedFareOptionDisplayPrice($mapped, $offer));
        $customerPrice = (int) round((float) ($offer['final_customer_price'] ?? $offer['displayed_price'] ?? 0));
        if ($customerPrice > 0) {
            $mapped['displayed_price'] = $customerPrice;
            $mapped['displayed_currency'] = 'PKR';
            $mapped['price_display'] = 'PKR '.number_format($customerPrice, 0, '.', ',');
        } elseif (($mapped['displayed_price'] ?? null) === null) {
            $fallbackPrice = (int) round((float) ($mapped['price_total'] ?? 0));
            if ($fallbackPrice > 0) {
                $mapped['displayed_price'] = $fallbackPrice;
                $mapped['displayed_currency'] = 'PKR';
                $mapped['price_display'] = 'PKR '.number_format($fallbackPrice, 0, '.', ',');
            }
        }

        return $mapped;
    }

    public static function brandedFaresDisplayEnabled(): bool
    {
        return (bool) config('suppliers.sabre.branded_fares_display_enabled', false);
    }

    /**
     * Provider-aware branded fare display gate (IATI uses suppliers.iati config; Sabre unchanged).
     *
     * @param  array<string, mixed>  $offer
     */
    public static function brandedFaresDisplayEnabledForOffer(array $offer = []): bool
    {
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        if ($provider === SupplierProvider::PiaNdc->value) {
            return PiaNdcFareFamilyPolicy::hasOrderCreateReadyContext(
                PiaNdcFareFamilyPolicy::extractProviderContextFromOffer($offer),
            );
        }
        if ($provider === 'iati') {
            return (bool) config('suppliers.iati.branded_fares_display_enabled', true);
        }

        return self::brandedFaresDisplayEnabled();
    }

    public static function brandedFaresSelectionEnabled(): bool
    {
        return (bool) config('suppliers.sabre.branded_fares_selection_enabled', false);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public static function brandedFaresSelectionEnabledForOffer(array $offer = []): bool
    {
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        if ($provider === SupplierProvider::PiaNdc->value) {
            return PiaNdcFareFamilyPolicy::hasMultipleProviderBackedBrands($offer);
        }
        if ($provider === 'iati') {
            return (bool) config('suppliers.iati.branded_fares_selection_enabled', true);
        }

        return self::brandedFaresSelectionEnabled();
    }

    public static function brandedFaresSelectionActive(): bool
    {
        return self::brandedFaresDisplayEnabled() && self::brandedFaresSelectionEnabled();
    }

    /**
     * Provider-aware checkout selection gate (IATI uses suppliers.iati; Sabre unchanged).
     *
     * @param  array<string, mixed>  $offer
     */
    public static function brandedFaresSelectionActiveForOffer(array $offer): bool
    {
        return self::brandedFaresDisplayEnabledForOffer($offer)
            && self::brandedFaresSelectionEnabledForOffer($offer);
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>
     */
    protected static function enrichFareFamilyOptionsWithDisplayPrices(array $options, array $offer): array
    {
        foreach ($options as $idx => $option) {
            $options[$idx] = array_merge($option, self::deriveBrandedFareOptionDisplayPrice($option, $offer));
        }

        return $options;
    }

    /**
     * Display PKR (or approximate) for branded fare option rows without mutating supplier amounts.
     *
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $offer
     * @return array{
     *     displayed_price: int|null,
     *     displayed_currency: string|null,
     *     price_display: string|null,
     *     price_is_approximate: bool
     * }
     */
    public static function deriveBrandedFareOptionDisplayPrice(array $option, array $offer): array
    {
        $priceTotal = isset($option['price_total']) && is_numeric($option['price_total'])
            ? (float) $option['price_total']
            : null;
        if ($priceTotal === null || $priceTotal <= 0) {
            return [
                'displayed_price' => null,
                'displayed_currency' => null,
                'price_display' => null,
                'price_is_approximate' => false,
            ];
        }

        if (! empty($option['is_synthetic_default'])) {
            $customerPrice = (int) round((float) ($offer['final_customer_price'] ?? $offer['displayed_price'] ?? 0));
            if ($customerPrice > 0) {
                return [
                    'displayed_price' => $customerPrice,
                    'displayed_currency' => 'PKR',
                    'price_display' => 'PKR '.number_format($customerPrice, 0, '.', ','),
                    'price_is_approximate' => false,
                ];
            }
        }

        $optionCurrency = strtoupper(trim((string) ($option['currency'] ?? '')));
        $pricingCurrency = strtoupper(trim((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR')));
        $finalCustomerPrice = (float) ($offer['final_customer_price'] ?? 0);
        $supplierTotalSource = (float) ($offer['supplier_total_source'] ?? 0);
        if ($supplierTotalSource <= 0) {
            $supplierTotalSource = self::resolveOfferSupplierTotalForSyntheticFare($offer);
        }
        if ($supplierTotalSource <= 0) {
            $supplierTotalSource = (float) (($offer['base_fare'] ?? 0) + ($offer['taxes'] ?? 0));
        }
        $fxRate = (float) (data_get($offer, 'pricing_components.fx_rate') ?? 0);

        if ($optionCurrency === 'PKR' || ($optionCurrency !== '' && $optionCurrency === $pricingCurrency && $pricingCurrency === 'PKR')) {
            if ($finalCustomerPrice > 0 && $supplierTotalSource > 0 && $priceTotal > 0) {
                $amount = (int) round($priceTotal * $finalCustomerPrice / $supplierTotalSource);
            } else {
                $amount = (int) round($priceTotal);
            }

            return [
                'displayed_price' => $amount,
                'displayed_currency' => 'PKR',
                'price_display' => 'PKR '.number_format($amount, 0, '.', ','),
                'price_is_approximate' => false,
            ];
        }

        if ($finalCustomerPrice > 0 && $supplierTotalSource > 0) {
            $approx = (int) round($priceTotal * $finalCustomerPrice / $supplierTotalSource);

            return [
                'displayed_price' => $approx,
                'displayed_currency' => 'PKR',
                'price_display' => 'Approx. PKR '.number_format($approx, 0, '.', ','),
                'price_is_approximate' => true,
            ];
        }

        if ($fxRate > 0 && $optionCurrency !== '' && $optionCurrency !== 'PKR') {
            $approx = (int) round($priceTotal * $fxRate);

            return [
                'displayed_price' => $approx,
                'displayed_currency' => 'PKR',
                'price_display' => 'Approx. PKR '.number_format($approx, 0, '.', ','),
                'price_is_approximate' => true,
            ];
        }

        if ($optionCurrency !== '') {
            $amount = (int) round($priceTotal);

            return [
                'displayed_price' => $amount,
                'displayed_currency' => $optionCurrency,
                'price_display' => $optionCurrency.' '.number_format($amount, 0, '.', ','),
                'price_is_approximate' => false,
            ];
        }

        return [
            'displayed_price' => null,
            'displayed_currency' => null,
            'price_display' => null,
            'price_is_approximate' => false,
        ];
    }

    /**
     * BF6: safe checkout intent payload (no raw Sabre refs or client-trusted payable totals).
     *
     * @param  array<string, mixed>  $resolved
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public static function sanitizeSelectedFareFamilyIntent(array $resolved, array $offer): array
    {
        if (PiaNdcFareFamilyPolicy::appliesToOffer($offer)) {
            $sanitized = PiaNdcFareFamilyPolicy::sanitizeSelectedIntentForPiaNdc($resolved, $offer);

            return is_array($sanitized) ? $sanitized : [];
        }

        $enriched = array_merge($resolved, self::deriveBrandedFareOptionDisplayPrice($resolved, $offer));
        $priceIsApproximate = (bool) ($enriched['price_is_approximate'] ?? false);
        $name = (string) ($enriched['name'] ?? '');
        $baggageSummary = self::nullableTrimmedString($enriched['baggage_summary'] ?? null);

        $fareBasisBySeg = is_array($enriched['fare_basis_codes_by_segment'] ?? null)
            ? array_values($enriched['fare_basis_codes_by_segment'])
            : (is_array($enriched['fare_basis_codes'] ?? null) ? array_values($enriched['fare_basis_codes']) : []);
        $bookingBySeg = is_array($enriched['booking_classes_by_segment'] ?? null)
            ? array_values($enriched['booking_classes_by_segment'])
            : [];
        $cabinBySeg = is_array($enriched['cabin_by_segment'] ?? null)
            ? array_values($enriched['cabin_by_segment'])
            : [];

        $segmentCount = count(is_array($offer['segments'] ?? null) ? $offer['segments'] : []);
        $expectedSliceCount = (int) ($enriched['segment_slice_count'] ?? 0);
        if ($expectedSliceCount > $segmentCount) {
            $segmentCount = $expectedSliceCount;
        }

        $singleFb = self::nullableTrimmedString($enriched['fare_basis'] ?? null);
        if ($fareBasisBySeg === [] && $singleFb !== null && $segmentCount <= 1) {
            $fareBasisBySeg = [strtoupper($singleFb)];
        }
        $singleBc = self::nullableTrimmedString($enriched['booking_class'] ?? null);
        if ($bookingBySeg === [] && $singleBc !== null && $segmentCount <= 1) {
            $bookingBySeg = [strtoupper($singleBc)];
        }
        if ($segmentCount > 1 && count($fareBasisBySeg) === 1) {
            $fareBasisBySeg = [];
        }
        if ($segmentCount > 1 && count($bookingBySeg) === 1) {
            $bookingBySeg = [];
        }

        return array_filter([
            'option_key' => (string) ($enriched['option_key'] ?? ''),
            'id' => self::nullableTrimmedString($enriched['id'] ?? $enriched['brand_id'] ?? null),
            'name' => $name,
            'brand_name' => $name !== '' ? $name : null,
            'brand_code' => self::nullableTrimmedString($enriched['brand_code'] ?? $enriched['supplier_brand_code'] ?? null),
            'departure_fare_key' => self::nullableTrimmedString($enriched['departure_fare_key'] ?? null),
            'return_fare_key' => self::nullableTrimmedString($enriched['return_fare_key'] ?? null),
            'displayed_price' => isset($enriched['displayed_price']) && is_numeric($enriched['displayed_price'])
                ? (int) $enriched['displayed_price']
                : null,
            'displayed_currency' => self::nullableTrimmedString($enriched['displayed_currency'] ?? null),
            'price_display' => self::nullableTrimmedString($enriched['price_display'] ?? null),
            'price_is_approximate' => $priceIsApproximate,
            'is_price_approximate' => $priceIsApproximate,
            'validation_note' => self::SELECTED_FARE_VALIDATION_NOTE,
            'cabin' => self::nullableTrimmedString($enriched['cabin'] ?? null),
            'booking_class' => $singleBc,
            'fare_basis' => $singleFb,
            'booking_classes_by_segment' => $bookingBySeg !== [] ? $bookingBySeg : null,
            'fare_basis_codes_by_segment' => $fareBasisBySeg !== [] ? $fareBasisBySeg : null,
            'fare_basis_codes' => $fareBasisBySeg !== [] ? $fareBasisBySeg : null,
            'cabin_by_segment' => $cabinBySeg !== [] ? $cabinBySeg : null,
            'segment_slice_count' => $segmentCount > 0 ? $segmentCount : null,
            'pricing_information_index' => isset($enriched['pricing_information_index']) && is_numeric($enriched['pricing_information_index'])
                ? (int) $enriched['pricing_information_index']
                : null,
            'baggage_summary' => $baggageSummary,
            'baggage' => $baggageSummary,
            'branded_fare_supported' => empty($enriched['is_synthetic_default']),
            'selectable' => self::brandedFaresSelectionActive(),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');
    }

    /**
     * BF6-FIX4: keep first-resolved display price when reaffirming from a different offer ratio.
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $fresh
     * @return array{intent: array<string, mixed>, estimate_drift_detected: bool}
     */
    public static function preserveStickySelectedFareFamilyDisplay(array $stored, array $fresh): array
    {
        $storedKey = trim((string) ($stored['option_key'] ?? ''));
        $freshKey = trim((string) ($fresh['option_key'] ?? ''));
        if ($storedKey === '' || $freshKey === '' || $storedKey !== $freshKey) {
            return ['intent' => $fresh, 'estimate_drift_detected' => false];
        }

        $previousPriceDisplay = trim((string) ($stored['price_display'] ?? ''));
        $freshPriceDisplay = trim((string) ($fresh['price_display'] ?? ''));
        $estimateDriftDetected = $previousPriceDisplay !== ''
            && $freshPriceDisplay !== ''
            && $previousPriceDisplay !== $freshPriceDisplay;

        $hasStickyPrice = isset($stored['displayed_price'])
            && is_numeric($stored['displayed_price'])
            && (int) $stored['displayed_price'] > 0
            && $previousPriceDisplay !== '';

        $merged = $fresh;
        if ($hasStickyPrice) {
            foreach (['price_display', 'displayed_price', 'displayed_currency', 'price_is_approximate', 'is_price_approximate'] as $field) {
                if (array_key_exists($field, $stored) && $stored[$field] !== null && $stored[$field] !== '') {
                    $merged[$field] = $stored[$field];
                }
            }
        }

        return ['intent' => $merged, 'estimate_drift_detected' => $estimateDriftDetected];
    }

    /**
     * BF6-FIX4: checkout sidebar fare-rules baggage/cabin from selected branded intent when present.
     *
     * @param  array<string, mixed>|null  $offer
     * @param  array<string, mixed>|null  $selectedIntent
     * @return array{baggage_display: string, cabin_display: string|null, uses_selected_fare_family: bool}
     */
    public static function buildCheckoutFareRulesSidebar(?array $offer, ?array $selectedIntent): array
    {
        $baseBaggage = '';
        $baseCabin = '';
        if (is_array($offer)) {
            $baggage = $offer['baggage'] ?? null;
            if (is_array($baggage)) {
                $baseBaggage = trim((string) (($baggage['summary'] ?? '') ?: ($baggage['checked'] ?? '')));
            } else {
                $baseBaggage = trim((string) ($baggage ?? ''));
            }
            $baseCabin = trim((string) ($offer['cabin'] ?? ''));
        }

        $usesSelected = is_array($selectedIntent) && trim((string) ($selectedIntent['name'] ?? '')) !== '';
        if ($usesSelected) {
            $selectedBaggage = self::nullableTrimmedString($selectedIntent['baggage'] ?? $selectedIntent['baggage_summary'] ?? null);
            $selectedCabin = self::nullableTrimmedString($selectedIntent['cabin'] ?? null);

            return [
                'baggage_display' => $selectedBaggage ?? ($baseBaggage !== '' ? $baseBaggage : 'Baggage per fare rules'),
                'cabin_display' => $selectedCabin ?? ($baseCabin !== '' ? ucfirst(str_replace('_', ' ', $baseCabin)) : null),
                'uses_selected_fare_family' => true,
            ];
        }

        return [
            'baggage_display' => $baseBaggage !== '' ? $baseBaggage : 'Baggage per fare rules',
            'cabin_display' => $baseCabin !== '' ? ucfirst(str_replace('_', ' ', $baseCabin)) : null,
            'uses_selected_fare_family' => false,
        ];
    }

    /**
     * BF6-FIX1: normalized checkout view model for selected branded fare intent.
     *
     * @param  array<string, mixed>|null  $intent
     * @return array<string, mixed>|null
     */
    public static function buildSelectedFareFamilyCheckoutView(?array $intent): ?array
    {
        if (! is_array($intent)) {
            return null;
        }

        $name = trim((string) ($intent['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $displayedPrice = isset($intent['displayed_price']) && is_numeric($intent['displayed_price'])
            ? (int) $intent['displayed_price']
            : null;
        $priceIsApproximate = (bool) ($intent['price_is_approximate'] ?? $intent['is_price_approximate'] ?? false);

        return array_filter([
            'name' => $name,
            'brand_code' => self::nullableTrimmedString($intent['brand_code'] ?? null),
            'price_display' => self::nullableTrimmedString($intent['price_display'] ?? null),
            'price_is_approximate' => $priceIsApproximate,
            'displayed_price' => $displayedPrice,
            'displayed_currency' => self::nullableTrimmedString($intent['displayed_currency'] ?? null),
            'validation_note' => self::nullableTrimmedString($intent['validation_note'] ?? null) ?? self::SELECTED_FARE_VALIDATION_NOTE,
            'has_checkout_estimate' => $displayedPrice !== null && $displayedPrice > 0,
            'baggage_summary' => self::nullableTrimmedString($intent['baggage_summary'] ?? null),
            'cabin' => self::nullableTrimmedString($intent['cabin'] ?? null),
            'booking_class' => self::nullableTrimmedString($intent['booking_class'] ?? null),
            'fare_basis' => self::nullableTrimmedString($intent['fare_basis'] ?? null),
        ], static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== false);
    }

    /**
     * BF6-FIX2: checkout sidebar estimate presentation from server-rebuilt intent only.
     *
     * @param  array<string, mixed>|null  $intent
     * @return array<string, mixed>|null
     */
    public static function buildCheckoutSelectedFareEstimatePresentation(?array $intent): ?array
    {
        $view = self::buildSelectedFareFamilyCheckoutView($intent);
        if ($view === null || empty($view['has_checkout_estimate'])) {
            return null;
        }

        $displayedPrice = (int) ($view['displayed_price'] ?? 0);
        $currency = strtoupper(trim((string) ($view['displayed_currency'] ?? 'PKR')));
        $priceIsApproximate = (bool) ($view['price_is_approximate'] ?? false);
        $priceDisplay = trim((string) ($view['price_display'] ?? ''));
        if ($priceDisplay === '' && $displayedPrice > 0) {
            $prefix = $priceIsApproximate ? 'Approx. ' : '';
            $priceDisplay = $prefix.$currency.' '.number_format($displayedPrice, 0, '.', ',');
        }

        return [
            'label' => 'Estimated selected fare',
            'displayed_price' => $displayedPrice,
            'displayed_currency' => $currency,
            'price_display' => $priceDisplay,
            'price_is_approximate' => $priceIsApproximate,
            'validation_note' => (string) ($view['validation_note'] ?? self::SELECTED_FARE_VALIDATION_NOTE),
            'has_checkout_estimate' => true,
        ];
    }

    /**
     * BF6-FIX7: email-safe selected fare family block from server-stored booking meta intent only.
     *
     * @param  array<string, mixed>|null  $intent
     * @return array<string, mixed>|null
     */
    public static function buildSelectedFareFamilyEmailSection(?array $intent): ?array
    {
        $view = self::buildSelectedFareFamilyCheckoutView($intent);
        if ($view === null) {
            return null;
        }

        $estimate = self::buildCheckoutSelectedFareEstimatePresentation($intent);
        $name = trim((string) ($view['name'] ?? ''));
        $brandCode = self::nullableTrimmedString($view['brand_code'] ?? null);
        $fareFamilyLabel = $brandCode !== null && $brandCode !== ''
            ? $name.' ('.$brandCode.')'
            : $name;

        $estimatedFareDisplay = trim((string) ($estimate['price_display'] ?? $view['price_display'] ?? ''));
        if ($estimatedFareDisplay === '' && is_array($estimate) && ! empty($estimate['displayed_price'])) {
            $prefix = ! empty($estimate['price_is_approximate']) ? 'Approx. ' : '';
            $currency = strtoupper(trim((string) ($estimate['displayed_currency'] ?? 'PKR')));
            $estimatedFareDisplay = $prefix.$currency.' '.number_format((int) $estimate['displayed_price'], 0, '.', ',');
        }

        return array_filter([
            'fare_family_label' => $fareFamilyLabel !== '' ? $fareFamilyLabel : null,
            'estimated_fare_label' => 'Estimated selected fare',
            'estimated_fare_display' => $estimatedFareDisplay !== '' ? $estimatedFareDisplay : null,
            'baggage' => self::nullableTrimmedString($view['baggage_summary'] ?? null),
            'cabin' => self::nullableTrimmedString($view['cabin'] ?? null),
            'booking_class' => self::nullableTrimmedString($view['booking_class'] ?? null),
            'fare_basis' => self::nullableTrimmedString($view['fare_basis'] ?? null),
            'validation_note' => (string) ($view['validation_note'] ?? self::SELECTED_FARE_VALIDATION_NOTE),
            'payable_disclaimer' => self::SELECTED_FARE_PAYABLE_DISCLAIMER,
            'has_selected_fare_family' => true,
        ], static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== false);
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    protected static function applyFareFamilySelectionGate(array $options, bool $selectionActive): array
    {
        foreach ($options as $idx => $option) {
            $options[$idx]['selectable'] = $selectionActive;
            $options[$idx]['display_only'] = ! $selectionActive;
        }

        return $options;
    }

    /**
     * Compact card-chip row for collapsed search results.
     *
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    protected static function mapBrandedFareCardDisplayOption(array $option, bool $selectionActive = false): array
    {
        $brandCode = self::nullableTrimmedString($option['supplier_brand_code'] ?? $option['brand_code'] ?? null);
        $priceDisplay = self::nullableTrimmedString($option['price_display'] ?? null);
        if ($priceDisplay === null) {
            $priceDisplay = self::formatBrandedFarePriceDisplay(
                isset($option['price_total']) && is_numeric($option['price_total'])
                    ? (float) $option['price_total']
                    : null,
                self::nullableTrimmedString($option['currency'] ?? null),
            );
        }

        return [
            'option_key' => (string) ($option['option_key'] ?? ''),
            'name' => (string) ($option['name'] ?? ''),
            'brand_code' => $brandCode,
            'price_total' => isset($option['price_total']) && is_numeric($option['price_total'])
                ? (float) $option['price_total']
                : null,
            'price_display' => $priceDisplay,
            'displayed_price' => isset($option['displayed_price']) && is_numeric($option['displayed_price'])
                ? (int) $option['displayed_price']
                : null,
            'displayed_currency' => self::nullableTrimmedString($option['displayed_currency'] ?? null),
            'price_is_approximate' => (bool) ($option['price_is_approximate'] ?? false),
            'currency' => self::nullableTrimmedString($option['currency'] ?? null),
            'cabin' => self::nullableTrimmedString($option['cabin'] ?? null),
            'booking_class' => self::nullableTrimmedString($option['booking_class'] ?? null),
            'fare_basis' => self::nullableTrimmedString($option['fare_basis'] ?? null),
            'baggage_summary' => self::nullableTrimmedString($option['baggage_summary'] ?? null),
            'carry_on_summary' => self::nullableTrimmedString($option['carry_on_summary'] ?? null),
            'check_in_summary' => self::nullableTrimmedString($option['check_in_summary'] ?? null),
            'meal_included' => self::nullableTrimmedString($option['meal_included'] ?? null),
            'refundable_display' => self::nullableTrimmedString($option['refundable_display'] ?? null),
            'modification_rule' => self::nullableTrimmedString($option['modification_rule'] ?? null),
            'cancellation_rule' => self::nullableTrimmedString($option['cancellation_rule'] ?? null),
            'source_offer_id' => self::nullableTrimmedString($option['source_offer_id'] ?? null),
            'is_grouped_offer_option' => (bool) ($option['is_grouped_offer_option'] ?? false),
            'is_synthetic_default' => (bool) ($option['is_synthetic_default'] ?? false),
            'fare_product_disambiguator' => self::nullableTrimmedString($option['fare_product_disambiguator'] ?? null),
            'fare_variant_subtitle' => self::nullableTrimmedString($option['fare_variant_subtitle'] ?? $option['fare_product_disambiguator'] ?? null),
            'selectable' => (bool) ($option['selectable'] ?? $selectionActive),
            'display_only' => (bool) ($option['display_only'] ?? ! $selectionActive),
        ];
    }

    protected static function formatBrandedFarePriceDisplay(?float $priceTotal, ?string $currency): ?string
    {
        if ($priceTotal === null || $priceTotal <= 0) {
            return null;
        }
        $amount = (string) (int) round($priceTotal);
        $currency = trim((string) ($currency ?? ''));

        return $currency !== '' ? $currency.' '.$amount : $amount;
    }

    /**
     * Branded fare option cards — empty unless the normalized offer carries a structured multi-option list.
     *
     * @return list<array<string, mixed>>
     */
    public static function buildFareFamilyOptionsDisplay(array $offer): array
    {
        $candidates = [
            $offer['fare_family_options'] ?? null,
            $offer['branded_fares'] ?? null,
            $offer['fare_options'] ?? null,
        ];

        foreach ($candidates as $raw) {
            if (! is_array($raw) || $raw === []) {
                continue;
            }
            $mapped = [];
            foreach ($raw as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $option = self::mapFareFamilyOptionRow($row, (int) $index);
                if ($option !== null) {
                    $mapped[] = $option;
                }
            }
            if ($mapped !== []) {
                return self::markCheapestFareFamilyOptions($mapped);
            }
        }

        return [];
    }

    /**
     * Read-only audit: raw vs normalized vs displayed branded fare options (no supplier calls).
     *
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public static function auditBrandedFareOptionsVisibility(array $offer): array
    {
        $rawRows = [];
        foreach (['branded_fares', 'fare_family_options', 'fare_options'] as $key) {
            if (is_array($offer[$key] ?? null) && $offer[$key] !== []) {
                $rawRows = array_values($offer[$key]);
                break;
            }
        }

        $normalized = [];
        $hiddenReasons = [];
        $seenKeys = [];
        foreach ($rawRows as $index => $row) {
            if (! is_array($row)) {
                $hiddenReasons[] = 'invalid_segment_context';

                continue;
            }
            if (! isset($row['price_total']) && ! isset($row['total'])) {
                $hiddenReasons[] = 'missing_price';
            }
            if (trim((string) ($row['brand_code'] ?? $row['supplier_brand_code'] ?? '')) === '') {
                $hiddenReasons[] = 'missing_brand_code';
            }
            $mapped = self::mapFareFamilyOptionRow($row, (int) $index);
            if ($mapped === null) {
                $hiddenReasons[] = 'missing_fare_basis';

                continue;
            }
            $optionKey = (string) ($mapped['option_key'] ?? '');
            if ($optionKey !== '' && isset($seenKeys[$optionKey])) {
                $hiddenReasons[] = 'duplicate_option_key';

                continue;
            }
            if ($optionKey !== '') {
                $seenKeys[$optionKey] = true;
            }
            $normalized[] = $mapped;
        }

        $presentation = self::buildBrandedFaresPresentationFields($normalized, $offer);
        $displayed = is_array($presentation['branded_fares_display_options'] ?? null)
            ? $presentation['branded_fares_display_options']
            : [];

        return [
            'offer_count' => 1,
            'raw_brand_options_count' => count($rawRows),
            'normalized_brand_options_count' => count($normalized),
            'displayed_brand_options_count' => count($displayed),
            'hidden_brand_options_count' => max(0, count($normalized) - count($displayed)),
            'hidden_reason_codes' => array_values(array_unique($hiddenReasons)),
            'option_keys' => array_values(array_map(static fn (array $row): string => (string) ($row['option_key'] ?? ''), $normalized)),
            'brand_codes' => array_values(array_filter(array_map(
                static fn (array $row): string => (string) ($row['brand_code'] ?? ''),
                $normalized
            ))),
            'fare_basis_codes' => array_values(array_filter(array_map(
                static fn (array $row): string => (string) ($row['fare_basis'] ?? ''),
                $normalized
            ))),
            'prices' => array_values(array_map(
                static fn (array $row): ?float => isset($row['price_total']) && is_numeric($row['price_total']) ? (float) $row['price_total'] : null,
                $normalized
            )),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $mapped
     * @return list<array<string, mixed>>
     */
    protected static function markCheapestFareFamilyOptions(array $mapped): array
    {
        $cheapest = null;
        foreach ($mapped as $opt) {
            $price = $opt['price_total'] ?? null;
            if ($price === null || ! is_numeric($price) || (float) $price <= 0) {
                continue;
            }
            $p = (float) $price;
            if ($cheapest === null || $p < $cheapest) {
                $cheapest = $p;
            }
        }
        if ($cheapest === null) {
            return $mapped;
        }

        foreach ($mapped as $idx => $opt) {
            $price = $opt['price_total'] ?? null;
            $mapped[$idx]['is_cheapest'] = $price !== null
                && is_numeric($price)
                && abs((float) $price - $cheapest) < 0.01;
        }

        return $mapped;
    }

    /**
     * Resolve a branded fare option from stored offer data (anti-tamper: key only, not client labels/prices).
     *
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null
     */
    public static function findFareFamilyOptionByKey(array $offer, string $optionKey): ?array
    {
        $optionKey = trim($optionKey);
        if ($optionKey === '') {
            return null;
        }

        foreach (self::buildEnrichedFareFamilyOptionsForOffer($offer) as $option) {
            if ((string) ($option['option_key'] ?? '') === $optionKey) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Resolve a branded fare option for IATI/Sabre revalidation (option_key, id, and aliases).
     *
     * @param  array<string, mixed>  $offer
     * @return array{
     *     match_field: string,
     *     option: array<string, mixed>,
     *     brand: array<string, mixed>,
     *     index: int
     * }|null
     */
    public static function resolveSelectedFareFamilyOption(array $offer, string $selectedId): ?array
    {
        $selectedId = trim($selectedId);
        if ($selectedId === '') {
            return null;
        }

        $options = self::buildFareFamilyOptionsDisplay($offer);
        $brandedFares = is_array($offer['branded_fares'] ?? null) ? $offer['branded_fares'] : [];

        foreach ($options as $index => $option) {
            $brand = is_array($brandedFares[$index] ?? null) ? $brandedFares[$index] : [];
            if ($brand === []) {
                $brand = self::brandContextFromFareFamilyOptionRow($option);
            }
            $candidates = [
                'option_key' => trim((string) ($option['option_key'] ?? '')),
                'id' => trim((string) ($brand['id'] ?? '')),
                'option_id' => trim((string) ($brand['option_id'] ?? '')),
                'fare_option_id' => trim((string) ($brand['fare_option_id'] ?? '')),
                'selected_fare_option_id' => trim((string) ($brand['selected_fare_option_id'] ?? '')),
            ];
            foreach ($candidates as $field => $value) {
                if ($value !== '' && $value === $selectedId) {
                    $enriched = self::enrichFareFamilyOptionsWithDisplayPrices([$option], $offer)[0] ?? $option;

                    return [
                        'match_field' => $field,
                        'option' => $enriched,
                        'brand' => $brand,
                        'index' => (int) $index,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Apply branded fare selection to an offer snapshot for supplier revalidation.
     *
     * @param  array<string, mixed>  $offer
     * @return array{
     *     offer: array<string, mixed>,
     *     resolved: array{match_field: string, option: array<string, mixed>, brand: array<string, mixed>, index: int}|null,
     *     error_code: string|null,
     *     error_message: string|null
     * }
     */
    public static function applySelectedFareFamilyOptionToOffer(array $offer, ?string $selectedId): array
    {
        $selectedId = $selectedId !== null ? trim($selectedId) : '';
        if ($selectedId === '') {
            return [
                'offer' => $offer,
                'resolved' => null,
                'error_code' => null,
                'error_message' => null,
            ];
        }

        $resolved = self::resolveSelectedFareFamilyOption($offer, $selectedId);
        if ($resolved === null) {
            return [
                'offer' => $offer,
                'resolved' => null,
                'error_code' => 'selected_fare_option_not_found',
                'error_message' => 'Selected fare option could not be confirmed. Please choose the fare again.',
            ];
        }

        $option = $resolved['option'];

        if (PiaNdcFareFamilyPolicy::appliesToOffer($offer)) {
            return self::applyPiaNdcSelectedFareFamilyOptionToOffer($offer, $resolved, $selectedId);
        }

        if (! empty($option['is_grouped_offer_option'])) {
            $sourceOfferId = trim((string) ($option['source_offer_id'] ?? ''));
            $memberOffer = $sourceOfferId !== ''
                ? ItineraryFareConsolidator::resolveGroupedSourceOffer($offer, $sourceOfferId)
                : null;
            if ($memberOffer === null) {
                return [
                    'offer' => $offer,
                    'resolved' => $resolved,
                    'error_code' => 'selected_fare_option_not_found',
                    'error_message' => 'Selected fare option could not be confirmed. Please choose the fare again.',
                ];
            }
            $parentId = trim((string) ($offer['offer_id'] ?? $offer['id'] ?? ''));
            $group = is_array($offer['itinerary_fare_group'] ?? null) ? $offer['itinerary_fare_group'] : [];
            $offer = array_merge($memberOffer, [
                'offer_id' => $parentId,
                'id' => $parentId,
                'itinerary_fare_group' => $group,
            ]);
            $resolved['brand'] = self::brandContextFromFareFamilyOptionRow($option);
        }

        $brand = $resolved['brand'];
        $departureKey = trim((string) ($brand['departure_fare_key'] ?? $option['departure_fare_key'] ?? ''));
        if ($departureKey === '') {
            return [
                'offer' => $offer,
                'resolved' => $resolved,
                'error_code' => 'selected_fare_option_missing_fare_key',
                'error_message' => 'Selected fare option could not be confirmed. Please choose the fare again.',
            ];
        }

        $rawPayload = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $context = is_array($rawPayload['provider_context'] ?? null) ? $rawPayload['provider_context'] : [];
        $context['departure_fare_key'] = $departureKey;
        $returnKey = trim((string) ($brand['return_fare_key'] ?? ''));
        $context['return_fare_key'] = $returnKey !== '' ? $returnKey : null;
        $context['selected_branded_fare_id'] = trim((string) ($brand['id'] ?? $resolved['option']['option_key'] ?? ''));
        $context['selected_fare_option_id'] = $selectedId;
        $rawPayload['provider_context'] = $context;
        $offer['raw_payload'] = $rawPayload;

        if (trim((string) ($brand['name'] ?? '')) !== '') {
            $offer['fare_family'] = (string) $brand['name'];
        }
        $offer['selected_fare_family_option'] = $selectedId;

        $supplierTotal = self::selectedFareFamilySupplierTotal($resolved['option'], $brand);
        if ($supplierTotal !== null && $supplierTotal > 0) {
            $fareBreakdown = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
            $fareBreakdown['supplier_total'] = $supplierTotal;
            $offer['fare_breakdown'] = $fareBreakdown;
        }

        return [
            'offer' => $offer,
            'resolved' => $resolved,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array{match_field: string, option: array<string, mixed>, brand: array<string, mixed>, index: int}  $resolved
     * @return array{
     *     offer: array<string, mixed>,
     *     resolved: array<string, mixed>,
     *     error_code: string|null,
     *     error_message: string|null
     * }
     */
    protected static function applyPiaNdcSelectedFareFamilyOptionToOffer(array $offer, array $resolved, string $selectedId): array
    {
        $option = $resolved['option'];
        $ctx = PiaNdcFareFamilyPolicy::extractProviderContextFromOption($option, $offer);
        if (! PiaNdcFareFamilyPolicy::hasOrderCreateReadyContext($ctx)) {
            return [
                'offer' => $offer,
                'resolved' => $resolved,
                'error_code' => 'selected_fare_option_missing_provider_context',
                'error_message' => 'Selected fare option could not be confirmed. Please choose the fare again.',
            ];
        }

        $rawPayload = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $rawPayload['provider_context'] = $ctx;
        $offer['raw_payload'] = $rawPayload;
        $offer['provider_context'] = $ctx;

        $brandName = trim((string) ($option['name'] ?? $option['brand_name'] ?? PiaNdcFareFamilyPolicy::providerFareFamilyLabel($ctx, $offer)));
        if ($brandName !== '') {
            $offer['fare_family'] = $brandName;
        }

        $supplierTotal = self::selectedFareFamilySupplierTotal($option, self::brandContextFromFareFamilyOptionRow($option));
        if ($supplierTotal !== null && $supplierTotal > 0) {
            $fareBreakdown = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
            $fareBreakdown['supplier_total'] = $supplierTotal;
            $offer['fare_breakdown'] = $fareBreakdown;
            $offer['supplier_total'] = $supplierTotal;
        }

        $offer['selected_fare_family_option'] = $selectedId;

        return [
            'offer' => $offer,
            'resolved' => $resolved,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    protected static function brandContextFromFareFamilyOptionRow(array $option): array
    {
        return array_filter([
            'departure_fare_key' => self::nullableTrimmedString($option['departure_fare_key'] ?? null),
            'return_fare_key' => self::nullableTrimmedString($option['return_fare_key'] ?? null),
            'name' => self::nullableTrimmedString($option['name'] ?? $option['brand_name'] ?? null),
            'id' => self::nullableTrimmedString($option['option_key'] ?? null),
            'source_offer_id' => self::nullableTrimmedString($option['source_offer_id'] ?? null),
            'price_total' => isset($option['price_total']) && is_numeric($option['price_total'])
                ? (float) $option['price_total']
                : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');
    }

    /**
     * Supplier total for a branded fare option (price_total preferred over displayed_price).
     *
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $brand
     */
    public static function selectedFareFamilySupplierTotal(array $option, array $brand): ?float
    {
        if (isset($option['price_total']) && is_numeric($option['price_total']) && (float) $option['price_total'] > 0) {
            return (float) $option['price_total'];
        }
        if (isset($brand['price_total']) && is_numeric($brand['price_total']) && (float) $brand['price_total'] > 0) {
            return (float) $brand['price_total'];
        }
        if (isset($brand['price']) && is_numeric($brand['price']) && (float) $brand['price'] > 0) {
            return (float) $brand['price'];
        }
        if (isset($option['displayed_price']) && is_numeric($option['displayed_price']) && (int) $option['displayed_price'] > 0) {
            return (float) (int) $option['displayed_price'];
        }

        return null;
    }

    /**
     * Safe short fare_option_key for logs (no payload / PII).
     */
    public static function safeFareOptionKeyForLog(string $fareOptionKey): ?string
    {
        $key = trim($fareOptionKey);
        if ($key === '' || strlen($key) > 120) {
            return null;
        }

        if (! preg_match('/^[a-z0-9\-]+$/i', $key)) {
            return null;
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<string>
     */
    public static function fareFamilyOptionKeysSample(array $offer, int $limit = 8): array
    {
        $keys = [];
        foreach (self::buildEnrichedFareFamilyOptionsForOffer($offer) as $option) {
            $key = trim((string) ($option['option_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $keys[] = $key;
            if (count($keys) >= $limit) {
                break;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>
     */
    protected static function buildEnrichedFareFamilyOptionsForOffer(array $offer): array
    {
        if (PiaNdcFareFamilyPolicy::appliesToOffer($offer)) {
            $mapped = PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer);
        } else {
            $mapped = self::buildFareFamilyOptionsDisplay($offer);
        }

        return self::applyFareFamilySelectionGate(
            self::enrichFareFamilyOptionsWithDisplayPrices($mapped, $offer),
            self::brandedFaresSelectionActiveForOffer($offer),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected static function mapFareFamilyOptionRow(array $row, int $index): ?array
    {
        $name = trim((string) ($row['name'] ?? $row['brand_name'] ?? $row['fare_family'] ?? ''));
        if ($name === '') {
            return null;
        }

        $priceTotal = null;
        if (isset($row['price_total']) && is_numeric($row['price_total'])) {
            $priceTotal = (float) $row['price_total'];
        } elseif (isset($row['total']) && is_numeric($row['total'])) {
            $priceTotal = (float) $row['total'];
        }

        $currency = trim((string) ($row['currency'] ?? ''));
        $cabin = self::nullableTrimmedString($row['cabin'] ?? $row['cabin_class'] ?? $row['class'] ?? null);
        $bookingClass = self::extractBookingClassForDisplay($row);
        $fareBasis = self::extractFareBasisForDisplay($row);
        $refundableDisplay = self::nullableTrimmedString($row['refundable_display'] ?? null);
        if ($refundableDisplay === null && array_key_exists('refundable', $row)) {
            $refundableDisplay = (bool) $row['refundable'] ? 'Refundable' : 'Non-refundable';
        }
        if ($refundableDisplay === null) {
            $refundableDisplay = self::nullableTrimmedString($row['refund_rule'] ?? $row['refund'] ?? null);
        }

        $baggageLines = array_values(array_filter([
            BaggageDisplayNormalizer::normalizeLabel(self::nullableTrimmedString($row['baggage_summary'] ?? $row['baggage'] ?? null)),
            BaggageDisplayNormalizer::normalizeLabel(self::nullableTrimmedString($row['carry_on_summary'] ?? $row['carry_on'] ?? null)),
            BaggageDisplayNormalizer::normalizeLabel(self::nullableTrimmedString($row['check_in_summary'] ?? $row['checked_baggage'] ?? null)),
            self::nullableTrimmedString($row['meal_included'] ?? $row['meal'] ?? null),
            self::nullableTrimmedString($row['seat_selection_rule'] ?? $row['seat_selection'] ?? null),
            self::nullableTrimmedString($row['modification_rule'] ?? $row['modification'] ?? null),
            self::nullableTrimmedString($row['cancellation_rule'] ?? $row['cancellation'] ?? null),
        ], fn (?string $line): bool => $line !== null));

        $carryOn = BaggageDisplayNormalizer::normalizeLabel(self::nullableTrimmedString($row['carry_on_summary'] ?? $row['carry_on'] ?? null));
        $checkIn = BaggageDisplayNormalizer::normalizeLabel(self::nullableTrimmedString($row['check_in_summary'] ?? $row['checked_baggage'] ?? null));
        $baggageSummary = BaggageDisplayNormalizer::normalizeLabel(self::nullableTrimmedString($row['baggage_summary'] ?? $row['baggage'] ?? null));
        if ($baggageSummary === null) {
            $baggageSummary = BaggageDisplayNormalizer::formatAllowance($checkIn, $carryOn)['summary'];
        }

        $explicitOptionKey = self::nullableTrimmedString($row['option_key'] ?? null);

        $mapped = [
            'option_key' => $explicitOptionKey ?? self::buildFareFamilyOptionKey($row, $index, $name, $priceTotal),
            'name' => $name,
            'price_total' => $priceTotal,
            'currency' => $currency !== '' ? $currency : null,
            'baggage_summary' => $baggageSummary,
            'baggage_lines' => $baggageLines,
            'carry_on_summary' => $carryOn,
            'check_in_summary' => $checkIn,
            'checked_baggage_source' => self::nullableTrimmedString($row['checked_baggage_source'] ?? null),
            'cabin_baggage_source' => self::nullableTrimmedString($row['cabin_baggage_source'] ?? null),
            'meal_included' => self::nullableTrimmedString($row['meal_included'] ?? $row['meal'] ?? null),
            'seat_selection_rule' => self::nullableTrimmedString($row['seat_selection_rule'] ?? $row['seat_selection'] ?? null),
            'modification_rule' => self::nullableTrimmedString($row['modification_rule'] ?? $row['modification'] ?? null),
            'cancellation_rule' => self::nullableTrimmedString($row['cancellation_rule'] ?? $row['cancellation'] ?? null),
            'refund_rule' => self::nullableTrimmedString($row['refund_rule'] ?? $row['refund'] ?? null),
            'refundable_display' => $refundableDisplay,
            'cabin' => $cabin,
            'booking_class' => $bookingClass,
            'fare_basis' => $fareBasis,
            'brand_code' => self::nullableTrimmedString($row['brand_code'] ?? $row['supplier_brand_code'] ?? null),
            'is_cheapest' => (bool) ($row['is_cheapest'] ?? false),
            'supplier_brand_code' => self::nullableTrimmedString($row['supplier_brand_code'] ?? $row['brand_code'] ?? null),
            'source_offer_id' => self::nullableTrimmedString($row['source_offer_id'] ?? null),
            'is_grouped_offer_option' => (bool) ($row['is_grouped_offer_option'] ?? false),
            'departure_fare_key' => self::nullableTrimmedString($row['departure_fare_key'] ?? null),
            'return_fare_key' => self::nullableTrimmedString($row['return_fare_key'] ?? null),
            'selectable' => (bool) ($row['selectable'] ?? false),
            'provider_context' => is_array($row['provider_context'] ?? null) ? $row['provider_context'] : null,
            'pia_ndc_provider_backed' => (bool) ($row['pia_ndc_provider_backed'] ?? false),
        ];

        return array_merge(
            OfferBaggageResolver::enrichFareOptionRow($mapped, []),
            self::fareFamilyOptionReadinessDisplayFields($row),
        );
    }

    /**
     * B2A: Safe readiness fields for display/API (no raw supplier ref tokens).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected static function fareFamilyOptionReadinessDisplayFields(array $row): array
    {
        $out = [];
        if (isset($row['pricing_information_index']) && is_numeric($row['pricing_information_index'])) {
            $out['pricing_information_index'] = (int) $row['pricing_information_index'];
        }
        foreach ([
            'has_revalidation_linkage',
            'has_segment_booking_linkage',
            'ready_for_revalidation',
            'ready_for_booking_payload',
        ] as $flag) {
            if (array_key_exists($flag, $row)) {
                $out[$flag] = (bool) $row[$flag];
            }
        }
        if (is_array($row['readiness_reasons'] ?? null) && $row['readiness_reasons'] !== []) {
            $out['readiness_reasons'] = array_values(array_slice(array_map(
                static fn ($v): string => substr(trim((string) $v), 0, 64),
                $row['readiness_reasons']
            ), 0, 12));
        }
        if (is_array($row['linkage_summary'] ?? null) && $row['linkage_summary'] !== []) {
            $out['linkage_summary'] = self::sanitizeFareFamilyLinkageSummaryForDisplay($row['linkage_summary']);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, bool|int>
     */
    protected static function sanitizeFareFamilyLinkageSummaryForDisplay(array $summary): array
    {
        $safe = [];
        foreach ($summary as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if (is_bool($v)) {
                $safe[$k] = $v;
            } elseif (is_int($v)) {
                $safe[$k] = $v;
            } elseif (is_numeric($v) && ! str_contains(strtolower($k), 'ref') && ! str_ends_with(strtolower($k), '_id')) {
                $safe[$k] = (int) $v;
            }
        }

        return $safe;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function buildFareFamilyOptionKey(array $row, int $index, string $name, ?float $priceTotal): string
    {
        $supplierOfferId = self::nullableTrimmedString($row['duffel_offer_id'] ?? $row['supplier_offer_id'] ?? null);
        $brand = self::nullableTrimmedString($row['supplier_brand_code'] ?? $row['brand_code'] ?? null);
        if ($brand !== null) {
            $key = self::normalizeFareFamilyOptionKeySegment($brand);
            if (isset($row['pricing_information_index']) && is_numeric($row['pricing_information_index'])) {
                $key .= '-pi'.(int) $row['pricing_information_index'];
            } elseif (isset($row['supplier_offer_id']) && trim((string) $row['supplier_offer_id']) !== '') {
                $key .= '-'.self::normalizeFareFamilyOptionKeySegment((string) $row['supplier_offer_id']);
            }

            return substr($key, 0, 120);
        }

        if ($supplierOfferId !== null) {
            return substr(self::normalizeFareFamilyOptionKeySegment($supplierOfferId), 0, 120);
        }

        $base = self::normalizeFareFamilyOptionKeySegment($name);
        if ($priceTotal !== null && $priceTotal > 0) {
            return substr($base.'-'.(int) round($priceTotal).'-'.$index, 0, 120);
        }

        return substr($base.'-'.$index, 0, 120);
    }

    protected static function normalizeFareFamilyOptionKeySegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');

        return $value !== '' ? $value : 'fare';
    }

    protected static function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s !== '' ? $s : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function extractBookingClassForDisplay(array $row): ?string
    {
        $direct = self::nullableTrimmedString($row['booking_class'] ?? $row['rbd'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        $bySegment = $row['booking_classes_by_segment'] ?? null;
        if (! is_array($bySegment) || $bySegment === []) {
            return null;
        }

        $classes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $v): string => strtoupper(trim((string) $v)),
            $bySegment
        ))));

        return $classes !== [] ? implode('/', $classes) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function extractFareBasisForDisplay(array $row): ?string
    {
        $direct = self::nullableTrimmedString($row['fare_basis'] ?? $row['fare_basis_code'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        $codes = $row['fare_basis_codes'] ?? $row['fare_basis_codes_by_segment'] ?? null;
        if (! is_array($codes) || $codes === []) {
            return null;
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $v): string => strtoupper(trim((string) $v)),
            $codes
        ))));

        if ($normalized === []) {
            return null;
        }

        return count($normalized) === 1 ? $normalized[0] : implode('/', array_slice($normalized, 0, 3));
    }
}
