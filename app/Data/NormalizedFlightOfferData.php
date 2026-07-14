<?php

namespace App\Data;

class NormalizedFlightOfferData
{
    /**
     * @param  list<array<string, int|string|null>>  $segments
     * @param  list<string>  $marketing_carrier_chain
     * @param  list<string>  $operating_carrier_chain
     * @param  list<string>  $all_airline_codes
     * @param  array<string, mixed>|null  $raw_payload
     * @param  list<array<string, mixed>>  $branded_fares
     */
    public function __construct(
        public string $offer_id,
        public string $supplier_provider,
        public ?int $supplier_connection_id,
        public string $airline_code,
        public string $airline_name,
        public ?string $flight_number,
        public string $origin,
        public string $destination,
        public string $departure_at,
        public string $arrival_at,
        public int $duration_minutes,
        public int $stops,
        public string $cabin,
        public ?string $fare_family,
        public bool $refundable,
        public ?int $seats_left,
        public array $segments,
        public BaggageAllowanceData $baggage,
        public FareBreakdownData $fare_breakdown,
        public ?string $expires_at = null,
        public ?string $raw_reference = null,
        public ?array $raw_payload = null,
        public array $marketing_carrier_chain = [],
        public array $operating_carrier_chain = [],
        public ?string $validating_carrier = null,
        public string $primary_display_carrier = '',
        public bool $mixed_carrier = false,
        public array $all_airline_codes = [],
        public array $branded_fares = [],
        /** Sabre GDS vs NDC (and similar); preserved through validation/checkout snapshots. */
        public ?string $distribution_channel = null,
    ) {}

    /**
     * Build marketing/operating chains, headline carrier, mixed flag, and joined flight numbers
     * from normalized segment rows (correct segment order).
     *
     * @param  list<array<string, mixed>>  $segmentRows
     * @return array{
     *     marketing_carrier_chain: list<string>,
     *     operating_carrier_chain: list<string>,
     *     primary_display_carrier: string,
     *     mixed_carrier: bool,
     *     all_airline_codes: list<string>,
     *     headline_flight_number: ?string,
     *     headline_airline_name: string
     * }
     */
    public static function deriveMultiSegmentCarrierDisplay(
        array $segmentRows,
        ?string $validatingCarrierCode,
        string $headlineAirlineFallback,
    ): array {
        $marketing = [];
        $operating = [];
        $flightParts = [];
        $firstMarketingName = '';

        foreach ($segmentRows as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $m = strtoupper(trim((string) ($seg['airline_code'] ?? '')));
            if ($m !== '') {
                $marketing[] = $m;
            }
            $o = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            if ($o !== '') {
                $operating[] = $o;
            }
            $fn = trim((string) ($seg['flight_number'] ?? ''));
            if ($m !== '' && $fn !== '') {
                $flightParts[] = $m.$fn;
            } elseif ($fn !== '') {
                $flightParts[] = $fn;
            }
            if ($firstMarketingName === '') {
                $nm = trim((string) ($seg['airline_name'] ?? ''));
                if ($nm !== '') {
                    $firstMarketingName = $nm;
                }
            }
        }

        $mUnique = array_values(array_unique($marketing));
        $oUnique = array_values(array_unique($operating));
        $v = $validatingCarrierCode !== null ? strtoupper(trim($validatingCarrierCode)) : '';
        $validating = $v !== '' ? $v : null;

        $primary = $mUnique[0] ?? strtoupper(trim($headlineAirlineFallback));
        if ($primary === '' || $primary === 'XX') {
            $primary = $validating ?? 'XX';
        }

        $all = [];
        foreach (array_merge($marketing, $operating, $validating !== null ? [$validating] : []) as $c) {
            $c = strtoupper(trim((string) $c));
            if ($c === '' || $c === 'XX') {
                continue;
            }
            if (! in_array($c, $all, true)) {
                $all[] = $c;
            }
        }

        $mixed = count($mUnique) > 1;

        $headlineFn = null;
        if (count($segmentRows) === 1 && isset($segmentRows[0]) && is_array($segmentRows[0])) {
            $fn0 = trim((string) ($segmentRows[0]['flight_number'] ?? ''));
            $headlineFn = $fn0 !== '' ? $fn0 : null;
        } elseif ($flightParts !== []) {
            $headlineFn = implode('+', $flightParts);
        }

        $headlineName = $firstMarketingName;
        if ($headlineName === '') {
            $headlineName = $mixed ? implode(' + ', $mUnique) : $primary;
        }

        return [
            'marketing_carrier_chain' => $mUnique,
            'operating_carrier_chain' => $oUnique,
            'primary_display_carrier' => $primary,
            'mixed_carrier' => $mixed,
            'all_airline_codes' => $all,
            'headline_flight_number' => $headlineFn,
            'headline_airline_name' => $headlineName !== '' ? $headlineName : $primary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'offer_id' => $this->offer_id,
            'supplier_offer_id' => $this->offer_id,
            'supplier_provider' => $this->supplier_provider,
            'supplier_connection_id' => $this->supplier_connection_id,
            'airline_code' => $this->airline_code,
            'airline_name' => $this->airline_name,
            'flight_number' => $this->flight_number,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure_at' => $this->departure_at,
            'arrival_at' => $this->arrival_at,
            'duration_minutes' => $this->duration_minutes,
            'stops' => $this->stops,
            'cabin' => $this->cabin,
            'fare_family' => $this->fare_family,
            'refundable' => $this->refundable,
            'seats_left' => $this->seats_left,
            'segments' => $this->segments,
            'baggage' => $this->baggage->toArray(),
            'fare_breakdown' => $this->fare_breakdown->toArray(),
            'expires_at' => $this->expires_at,
            'raw_reference' => $this->raw_reference,
            'raw_payload' => $this->raw_payload,
            'provider_context' => is_array($this->raw_payload['provider_context'] ?? null)
                ? $this->raw_payload['provider_context']
                : [],
            'marketing_carrier_chain' => $this->marketing_carrier_chain,
            'operating_carrier_chain' => $this->operating_carrier_chain,
            'validating_carrier' => $this->validating_carrier,
            'primary_display_carrier' => $this->primary_display_carrier !== ''
                ? $this->primary_display_carrier
                : $this->airline_code,
            'mixed_carrier' => $this->mixed_carrier,
            'all_airline_codes' => $this->all_airline_codes,
            'branded_fares' => $this->branded_fares,
            ...($this->distribution_channel !== null && trim($this->distribution_channel) !== ''
                ? ['distribution_channel' => $this->distribution_channel]
                : []),
            ...(is_array($this->raw_payload['customer_display_fields'] ?? null)
                ? $this->raw_payload['customer_display_fields']
                : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $baggage = is_array($data['baggage'] ?? null) ? $data['baggage'] : [];
        $fare = is_array($data['fare_breakdown'] ?? null) ? $data['fare_breakdown'] : [];

        $dto = new self(
            offer_id: (string) ($data['offer_id'] ?? $data['id'] ?? ''),
            supplier_provider: (string) ($data['supplier_provider'] ?? ''),
            supplier_connection_id: isset($data['supplier_connection_id']) ? (int) $data['supplier_connection_id'] : null,
            airline_code: (string) ($data['airline_code'] ?? $data['carrier_code'] ?? 'XX'),
            airline_name: (string) ($data['airline_name'] ?? ''),
            flight_number: isset($data['flight_number']) ? (string) $data['flight_number'] : null,
            origin: (string) ($data['origin'] ?? ''),
            destination: (string) ($data['destination'] ?? ''),
            departure_at: (string) ($data['departure_at'] ?? $data['depart_at'] ?? ''),
            arrival_at: (string) ($data['arrival_at'] ?? $data['arrive_at'] ?? ''),
            duration_minutes: (int) ($data['duration_minutes'] ?? 0),
            stops: (int) ($data['stops'] ?? 0),
            cabin: (string) ($data['cabin'] ?? 'economy'),
            fare_family: isset($data['fare_family']) ? (string) $data['fare_family'] : null,
            refundable: (bool) ($data['refundable'] ?? false),
            seats_left: isset($data['seats_left']) ? (int) $data['seats_left'] : null,
            segments: is_array($data['segments'] ?? null) ? $data['segments'] : [],
            baggage: new BaggageAllowanceData(
                checked: isset($baggage['checked']) ? (string) $baggage['checked'] : null,
                cabin: isset($baggage['cabin']) ? (string) $baggage['cabin'] : null,
                summary: isset($baggage['summary']) ? (string) $baggage['summary'] : (is_string($data['baggage'] ?? null) ? $data['baggage'] : null),
            ),
            fare_breakdown: new FareBreakdownData(
                base_fare: (float) ($fare['base_fare'] ?? $data['base_fare'] ?? 0),
                taxes: (float) ($fare['taxes'] ?? $data['taxes'] ?? 0),
                supplier_fees: (float) ($fare['supplier_fees'] ?? 0),
                supplier_total: (float) ($fare['supplier_total'] ?? (($fare['base_fare'] ?? $data['base_fare'] ?? 0) + ($fare['taxes'] ?? $data['taxes'] ?? 0))),
                currency: (string) ($fare['currency'] ?? $data['currency'] ?? 'PKR'),
                passenger_pricing: is_array($fare['passenger_pricing'] ?? null) ? $fare['passenger_pricing'] : null,
                passenger_pricing_available: (bool) ($fare['passenger_pricing_available'] ?? false),
                passenger_counts: is_array($fare['passenger_counts'] ?? null) ? $fare['passenger_counts'] : [],
                fare_basis_codes: is_array($fare['fare_basis_codes'] ?? null) ? $fare['fare_basis_codes'] : [],
                display_base_fare: isset($fare['display_base_fare']) ? (float) $fare['display_base_fare'] : null,
                display_taxes: isset($fare['display_taxes']) ? (float) $fare['display_taxes'] : null,
                raw_base_fare: isset($fare['raw_base_fare']) ? (float) $fare['raw_base_fare'] : null,
                base_fare_display_source: isset($fare['base_fare_display_source'])
                    ? (string) $fare['base_fare_display_source']
                    : null,
                breakdown_reconciled: (bool) ($fare['breakdown_reconciled'] ?? false),
            ),
            expires_at: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            raw_reference: isset($data['raw_reference']) ? (string) $data['raw_reference'] : null,
            raw_payload: is_array($data['raw_payload'] ?? null) ? $data['raw_payload'] : null,
            marketing_carrier_chain: self::stringListFromData($data, 'marketing_carrier_chain'),
            operating_carrier_chain: self::stringListFromData($data, 'operating_carrier_chain'),
            validating_carrier: isset($data['validating_carrier']) ? (string) $data['validating_carrier'] : null,
            primary_display_carrier: (string) ($data['primary_display_carrier'] ?? ''),
            mixed_carrier: (bool) ($data['mixed_carrier'] ?? false),
            all_airline_codes: self::stringListFromData($data, 'all_airline_codes'),
            branded_fares: self::brandedFaresListFromData($data),
            distribution_channel: self::distributionChannelFromData($data),
        );

        return self::hydrateCarrierFieldsIfLegacy($dto, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function distributionChannelFromData(array $data): ?string
    {
        foreach (['distribution_channel', 'provider_channel'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key])) {
                continue;
            }
            $value = trim($data[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected static function brandedFaresListFromData(array $data): array
    {
        $raw = $data['branded_fares'] ?? $data['fare_family_options'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected static function stringListFromData(array $data, string $key): array
    {
        $v = $data[$key] ?? null;
        if (! is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $x) {
            $s = strtoupper(trim((string) $x));
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Backfill carrier display fields for snapshots stored before Phase S31.
     */
    protected static function hydrateCarrierFieldsIfLegacy(self $dto, array $data): self
    {
        if (array_key_exists('all_airline_codes', $data) && is_array($data['all_airline_codes']) && $data['all_airline_codes'] !== []) {
            return $dto;
        }
        if (array_key_exists('marketing_carrier_chain', $data) && is_array($data['marketing_carrier_chain']) && $data['marketing_carrier_chain'] !== []) {
            return $dto;
        }
        $segs = $dto->segments;
        if ($segs === []) {
            $pdc = strtoupper(trim($dto->primary_display_carrier !== '' ? $dto->primary_display_carrier : $dto->airline_code));
            if ($pdc === '') {
                return $dto;
            }

            return new self(
                offer_id: $dto->offer_id,
                supplier_provider: $dto->supplier_provider,
                supplier_connection_id: $dto->supplier_connection_id,
                airline_code: $dto->airline_code,
                airline_name: $dto->airline_name,
                flight_number: $dto->flight_number,
                origin: $dto->origin,
                destination: $dto->destination,
                departure_at: $dto->departure_at,
                arrival_at: $dto->arrival_at,
                duration_minutes: $dto->duration_minutes,
                stops: $dto->stops,
                cabin: $dto->cabin,
                fare_family: $dto->fare_family,
                refundable: $dto->refundable,
                seats_left: $dto->seats_left,
                segments: $dto->segments,
                baggage: $dto->baggage,
                fare_breakdown: $dto->fare_breakdown,
                expires_at: $dto->expires_at,
                raw_reference: $dto->raw_reference,
                raw_payload: $dto->raw_payload,
                marketing_carrier_chain: [$pdc],
                operating_carrier_chain: [],
                validating_carrier: $dto->validating_carrier,
                primary_display_carrier: $pdc,
                mixed_carrier: false,
                all_airline_codes: array_values(array_unique(array_filter([
                    $pdc,
                    $dto->validating_carrier ? strtoupper(trim($dto->validating_carrier)) : '',
                ]))),
                branded_fares: $dto->branded_fares,
                distribution_channel: $dto->distribution_channel,
            );
        }

        $derived = self::deriveMultiSegmentCarrierDisplay(
            $segs,
            $dto->validating_carrier,
            $dto->airline_code,
        );

        return new self(
            offer_id: $dto->offer_id,
            supplier_provider: $dto->supplier_provider,
            supplier_connection_id: $dto->supplier_connection_id,
            airline_code: $dto->airline_code,
            airline_name: $dto->airline_name,
            flight_number: $dto->flight_number,
            origin: $dto->origin,
            destination: $dto->destination,
            departure_at: $dto->departure_at,
            arrival_at: $dto->arrival_at,
            duration_minutes: $dto->duration_minutes,
            stops: $dto->stops,
            cabin: $dto->cabin,
            fare_family: $dto->fare_family,
            refundable: $dto->refundable,
            seats_left: $dto->seats_left,
            segments: $dto->segments,
            baggage: $dto->baggage,
            fare_breakdown: $dto->fare_breakdown,
            expires_at: $dto->expires_at,
            raw_reference: $dto->raw_reference,
            raw_payload: $dto->raw_payload,
            marketing_carrier_chain: $derived['marketing_carrier_chain'],
            operating_carrier_chain: $derived['operating_carrier_chain'],
            validating_carrier: $dto->validating_carrier,
            primary_display_carrier: $derived['primary_display_carrier'],
            mixed_carrier: $derived['mixed_carrier'],
            all_airline_codes: $derived['all_airline_codes'],
            branded_fares: $dto->branded_fares,
            distribution_channel: $dto->distribution_channel,
        );
    }
}
