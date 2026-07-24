<?php

namespace App\Services\Suppliers\OneApi\Pricing;

use App\Models\SupplierConnection;
use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;

class OneApiPricingService
{
    public function __construct(
        private readonly OneApiAirPriceRequestBuilder $requestBuilder,
        private readonly OneApiAirPriceResponseParser $responseParser,
        private readonly OneApiSoapTransportContract $soapTransport,
        private readonly OneApiWorkflowContextStore $workflowContextStore,
    ) {}

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function initialPrice(
        SupplierConnection $connection,
        OneApiWorkflowContext $context,
        array $diagnosticContext = [],
    ): array {
        $segments = is_array($context->signedOfferPayload['segments'] ?? null)
            ? $context->signedOfferPayload['segments']
            : [];
        $ondGroups = [$segments];
        $direction = ($context->signedOfferPayload['trip_type'] ?? '') === 'return' ? 'Return' : 'OneWay';

        $xml = $this->requestBuilder->buildInitialPrice($connection, $context->signedOfferPayload, $ondGroups, $direction);
        $parsed = $this->soapTransport->call(
            $connection,
            'price',
            $xml,
            $context->contextId,
            $diagnosticContext,
        );

        $price = $this->responseParser->parse($parsed);
        if (($price['transaction_identifier'] ?? '') !== '') {
            $context->transactionIdentifier = (string) $price['transaction_identifier'];
        }
        if (method_exists($this->soapTransport, 'cookiesForSession')) {
            $cookies = $this->soapTransport->cookiesForSession($context->contextId);
            if ($cookies !== []) {
                $context->cookieJar = $cookies;
            }
        }
        $context->moneySnapshot = [
            'supplier_settlement' => $price['total_fare'] ?? $price['equi_base_fare'] ?? $price['base_fare'],
            'price_confirmed' => true,
        ];
        $context->originDestinationGroups = $ondGroups;
        $this->workflowContextStore->put($context);

        return $price;
    }
}
