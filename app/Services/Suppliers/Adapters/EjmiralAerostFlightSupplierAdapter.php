<?php

namespace App\Services\Suppliers\Adapters;

use App\Contracts\Suppliers\FlightSupplierInterface;
use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostSearchService;
use App\Services\Suppliers\SupplierDiagnosticLogger;

class EjmiralAerostFlightSupplierAdapter implements FlightSupplierInterface
{
    public function __construct(
        private readonly EjmiralAerostSearchService $searchService,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        if (! $this->connectionReady($connection)) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'warning',
                safeMessage: 'Connection is inactive for EJMIRAL AEROST search.',
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::EjmiralAerost,
                offers: [],
                warnings: ['EJMIRAL AEROST supplier connection is inactive.'],
                meta: ['connection_id' => $connection->id],
            );
        }

        return $this->searchService->search($request, $connection);
    }

    public function validateOffer(
        NormalizedFlightOfferData|string $offer,
        FlightSearchRequestData $request,
        SupplierConnection $connection,
    ): OfferValidationResultData {
        unset($request, $connection);

        if (is_string($offer)) {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'invalid_offer',
                original_offer_id: $offer,
                warnings: ['EJMIRAL AEROST offer snapshot is required for validation.'],
            );
        }

        return new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: $offer->offer_id,
            validated_offer: $offer,
            warnings: ['AEROST fare_info revalidation should be invoked before booking when available.'],
        );
    }

    public function provider(): SupplierProvider
    {
        return SupplierProvider::EjmiralAerost;
    }

    protected function connectionReady(SupplierConnection $connection): bool
    {
        return $connection->provider === SupplierProvider::EjmiralAerost
            && $connection->status === SupplierConnectionStatus::Active
            && (bool) $connection->is_active;
    }
}
