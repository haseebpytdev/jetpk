<?php

namespace App\Data;

/**
 * Canonical selected branded/default fare option — supplier-agnostic checkout handoff slice.
 */
final class SelectedFareOption
{
    /**
     * @param  list<string>  $bookingClassesBySegment
     * @param  list<string>  $fareBasisCodesBySegment
     * @param  list<string>  $cabinCodesBySegment
     * @param  list<string>  $marketingCarrierChain
     * @param  list<string>  $operatingCarrierChain
     * @param  array<string, mixed>  $supplierReferences
     * @param  array<string, mixed>|null  $passengerPricing
     */
    public function __construct(
        public ?string $fareOptionKey = null,
        public ?string $brandCode = null,
        public ?string $brandName = null,
        public ?string $fareFamily = null,
        public ?float $selectedPriceTotal = null,
        public ?float $selectedSupplierTotal = null,
        public ?string $selectedCurrency = null,
        public ?string $supplierCurrency = null,
        public ?string $baggageSummary = null,
        public ?string $cabin = null,
        public ?string $validatingCarrier = null,
        public array $bookingClassesBySegment = [],
        public array $fareBasisCodesBySegment = [],
        public array $cabinCodesBySegment = [],
        public array $marketingCarrierChain = [],
        public array $operatingCarrierChain = [],
        public int $segmentCount = 0,
        public bool $brandedFareSupported = true,
        public array $supplierReferences = [],
        public ?array $passengerPricing = null,
    ) {}

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $offer
     */
    public static function fromIntentArray(array $intent, array $offer = []): self
    {
        $fareBasisBySeg = self::stringList(
            $intent['fare_basis_codes_by_segment']
            ?? $intent['fare_basis_codes']
            ?? []
        );
        $singleFb = trim((string) ($intent['fare_basis'] ?? ''));
        if ($fareBasisBySeg === [] && $singleFb !== '') {
            $fareBasisBySeg = [$singleFb];
        }

        $bookingBySeg = self::stringList(
            $intent['booking_classes_by_segment']
            ?? (trim((string) ($intent['booking_class'] ?? '')) !== '' ? [$intent['booking_class']] : [])
        );

        $cabinBySeg = self::stringList($intent['cabin_by_segment'] ?? []);
        $cabin = trim((string) ($intent['cabin'] ?? ''));
        if ($cabinBySeg === [] && $cabin !== '') {
            $cabinBySeg = [$cabin];
        }

        $segments = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];
        $segmentCount = count($segments) > 0 ? count($segments) : max(count($fareBasisBySeg), count($bookingBySeg));

        $mkt = [];
        $op = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $m = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_airline'] ?? '')));
            $o = strtoupper(trim((string) ($seg['operating_airline_code'] ?? $seg['operating_carrier'] ?? $m)));
            if ($m !== '') {
                $mkt[] = $m;
            }
            if ($o !== '') {
                $op[] = $o;
            }
        }

        $priceTotal = isset($intent['displayed_price']) && is_numeric($intent['displayed_price'])
            ? (float) $intent['displayed_price']
            : (isset($intent['price_total']) && is_numeric($intent['price_total']) ? (float) $intent['price_total'] : null);

        $supplierTotal = isset($intent['supplier_total']) && is_numeric($intent['supplier_total'])
            ? (float) $intent['supplier_total']
            : $priceTotal;

        $brandName = trim((string) ($intent['brand_name'] ?? $intent['name'] ?? ''));

        return new self(
            fareOptionKey: self::nullableTrim($intent['fare_option_key'] ?? $intent['option_key'] ?? null),
            brandCode: self::nullableTrim($intent['brand_code'] ?? $intent['code'] ?? null),
            brandName: $brandName !== '' ? $brandName : null,
            fareFamily: $brandName !== '' ? $brandName : null,
            selectedPriceTotal: $priceTotal,
            selectedSupplierTotal: $supplierTotal,
            selectedCurrency: self::nullableTrim($intent['displayed_currency'] ?? $intent['currency'] ?? $offer['pricing_currency'] ?? 'PKR'),
            supplierCurrency: self::nullableTrim($intent['supplier_currency'] ?? $offer['currency'] ?? null),
            baggageSummary: self::nullableTrim($intent['baggage_summary'] ?? $intent['baggage'] ?? null),
            cabin: $cabin !== '' ? $cabin : null,
            validatingCarrier: self::nullableTrim($intent['validating_carrier'] ?? $offer['validating_carrier'] ?? null),
            bookingClassesBySegment: $bookingBySeg,
            fareBasisCodesBySegment: $fareBasisBySeg,
            cabinCodesBySegment: $cabinBySeg,
            marketingCarrierChain: $mkt,
            operatingCarrierChain: $op,
            segmentCount: $segmentCount,
            brandedFareSupported: ! ((bool) ($intent['branded_fare_supported'] ?? true) === false
                || (bool) ($intent['is_synthetic_default'] ?? false)),
            supplierReferences: is_array($intent['provider_context'] ?? null)
                ? $intent['provider_context']
                : (is_array($intent['supplier_references'] ?? null) ? $intent['supplier_references'] : []),
            passengerPricing: is_array($intent['passenger_pricing'] ?? null) ? $intent['passenger_pricing'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'fare_option_key' => $this->fareOptionKey,
            'option_key' => $this->fareOptionKey,
            'brand_code' => $this->brandCode,
            'brand_name' => $this->brandName,
            'name' => $this->brandName,
            'fare_family' => $this->fareFamily,
            'displayed_price' => $this->selectedPriceTotal,
            'price_total' => $this->selectedSupplierTotal,
            'displayed_currency' => $this->selectedCurrency,
            'supplier_currency' => $this->supplierCurrency,
            'baggage_summary' => $this->baggageSummary,
            'baggage' => $this->baggageSummary,
            'cabin' => $this->cabin,
            'validating_carrier' => $this->validatingCarrier,
            'booking_class' => $this->bookingClassesBySegment[0] ?? null,
            'fare_basis' => $this->fareBasisCodesBySegment[0] ?? null,
            'booking_classes_by_segment' => $this->bookingClassesBySegment,
            'fare_basis_codes_by_segment' => $this->fareBasisCodesBySegment,
            'fare_basis_codes' => $this->fareBasisCodesBySegment,
            'cabin_by_segment' => $this->cabinCodesBySegment,
            'segment_count' => $this->segmentCount > 0 ? $this->segmentCount : null,
            'branded_fare_supported' => $this->brandedFareSupported,
            'provider_context' => $this->supplierReferences !== [] ? $this->supplierReferences : null,
            'passenger_pricing' => $this->passengerPricing,
        ], static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @return list<string>
     */
    protected static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v): string => strtoupper(trim((string) $v)),
            $value
        ), static fn (string $s): bool => $s !== ''));
    }

    protected static function nullableTrim(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s !== '' ? $s : null;
    }
}
