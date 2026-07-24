<?php

namespace App\Support\FlightSearch;

use App\Support\Suppliers\SupplierSourcePresenter;

/**
 * Customer-safe fallback flight detail sections when branded fare options are unavailable.
 */
class FlightOfferFallbackDetailsPresenter
{
    /**
     * @param  array<string, mixed>  $offer  Cached search offer (may include raw_payload).
     * @param  array<string, mixed>  $presentation  Output from {@see FlightOfferDisplayPresenter::buildPresentation()}.
     * @return array<string, mixed>
     */
    public static function buildForOffer(array $offer, array $presentation = []): array
    {
        $overview = self::buildOverviewSection($offer, $presentation);
        $baggage = self::buildBaggageSection($offer, $presentation);
        $fareBreakdown = self::buildFareBreakdownSection($offer);
        $fareRules = self::buildFareRulesSection($offer, $presentation);
        $supplier = self::buildSupplierSection($offer);

        $sectionsPresent = [
            'overview' => self::sectionHasContent($overview),
            'baggage' => self::sectionHasContent($baggage),
            'fare_breakdown' => self::sectionHasContent($fareBreakdown),
            'fare_rules' => self::sectionHasContent($fareRules),
            'supplier' => self::sectionHasContent($supplier),
        ];

        $hasFallback = in_array(true, $sectionsPresent, true);

        return [
            'has_fallback_details' => $hasFallback,
            'fallback_detail_sections_present' => $sectionsPresent,
            'fallback_details' => $hasFallback ? [
                'overview' => $overview,
                'baggage' => $baggage,
                'fare_breakdown' => $fareBreakdown,
                'fare_rules' => $fareRules,
                'supplier' => $supplier,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>
     */
    protected static function buildOverviewSection(array $offer, array $presentation): array
    {
        $segments = is_array($presentation['segments_display'] ?? null)
            ? $presentation['segments_display']
            : (is_array($offer['segments'] ?? null) ? $offer['segments'] : []);

        $segmentRows = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $segmentRows[] = array_filter([
                'airline_code' => self::nullableString($segment['airline_code'] ?? $segment['carrier_code'] ?? null),
                'airline_name' => self::nullableString($segment['airline_name'] ?? null),
                'flight_number' => self::nullableString($segment['flight_number'] ?? null),
                'operating_airline_code' => self::nullableString($segment['operating_airline_code'] ?? null),
                'operating_airline_name' => self::nullableString($segment['operating_airline_name'] ?? null),
                'origin' => self::nullableString($segment['origin'] ?? null),
                'destination' => self::nullableString($segment['destination'] ?? null),
                'origin_city' => self::nullableString($segment['origin_city'] ?? null),
                'destination_city' => self::nullableString($segment['destination_city'] ?? null),
                'departure_time' => self::nullableString($segment['departure_time_display'] ?? $segment['departure_at'] ?? null),
                'departure_date' => self::nullableString($segment['departure_date_display'] ?? null),
                'arrival_time' => self::nullableString($segment['arrival_time_display'] ?? $segment['arrival_at'] ?? null),
                'arrival_date' => self::nullableString($segment['arrival_date_display'] ?? null),
                'terminal_departure' => self::nullableString($segment['terminal_departure'] ?? $segment['departure_terminal'] ?? null),
                'terminal_arrival' => self::nullableString($segment['terminal_arrival'] ?? $segment['arrival_terminal'] ?? null),
                'duration' => self::nullableString($segment['duration_display'] ?? null),
                'aircraft' => self::nullableString($segment['aircraft'] ?? $segment['aircraft_type'] ?? null),
                'booking_class' => self::nullableString($segment['booking_class'] ?? null),
                'cabin' => self::nullableString($segment['cabin'] ?? null),
            ], fn ($v) => $v !== null && $v !== '');
        }

        $layovers = is_array($presentation['layovers_display'] ?? null) ? $presentation['layovers_display'] : [];

        return array_filter([
            'airline_code' => self::nullableString($offer['airline_code'] ?? $offer['primary_display_carrier'] ?? null),
            'airline_name' => self::nullableString($offer['airline_name'] ?? $offer['primary_display_carrier_name'] ?? null),
            'flight_number' => self::nullableString($offer['flight_number'] ?? null),
            'operating_airline_code' => self::nullableString($offer['operating_airline_code'] ?? null),
            'operating_airline_name' => self::nullableString($offer['operating_airline_name'] ?? null),
            'route' => self::nullableString($offer['route'] ?? null),
            'origin' => self::nullableString($presentation['departure_airport_code'] ?? $offer['origin'] ?? null),
            'destination' => self::nullableString($presentation['arrival_airport_code'] ?? $offer['destination'] ?? null),
            'origin_city' => self::nullableString($presentation['departure_city'] ?? null),
            'destination_city' => self::nullableString($presentation['arrival_city'] ?? null),
            'departure_time' => self::nullableString($presentation['departure_time_display'] ?? $offer['departure_time'] ?? null),
            'departure_date' => self::nullableString($presentation['departure_date_display'] ?? null),
            'arrival_time' => self::nullableString($presentation['arrival_time_display'] ?? $offer['arrival_time'] ?? null),
            'arrival_date' => self::nullableString($presentation['arrival_date_display'] ?? null),
            'duration' => self::nullableString($presentation['itinerary_duration_display'] ?? $offer['duration'] ?? null),
            'stops' => self::nullableString($presentation['stops_display'] ?? null),
            'stops_count' => isset($offer['stops']) ? (int) $offer['stops'] : null,
            'layovers' => $layovers !== [] ? $layovers : null,
            'segments' => $segmentRows !== [] ? $segmentRows : null,
            'journeys_display' => is_array($presentation['journeys_display'] ?? null) && $presentation['journeys_display'] !== []
                ? $presentation['journeys_display']
                : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>
     */
    protected static function buildBaggageSection(array $offer, array $presentation): array
    {
        $checked = BaggageDisplayNormalizer::normalizeLabel(self::nullableString($presentation['baggage_checked_display'] ?? data_get($offer, 'baggage.checked')));
        $cabin = BaggageDisplayNormalizer::normalizeLabel(self::nullableString($presentation['baggage_cabin_display'] ?? data_get($offer, 'baggage.cabin')));
        $summary = BaggageDisplayNormalizer::normalizeLabel(self::nullableString($presentation['baggage_summary_display'] ?? data_get($offer, 'baggage.summary') ?? $offer['baggage'] ?? null));
        $lines = is_array($offer['baggage_lines'] ?? null)
            ? array_values(array_filter(array_map(
                static fn (mixed $line): ?string => BaggageDisplayNormalizer::normalizeLabel(self::nullableString($line)),
                $offer['baggage_lines'],
            )))
            : [];
        $passengerBaggage = is_array($offer['passenger_baggage'] ?? null) ? $offer['passenger_baggage'] : [];
        $segmentBaggage = is_array($offer['segment_baggage'] ?? null) ? $offer['segment_baggage'] : [];

        if ($passengerBaggage !== []) {
            $passengerBaggage = array_map(static function (mixed $row): array {
                if (! is_array($row)) {
                    return [];
                }

                return array_filter([
                    'passenger_type' => self::nullableString($row['passenger_type'] ?? null),
                    'checked' => BaggageDisplayNormalizer::normalizeLabel(self::nullableString($row['checked'] ?? null)),
                    'cabin' => BaggageDisplayNormalizer::normalizeLabel(self::nullableString($row['cabin'] ?? null)),
                ], fn ($v) => $v !== null && $v !== '');
            }, $passengerBaggage);
        }

        if ($segmentBaggage !== []) {
            $segmentBaggage = array_map(static function (mixed $row): array {
                if (! is_array($row)) {
                    return [];
                }

                return array_filter([
                    'segment_index' => $row['segment_index'] ?? null,
                    'route' => self::nullableString($row['route'] ?? null),
                    'checked' => BaggageDisplayNormalizer::normalizeLabel(self::nullableString($row['checked'] ?? null)),
                    'cabin' => BaggageDisplayNormalizer::normalizeLabel(self::nullableString($row['cabin'] ?? null)),
                ], fn ($v) => $v !== null && $v !== '');
            }, $segmentBaggage);
        }

        $unavailable = $checked === null && $cabin === null && $summary === null && $lines === [] && $passengerBaggage === [] && $segmentBaggage === [];

        return array_filter([
            'checked' => $checked,
            'cabin' => $cabin,
            'summary' => $summary,
            'lines' => $lines !== [] ? $lines : null,
            'passenger_baggage' => $passengerBaggage !== [] ? $passengerBaggage : null,
            'segment_baggage' => $segmentBaggage !== [] ? $segmentBaggage : null,
            'unavailable_message' => $unavailable ? BaggageDisplayNormalizer::NOT_PROVIDED : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected static function buildFareBreakdownSection(array $offer): array
    {
        $currency = strtoupper(trim((string) ($offer['supplier_currency'] ?? $offer['currency'] ?? data_get($offer, 'fare_breakdown.currency', 'PKR'))));
        $base = (float) ($offer['base_fare'] ?? data_get($offer, 'fare_breakdown.base_fare', 0));
        $taxes = (float) ($offer['taxes'] ?? data_get($offer, 'fare_breakdown.taxes', 0));
        $supplierTotal = (float) ($offer['supplier_total'] ?? data_get($offer, 'fare_breakdown.supplier_total', $base + $taxes));
        $markup = (float) ($offer['markup'] ?? 0);
        $serviceFee = (float) ($offer['service_fee'] ?? 0);
        $displayedPrice = isset($offer['displayed_price']) ? (int) round((float) $offer['displayed_price']) : null;
        $grandTotal = $displayedPrice !== null && $displayedPrice > 0
            ? (float) $displayedPrice
            : (float) ($offer['final_customer_price'] ?? 0);
        if ($grandTotal <= 0 && $supplierTotal > 0) {
            $grandTotal = $supplierTotal + $markup + $serviceFee;
        }
        $passengerPricing = is_array($offer['passenger_pricing'] ?? null)
            ? $offer['passenger_pricing']
            : (is_array(data_get($offer, 'fare_breakdown.passenger_pricing')) ? data_get($offer, 'fare_breakdown.passenger_pricing') : []);

        return array_filter([
            'base_fare' => $base > 0 ? $base : null,
            'taxes' => $taxes > 0 ? $taxes : null,
            'supplier_total' => $supplierTotal > 0 ? $supplierTotal : null,
            'markup' => $markup > 0 ? $markup : null,
            'service_fee' => $serviceFee > 0 ? $serviceFee : null,
            'grand_total' => $grandTotal > 0 ? $grandTotal : null,
            'displayed_price' => $displayedPrice,
            'displayed_currency' => $displayedPrice !== null ? 'PKR' : null,
            'currency' => $currency !== '' ? $currency : null,
            'passenger_pricing' => $passengerPricing !== [] ? $passengerPricing : null,
            'price_note' => self::nullableString($offer['price_note'] ?? null),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>
     */
    protected static function buildFareRulesSection(array $offer, array $presentation): array
    {
        $refundable = (bool) ($offer['refundable'] ?? false);
        $refundRule = self::nullableString($offer['refund_rule'] ?? null);
        $changeRule = self::nullableString($offer['change_rule'] ?? null);
        $fareBasis = self::nullableString($offer['fare_basis'] ?? null);
        $bookingClass = self::nullableString($offer['booking_class'] ?? null);
        $cabin = self::nullableString($offer['cabin'] ?? $presentation['fare_summary_display']['cabin'] ?? null);
        $fareFamily = self::nullableString($offer['fare_family'] ?? $presentation['fare_summary_display']['fare_family'] ?? null);
        $ruleLines = is_array($offer['fare_rule_lines'] ?? null) ? array_values($offer['fare_rule_lines']) : [];

        return array_filter([
            'refundable' => $refundable,
            'refund_status' => $refundRule ?: ($refundable ? 'Refundable' : 'Non-refundable'),
            'change_allowed' => $changeRule !== null ? ! str_contains(strtolower($changeRule), 'not permitted') : null,
            'change_rule' => $changeRule,
            'refund_rule' => $refundRule,
            'penalty' => self::nullableString($offer['penalty'] ?? null),
            'fare_basis' => $fareBasis,
            'booking_class' => $bookingClass,
            'cabin' => $cabin,
            'fare_family' => $fareFamily,
            'rule_lines' => $ruleLines !== [] ? $ruleLines : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected static function buildSupplierSection(array $offer): array
    {
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        $freshness = is_array($offer['offer_freshness'] ?? null) ? $offer['offer_freshness'] : [];

        return array_filter([
            'provider' => $provider !== '' ? $provider : null,
            'provider_label' => self::nullableString($offer['supplier_source_label'] ?? SupplierSourcePresenter::label($provider)),
            'freshness_status' => self::nullableString($freshness['offer_freshness_status'] ?? null),
            'last_checked_display' => self::nullableString($freshness['last_checked_display'] ?? $freshness['search_age_display'] ?? null),
            'revalidation_required' => array_key_exists('revalidation_required', $freshness)
                ? (bool) $freshness['revalidation_required']
                : ($provider === 'iati' ? true : null),
            'revalidation_note' => self::nullableString(
                $freshness['revalidation_note']
                ?? ($provider === 'iati' ? 'Airline price validation is required before booking.' : null)
            ),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $section
     */
    protected static function sectionHasContent(array $section): bool
    {
        return $section !== [];
    }

    protected static function nullableString(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
