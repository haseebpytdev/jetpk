<?php

namespace App\Data;

class FareBreakdownData
{
    /**
     * @param  list<array<string, mixed>>|null  $passenger_pricing
     */
    public function __construct(
        public float $base_fare,
        public float $taxes,
        public float $supplier_fees,
        public float $supplier_total,
        public string $currency = 'PKR',
        public ?array $passenger_pricing = null,
        public bool $passenger_pricing_available = false,
        public array $passenger_counts = [],
        public array $fare_basis_codes = [],
        public ?float $display_base_fare = null,
        public ?float $display_taxes = null,
        public ?float $raw_base_fare = null,
        public ?string $base_fare_display_source = null,
        public bool $breakdown_reconciled = false,
    ) {}

    /**
     * @return array<string, float|string|bool|null|list<mixed>|array<string, mixed>>
     */
    public function toArray(): array
    {
        $out = [
            'base_fare' => $this->base_fare,
            'taxes' => $this->taxes,
            'supplier_fees' => $this->supplier_fees,
            'supplier_total' => $this->supplier_total,
            'currency' => $this->currency,
            'passenger_pricing' => $this->passenger_pricing,
            'passenger_pricing_available' => $this->passenger_pricing_available || (is_array($this->passenger_pricing) && $this->passenger_pricing !== []),
            'has_passenger_pricing' => $this->passenger_pricing_available || (is_array($this->passenger_pricing) && $this->passenger_pricing !== []),
            'passenger_counts' => $this->passenger_counts,
            'fare_basis_codes' => $this->fare_basis_codes,
            'breakdown_reconciled' => $this->breakdown_reconciled,
        ];

        if ($this->display_base_fare !== null) {
            $out['display_base_fare'] = $this->display_base_fare;
        }
        if ($this->display_taxes !== null) {
            $out['display_taxes'] = $this->display_taxes;
        }
        if ($this->raw_base_fare !== null) {
            $out['raw_base_fare'] = $this->raw_base_fare;
        }
        if ($this->base_fare_display_source !== null && $this->base_fare_display_source !== '') {
            $out['base_fare_display_source'] = $this->base_fare_display_source;
        }

        return $out;
    }
}
