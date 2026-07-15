<?php

namespace App\Services\Suppliers\Duffel;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Security\SensitiveDataRedactor;

/**
 * One normalized offer per Duffel API offer. Sibling fare brands for the same itinerary are not nested here;
 * {@see FlightSearchService::attachDuffelBrandedFareOptionsToOffers()} may attach
 * `branded_fares` on the cheapest display row after pricing (B1 display only; selectable stays false until B2).
 */
class DuffelOfferNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<NormalizedFlightOfferData>
     */
    public function normalizeMany(array $payload, SupplierConnection $connection): array
    {
        $offers = [];
        foreach ($this->extractOfferRows($payload) as $row) {
            $mapped = $this->normalizeSingle($row, $connection);
            if ($mapped !== null) {
                $offers[] = $mapped;
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalizeOne(array $payload, SupplierConnection $connection): ?NormalizedFlightOfferData
    {
        $rows = $this->extractOfferRows($payload);

        return $rows === [] ? null : $this->normalizeSingle($rows[0], $connection);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function normalizeSingle(array $offer, SupplierConnection $connection): ?NormalizedFlightOfferData
    {
        $segments = $this->extractSegments($offer);
        if ($segments === []) {
            return null;
        }

        $first = $segments[0];
        $last = $segments[count($segments) - 1];
        $stops = max(0, count($segments) - 1);
        $base = $this->money($offer['base_amount'] ?? 0);
        $total = $this->money($offer['total_amount'] ?? 0);
        $tax = $this->money($offer['tax_amount'] ?? max(0, $total - $base));
        $fee = $this->money($offer['fee_amount'] ?? 0);
        $currency = strtoupper((string) ($offer['total_currency'] ?? $offer['currency'] ?? 'USD'));
        $ownerCode = strtoupper((string) ($offer['owner']['iata_code'] ?? $first['airline_code'] ?? 'XX'));
        $ownerName = trim((string) ($offer['owner']['name'] ?? $first['airline_name'] ?? $ownerCode));
        $offerId = trim((string) ($offer['id'] ?? ''));
        $durationMinutes = $this->durationMinutes((string) ($first['departure_at'] ?? ''), (string) ($last['arrival_at'] ?? ''));
        $baggage = $this->aggregateBaggageFromSegments($segments);
        $passengerPricing = $this->extractPassengerPricing($offer, $currency);
        $passengerCounts = $this->buildPassengerCounts($offer);

        $carrierDisplay = NormalizedFlightOfferData::deriveMultiSegmentCarrierDisplay(
            $segments,
            null,
            $ownerCode,
        );

        return new NormalizedFlightOfferData(
            offer_id: $offerId !== '' ? $offerId : sha1((string) json_encode($offer)),
            supplier_provider: SupplierProvider::Duffel->value,
            supplier_connection_id: $connection->id,
            airline_code: $ownerCode !== '' ? $ownerCode : 'XX',
            airline_name: $ownerName !== '' ? $ownerName : 'Duffel Partner',
            flight_number: isset($first['flight_number']) ? (string) $first['flight_number'] : null,
            origin: (string) ($first['origin'] ?? ''),
            destination: (string) ($last['destination'] ?? ''),
            departure_at: (string) ($first['departure_at'] ?? ''),
            arrival_at: (string) ($last['arrival_at'] ?? ''),
            duration_minutes: $durationMinutes,
            stops: $stops,
            cabin: strtolower((string) ($first['cabin'] ?? 'economy')),
            fare_family: isset($offer['fare_brand_name']) ? (string) $offer['fare_brand_name'] : null,
            refundable: (bool) ($offer['conditions']['refund_before_departure']['allowed'] ?? false),
            seats_left: isset($offer['available_services']) && is_array($offer['available_services']) ? count($offer['available_services']) : null,
            segments: $segments,
            baggage: new BaggageAllowanceData(
                checked: $baggage['checked'],
                cabin: $baggage['cabin'],
                summary: $baggage['summary']
            ),
            fare_breakdown: new FareBreakdownData(
                base_fare: $base,
                taxes: $tax,
                supplier_fees: $fee,
                supplier_total: $total,
                currency: $currency,
                passenger_pricing: $passengerPricing,
                passenger_pricing_available: is_array($passengerPricing) && $passengerPricing !== [],
                passenger_counts: $passengerCounts,
            ),
            expires_at: isset($offer['expires_at']) ? (string) $offer['expires_at'] : null,
            raw_reference: $offerId !== '' ? $offerId : null,
            raw_payload: SensitiveDataRedactor::redact($offer),
            marketing_carrier_chain: $carrierDisplay['marketing_carrier_chain'],
            operating_carrier_chain: $carrierDisplay['operating_carrier_chain'],
            validating_carrier: null,
            primary_display_carrier: $carrierDisplay['primary_display_carrier'],
            mixed_carrier: $carrierDisplay['mixed_carrier'],
            all_airline_codes: $carrierDisplay['all_airline_codes'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function extractOfferRows(array $payload): array
    {
        $rows = data_get($payload, 'data.offers', $payload['offers'] ?? null);
        if ($rows === null && isset($payload['data']) && is_array($payload['data']) && (($payload['data']['type'] ?? null) === 'offer')) {
            $rows = [$payload['data']];
        }
        if (! is_array($rows) && isset($payload['data']) && is_array($payload['data'])) {
            $rows = $payload['data'];
        }
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn (mixed $row): bool => is_array($row)));
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<array<string, int|string|null>>
     */
    private function extractSegments(array $offer): array
    {
        $segments = [];
        foreach ((array) ($offer['slices'] ?? []) as $slice) {
            if (! is_array($slice)) {
                continue;
            }
            foreach ((array) ($slice['segments'] ?? []) as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $segmentBaggage = $this->extractSegmentBaggage($segment);
                $segments[] = [
                    'origin' => strtoupper((string) data_get($segment, 'origin.iata_code', '')),
                    'destination' => strtoupper((string) data_get($segment, 'destination.iata_code', '')),
                    'departure_at' => (string) data_get($segment, 'departing_at', ''),
                    'arrival_at' => (string) data_get($segment, 'arriving_at', ''),
                    'flight_number' => (string) data_get($segment, 'marketing_carrier_flight_number', ''),
                    'airline_code' => strtoupper((string) data_get($segment, 'marketing_carrier.iata_code', '')),
                    'airline_name' => (string) data_get($segment, 'marketing_carrier.name', ''),
                    'operating_airline_code' => strtoupper((string) data_get($segment, 'operating_carrier.iata_code', '')),
                    'operating_airline_name' => (string) data_get($segment, 'operating_carrier.name', ''),
                    'duration_minutes' => null,
                    'cabin' => (string) data_get($segment, 'cabin_class', ''),
                    'baggage_checked' => $segmentBaggage['checked'],
                    'baggage_cabin' => $segmentBaggage['cabin'],
                    'baggage' => $segmentBaggage['summary'],
                ];
            }
        }

        return $segments;
    }

    /**
     * @param  list<array<string, int|string|null>>  $segments
     */
    private function baggageSummary(array $segments): ?string
    {
        $parts = [];
        foreach ($segments as $segment) {
            $value = trim((string) ($segment['baggage'] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(' · ', array_values(array_unique($parts)));
    }

    /**
     * @param  list<array<string, int|string|null>>  $segments
     * @return array{checked: ?string, cabin: ?string, summary: ?string}
     */
    private function aggregateBaggageFromSegments(array $segments): array
    {
        $checked = [];
        $cabin = [];
        foreach ($segments as $segment) {
            $checkedVal = trim((string) ($segment['baggage_checked'] ?? ''));
            $cabinVal = trim((string) ($segment['baggage_cabin'] ?? ''));
            if ($checkedVal !== '') {
                $checked[] = $checkedVal;
            }
            if ($cabinVal !== '') {
                $cabin[] = $cabinVal;
            }
        }

        $checked = array_values(array_unique($checked));
        $cabin = array_values(array_unique($cabin));
        $checkedText = $checked === [] ? null : implode(' / ', $checked);
        $cabinText = $cabin === [] ? null : implode(' / ', $cabin);
        $summary = $this->baggageSummary($segments);

        return [
            'checked' => $checkedText,
            'cabin' => $cabinText,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return array{checked: ?string, cabin: ?string, summary: ?string}
     */
    private function extractSegmentBaggage(array $segment): array
    {
        $rows = data_get($segment, 'passengers.0.baggages', []);
        if (! is_array($rows)) {
            return ['checked' => null, 'cabin' => null, 'summary' => null];
        }

        $checked = [];
        $cabin = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = $this->formatDuffelAllowance($row);
            if ($label === null) {
                continue;
            }
            $type = strtolower((string) ($row['type'] ?? ''));
            if (in_array($type, ['carry_on', 'cabin'], true)) {
                $cabin[] = $label;
            } else {
                $checked[] = $label;
            }
        }

        $checked = array_values(array_unique($checked));
        $cabin = array_values(array_unique($cabin));

        $summaryParts = [];
        if ($checked !== []) {
            $summaryParts[] = 'Checked baggage: '.implode(' / ', $checked);
        }
        if ($cabin !== []) {
            $summaryParts[] = 'Cabin baggage: '.implode(' / ', $cabin);
        }

        return [
            'checked' => $checked === [] ? null : implode(' / ', $checked),
            'cabin' => $cabin === [] ? null : implode(' / ', $cabin),
            'summary' => $summaryParts === [] ? null : implode(' · ', $summaryParts),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function formatDuffelAllowance(array $row): ?string
    {
        $quantity = (int) ($row['quantity'] ?? 0);
        $weightValue = (float) data_get($row, 'maximum_weight.value', 0);
        $weightUnit = strtolower((string) data_get($row, 'maximum_weight.unit', ''));

        if ($weightValue > 0 && $weightUnit !== '') {
            $weight = rtrim(rtrim(number_format($weightValue, 2, '.', ''), '0'), '.').' '.strtoupper($weightUnit);

            if ($quantity > 1) {
                return $quantity.' x '.$weight;
            }

            return $weight;
        }

        if ($quantity > 0) {
            return $quantity.' bag'.($quantity > 1 ? 's' : '');
        }

        return null;
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return (float) $value;
    }

    /**
     * Attempt to normalize supplier-provided per-passenger pricing if present.
     *
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>|null
     */
    private function extractPassengerPricing(array $offer, string $currency): ?array
    {
        $candidates = [
            data_get($offer, 'passenger_pricing'),
            data_get($offer, 'fare_breakdown.passenger_pricing'),
            data_get($offer, 'fare_details_by_passenger'),
            data_get($offer, 'passengers'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || $candidate === []) {
                continue;
            }
            $rows = [];
            foreach ($candidate as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $type = strtolower((string) ($row['type'] ?? $row['passenger_type'] ?? $row['ptc'] ?? ''));
                $amount = $this->money($row['total_amount'] ?? $row['amount'] ?? $row['total'] ?? 0);
                $base = $this->money($row['base_amount'] ?? $row['base_fare'] ?? 0);
                $taxes = $this->money($row['tax_amount'] ?? $row['taxes'] ?? 0);
                if ($amount <= 0 && $base <= 0 && $taxes <= 0) {
                    continue;
                }

                $rows[] = [
                    'supplier_passenger_id' => (string) ($row['id'] ?? $row['passenger_id'] ?? ('pax_'.($index + 1))),
                    'passenger_type' => $type !== '' ? $type : null,
                    'age' => isset($row['age']) ? (int) $row['age'] : null,
                    'base_amount' => $base > 0 ? $base : null,
                    'tax_amount' => $taxes > 0 ? $taxes : null,
                    'total_amount' => $amount > 0 ? $amount : ($base + $taxes),
                    'currency' => strtoupper((string) ($row['currency'] ?? $currency)),
                    'converted_total_pkr' => strtoupper((string) ($row['currency'] ?? $currency)) === 'PKR'
                        ? ($amount > 0 ? $amount : ($base + $taxes))
                        : null,
                    'fare_basis_code' => isset($row['fare_basis_code']) ? (string) $row['fare_basis_code'] : null,
                    'cabin_class' => isset($row['cabin_class']) ? (string) $row['cabin_class'] : null,
                    'fare_family' => isset($row['fare_family']) ? (string) $row['fare_family'] : null,
                ];
            }

            if ($rows !== []) {
                return array_values($rows);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array{adults:int,children:int,infants:int,total:int}
     */
    private function buildPassengerCounts(array $offer): array
    {
        $adults = 0;
        $children = 0;
        $infants = 0;
        $rows = data_get($offer, 'passengers');
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $type = strtolower((string) ($row['type'] ?? $row['passenger_type'] ?? 'adult'));
                if ($type === 'infant') {
                    $infants++;

                    continue;
                }
                if ($type === 'child') {
                    $children++;

                    continue;
                }
                if (isset($row['age']) && (int) $row['age'] < 2) {
                    $infants++;
                } elseif (isset($row['age']) && (int) $row['age'] < 18) {
                    $children++;
                } else {
                    $adults++;
                }
            }
        }

        return [
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'total' => $adults + $children + $infants,
        ];
    }

    private function durationMinutes(string $departureAt, string $arrivalAt): int
    {
        try {
            $start = new \DateTimeImmutable($departureAt);
            $end = new \DateTimeImmutable($arrivalAt);

            return max(0, (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60));
        } catch (\Throwable) {
            return 0;
        }
    }
}
