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
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use App\Services\Suppliers\Iati\IatiFlightSearchService;
use App\Services\Suppliers\SupplierDiagnosticLogger;

class IatiFlightSupplierAdapter implements FlightSupplierInterface
{
    public function __construct(
        private readonly IatiFlightSearchService $searchService,
        private readonly IatiFareRevalidationService $revalidationService,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        if (! $this->connectionReady($connection)) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'warning',
                safeMessage: 'Connection is inactive for IATI search.',
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Iati,
                offers: [],
                warnings: ['IATI supplier connection is inactive.'],
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
        unset($request);

        if (is_string($offer)) {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'invalid_offer',
                original_offer_id: $offer,
                warnings: ['IATI offer snapshot is required for validation.'],
            );
        }

        if (! $this->connectionReady($connection)) {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'inactive_connection',
                original_offer_id: $offer->offer_id,
                warnings: ['IATI supplier connection is inactive.'],
            );
        }

        return $this->revalidationService->revalidate($offer, $connection);
    }

    public function provider(): SupplierProvider
    {
        return SupplierProvider::Iati;
    }

    protected function connectionReady(SupplierConnection $connection): bool
    {
        if ($connection->provider !== SupplierProvider::Iati) {
            return false;
        }

        $status = $connection->status?->value ?? (string) $connection->status;

        return $connection->is_active || $status === SupplierConnectionStatus::Active->value;
    }
}
