<?php

namespace App\Services\Suppliers\OneApi;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Normalization\OneApiOfferTokenSigner;
use App\Services\Suppliers\OneApi\Pricing\OneApiPricingService;
use App\Services\Suppliers\OneApi\Support\OneApiCorrelationContext;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;

class OneApiFareRevalidationService
{
    public function __construct(
        private readonly OneApiOfferTokenSigner $offerTokenSigner,
        private readonly OneApiWorkflowContextStore $workflowContextStore,
        private readonly OneApiPricingService $pricingService,
        private readonly OneApiCorrelationContext $correlationContext,
    ) {}

    public function revalidate(NormalizedFlightOfferData $offer, SupplierConnection $connection, ?string $fixturePath = null): OfferValidationResultData
    {
        $token = (string) data_get($offer->raw_payload, 'provider_context.signed_offer_token', '');
        if ($token === '') {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'invalid_offer',
                original_offer_id: $offer->offer_id,
                warnings: ['Missing signed One API offer token.'],
            );
        }

        try {
            $payload = $this->offerTokenSigner->verify($token);
        } catch (\Throwable) {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'stale_offer',
                original_offer_id: $offer->offer_id,
                warnings: ['Offer signature validation failed.'],
            );
        }

        $context = $this->workflowContextStore->create(
            (int) $connection->id,
            $this->correlationContext->newCorrelationId(),
            $payload,
            $connection->agency_id !== null ? (int) $connection->agency_id : null,
        );

        $diagnostic = $fixturePath !== null ? ['fixture_path' => $fixturePath] : [];
        if ($fixturePath === null && trim((string) (($connection->credentials ?? [])['soap_url'] ?? '')) === '') {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'configuration_error',
                original_offer_id: $offer->offer_id,
                warnings: ['SOAP URL is not configured; price/revalidation blocked.'],
            );
        }

        $price = $this->pricingService->initialPrice($connection, $context, $diagnostic);
        $newTid = trim((string) ($price['transaction_identifier'] ?? ''));
        $priorTid = trim((string) data_get($offer->raw_payload, 'provider_context.transaction_identifier', ''));
        if ($priorTid !== '' && $newTid !== '' && ! hash_equals($priorTid, $newTid)) {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'stale_offer',
                original_offer_id: $offer->offer_id,
                warnings: ['Supplier transaction identifier changed during revalidation.'],
            );
        }

        $settlement = $price['total_fare'] ?? $price['equi_base_fare'] ?? $price['base_fare'] ?? null;
        $newTotal = is_array($settlement) ? (float) $settlement['amount'] : null;
        $currency = is_array($settlement) ? (string) $settlement['currency'] : $offer->fare_breakdown->currency;
        $oldTotal = (float) $offer->fare_breakdown->supplier_total;

        $status = 'validated';
        $warnings = [];
        if ($newTotal !== null && abs($newTotal - $oldTotal) > 0.009) {
            $status = 'price_changed';
            $warnings[] = 'Supplier price changed during revalidation.';
        }

        return new OfferValidationResultData(
            is_valid: true,
            status: $status,
            original_offer_id: $offer->offer_id,
            price_changed: $status === 'price_changed',
            old_total: $oldTotal,
            new_total: $newTotal ?? $oldTotal,
            currency: $currency,
            warnings: $warnings,
            meta: [
                'one_api_workflow_context_id' => $context->contextId,
                'transaction_identifier' => $price['transaction_identifier'] ?? null,
                'old_supplier_total' => $oldTotal,
                'new_supplier_total' => $newTotal,
            ],
        );
    }
}
