<?php

namespace App\Services\Suppliers\Adapters;

use App\Contracts\Suppliers\FlightSupplierInterface;
use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

class AirlineDirectFlightSupplierAdapter implements FlightSupplierInterface
{
    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        return new FlightSearchResultData(
            supplier_provider: SupplierProvider::AirlineDirect,
            offers: [],
            warnings: ['Provider adapter is configured but live search is not implemented yet.'],
            meta: ['connection_id' => $connection->id]
        );
    }

    public function provider(): SupplierProvider
    {
        return SupplierProvider::AirlineDirect;
    }

    public function validateOffer(NormalizedFlightOfferData|string $offer, FlightSearchRequestData $request, SupplierConnection $connection): OfferValidationResultData
    {
        return new OfferValidationResultData(
            is_valid: false,
            status: 'not_supported',
            original_offer_id: is_string($offer) ? $offer : $offer->offer_id,
            warnings: ['Airline direct offer validation is not supported yet.']
        );
    }
}
