<?php

namespace App\Data;

use App\Enums\SupplierProvider;

class FlightSearchResultData
{
    /**
     * @param  list<NormalizedFlightOfferData>  $offers
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public SupplierProvider $supplier_provider,
        public array $offers = [],
        public array $warnings = [],
        public array $meta = [],
    ) {}
}
