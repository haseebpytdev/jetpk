<?php

namespace App\Data;

class OfferValidationResultData
{
    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $is_valid,
        public string $status,
        public ?string $original_offer_id = null,
        public ?NormalizedFlightOfferData $validated_offer = null,
        public bool $price_changed = false,
        public ?float $old_total = null,
        public ?float $new_total = null,
        public ?string $currency = null,
        public array $warnings = [],
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->is_valid,
            'status' => $this->status,
            'original_offer_id' => $this->original_offer_id,
            'validated_offer' => $this->validated_offer?->toArray(),
            'price_changed' => $this->price_changed,
            'old_total' => $this->old_total,
            'new_total' => $this->new_total,
            'currency' => $this->currency,
            'warnings' => $this->warnings,
            'meta' => $this->meta,
        ];
    }
}
