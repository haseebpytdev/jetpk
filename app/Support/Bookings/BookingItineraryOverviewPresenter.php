<?php

namespace App\Support\Bookings;

use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Carbon\Carbon;

/**
 * Builds dashboard-safe itinerary labels from booking meta snapshots (Sabre/Duffel/public checkout).
 *
 * B83: Labels itinerary source (search/checkout snapshot vs optional `meta.pnr_itinerary_snapshot`),
 * shows layovers and unambiguous schedule strings for admin/staff.
 */
final class BookingItineraryOverviewPresenter
{
    public const ITINERARY_SOURCE_SEARCH_SNAPSHOT = 'search_snapshot';

    public const ITINERARY_SOURCE_PNR_SYNCED = 'pnr_synced';

    /**
     * Optional future key: sanitized host itinerary with the same segment shape as `flight_offer_snapshot`.
     *
     * @param  array<string, mixed>|null  $meta
     * @return array{
     *     has_data: bool,
     *     stops_label: string,
     *     segment_lines: list<string>,
     *     journey_od: string,
     *     trip_type_label: string,
     *     journey_group_lines: list<string>,
     *     itinerary_source: string,
     *     itinerary_source_label: string,
     *     show_snapshot_itinerary_warning: bool,
     *     show_fare_snapshot_note: bool,
     * }|null
     */
    public static function fromBookingMeta(?array $meta, bool $hasPnrOrReference = false): ?array
    {
        if ($meta === null || $meta === []) {
            return null;
        }

        $pnrSnap = $meta['pnr_itinerary_snapshot'] ?? null;
        $usePnrItinerary = is_array($pnrSnap) && is_array($pnrSnap['segments'] ?? null) && $pnrSnap['segments'] !== [];

        $offer = null;
        $itinerarySource = self::ITINERARY_SOURCE_SEARCH_SNAPSHOT;

        if ($usePnrItinerary) {
            $offer = $pnrSnap;
            $itinerarySource = self::ITINERARY_SOURCE_PNR_SYNCED;
        } else {
            foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
                $snap = $meta[$key] ?? null;
                if (is_array($snap) && $snap !== []) {
                    $offer = $snap;
                    break;
                }
            }
        }

        if ($offer === null) {
            return null;
        }

        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $tripType = (string) ($criteria['trip_type'] ?? 'one_way');
        $tripTypeLabel = FlightOfferDisplayPresenter::formatCriteriaTripTypeLabel($tripType);
        $presentation = FlightOfferDisplayPresenter::buildPresentation($offer, $criteria, []);

        $journeysDisplay = is_array($offer['journeys_display'] ?? null) && $offer['journeys_display'] !== []
            ? $offer['journeys_display']
            : (is_array($presentation['journeys_display'] ?? null) ? $presentation['journeys_display'] : []);

        $offerSegments = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];

        $lines = [];
        $journeyGroupLines = [];
        if ($journeysDisplay !== []) {
            foreach ($journeysDisplay as $journey) {
                if (! is_array($journey)) {
                    continue;
                }
                $groupLabel = trim((string) ($journey['label'] ?? ''));
                if ($groupLabel !== '') {
                    $journeyGroupLines[] = $groupLabel;
                    $lines[] = $groupLabel;
                }
                $jSegs = is_array($journey['segments_display'] ?? null) ? $journey['segments_display'] : [];
                $leg = 1;
                foreach ($jSegs as $seg) {
                    if (! is_array($seg)) {
                        continue;
                    }
                    $line = self::formatSegmentOverviewLine($seg, null);
                    if ($line !== '') {
                        $lines[] = '   '.$leg.'. '.$line;
                        $leg++;
                    }
                    $layover = trim((string) ($seg['layover_after_display'] ?? ''));
                    if ($layover !== '') {
                        $lines[] = '      — '.(string) __('Transfer: :duration', ['duration' => $layover]);
                    }
                }
            }
        } else {
            $segments = is_array($presentation['segments_display'] ?? null)
                ? $presentation['segments_display']
                : [];
            $leg = 1;
            $segIndex = 0;
            foreach ($segments as $seg) {
                if (! is_array($seg)) {
                    $segIndex++;

                    continue;
                }
                $depIso = trim((string) (is_array($offerSegments[$segIndex] ?? null) ? ($offerSegments[$segIndex]['departure_at'] ?? '') : ''));
                $arrIso = trim((string) (is_array($offerSegments[$segIndex] ?? null) ? ($offerSegments[$segIndex]['arrival_at'] ?? '') : ''));
                $line = self::formatSegmentOverviewLine($seg, self::formatSegmentScheduleWindow($depIso, $arrIso));
                if ($line !== '') {
                    $lines[] = $leg.'. '.$line;
                    $leg++;
                }

                $layover = trim((string) ($seg['layover_after_display'] ?? ''));
                if ($layover !== '' && $segIndex < count($segments) - 1) {
                    $lines[] = '   — '.(string) __('Transfer: :duration', ['duration' => $layover]);
                }

                $segIndex++;
            }
        }

        $journeyOd = FlightOfferDisplayPresenter::formatCriteriaRouteLabel($criteria);
        if ($journeyOd === '') {
            $dep = strtoupper(trim((string) ($presentation['departure_airport_code'] ?? '')));
            $arr = strtoupper(trim((string) ($presentation['arrival_airport_code'] ?? '')));
            $journeyOd = ($dep !== '' && $arr !== '') ? "{$dep} → {$arr}" : '';
        }

        if ($lines === [] && $journeyOd !== '') {
            $lines[] = $journeyOd;
        }

        $stopsLabel = trim((string) ($presentation['stops_display'] ?? ''));
        if ($stopsLabel === '') {
            $stopsLabel = '—';
        }

        if ($lines === [] && $stopsLabel === '—' && $journeyOd === '') {
            return null;
        }

        $isSearchSnapshot = $itinerarySource === self::ITINERARY_SOURCE_SEARCH_SNAPSHOT;

        $sourceLabel = match (true) {
            $itinerarySource === self::ITINERARY_SOURCE_PNR_SYNCED => 'PNR/airline itinerary',
            $hasPnrOrReference && $isSearchSnapshot => 'Search/checkout snapshot — final airline itinerary not yet synced',
            default => 'Search/checkout snapshot',
        };

        $showSnapshotWarning = $hasPnrOrReference && $isSearchSnapshot;

        return [
            'has_data' => true,
            'stops_label' => $stopsLabel,
            'segment_lines' => $lines,
            'journey_od' => $journeyOd,
            'trip_type_label' => $tripTypeLabel,
            'journey_group_lines' => $journeyGroupLines,
            'itinerary_source' => $itinerarySource,
            'itinerary_source_label' => $sourceLabel,
            'show_snapshot_itinerary_warning' => $showSnapshotWarning,
            'show_fare_snapshot_note' => $showSnapshotWarning,
        ];
    }

    /**
     * @param  array<string, mixed>  $seg
     */
    protected static function formatSegmentOverviewLine(array $seg, ?string $schedule): string
    {
        $o = strtoupper(trim((string) ($seg['origin'] ?? '')));
        $d = strtoupper(trim((string) ($seg['destination'] ?? '')));
        $ac = strtoupper(trim((string) ($seg['airline_code'] ?? '')));
        $fn = trim((string) ($seg['flight_number'] ?? ''));
        $flight = $fn !== ''
            ? ($ac !== '' && ! str_starts_with(strtoupper($fn), $ac) ? $ac.$fn : $fn)
            : $ac;

        $parts = [];
        if ($o !== '' && $d !== '') {
            $parts[] = "{$o} → {$d}";
        }
        if ($flight !== '') {
            $parts[] = $flight;
        }
        if ($schedule !== null && $schedule !== '') {
            $parts[] = $schedule;
        }

        return implode(' · ', $parts);
    }

    protected static function formatSegmentScheduleWindow(string $depIso, string $arrIso): string
    {
        if ($depIso === '' && $arrIso === '') {
            return '';
        }
        try {
            $d = $depIso !== '' ? Carbon::parse($depIso) : null;
            $a = $arrIso !== '' ? Carbon::parse($arrIso) : null;
        } catch (\Throwable) {
            return '';
        }
        $left = $d !== null ? $d->format('j M Y, g:i A') : '';
        $right = $a !== null ? $a->format('j M Y, g:i A') : '';
        if ($left === '' && $right === '') {
            return '';
        }
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return "{$left} → {$right}";
    }

    /**
     * Admin Payments tab: when `booking_fare_breakdowns.base_fare` / `taxes` look like a fragment
     * of `meta.supplier_total` (e.g. per-passenger row stored as base), hide them as authoritative totals.
     */
    public static function adminStoredFareLineItemsLookUnreliable(float $baseFare, float $taxes, float $supplierTotal, float $customerTotal): bool
    {
        if ($supplierTotal >= 1000 && ($baseFare + $taxes) < ($supplierTotal * 0.25)) {
            return true;
        }

        if ($baseFare > 0 && $baseFare < 500 && $customerTotal > 10_000) {
            return true;
        }

        return false;
    }
}
