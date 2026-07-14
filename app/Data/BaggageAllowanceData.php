<?php

namespace App\Data;

class BaggageAllowanceData
{
    public function __construct(
        public ?string $checked = null,
        public ?string $cabin = null,
        public ?string $summary = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'checked' => $this->checked,
            'cabin' => $this->cabin,
            'summary' => $this->summary,
        ];
    }
}
