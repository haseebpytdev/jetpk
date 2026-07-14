<?php

namespace App\Contracts\Suppliers;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

interface FlightSupplierInterface
{
    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData;

    public function validateOffer(NormalizedFlightOfferData|string $offer, FlightSearchRequestData $request, SupplierConnection $connection): OfferValidationResultData;

    public function provider(): SupplierProvider;
}
