<?php

namespace App\Services\Suppliers\OneApi\Checkout;

use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Ancillaries\OneApiAncillaryCatalogService;
use App\Services\Suppliers\OneApi\Bundles\OneApiBundleParser;
use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;
use App\Services\Suppliers\OneApi\Pricing\OneApiPricingService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Support\OneApi\OneApiFixtureTransportScope;
use App\Support\OneApi\OneApiWorkflowContextGuard;

/**
 * Server-side One API checkout: catalog load, selection validation, final price gate.
 */
class OneApiCheckoutFlowService
{
    public function __construct(
        private readonly OneApiWorkflowContextStore $contextStore,
        private readonly OneApiPricingService $pricingService,
        private readonly OneApiAncillaryCatalogService $ancillaryCatalog,
        private readonly OneApiBundleParser $bundleParser,
        private readonly OneApiCheckoutSelectionValidator $selectionValidator,
        private readonly OneApiCheckoutCatalogPresenter $catalogPresenter,
        private readonly OneApiWorkflowContextGuard $workflowGuard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function loadCatalog(
        SupplierConnection $connection,
        string $contextId,
        array $diagnosticContext = [],
        ?User $actor = null,
        ?string $sessionId = null,
        bool $internalFixtureRunner = false,
    ): array {
        $context = $this->requireContext($contextId);
        $context = $this->authorizeContext($connection, $context, $actor, $sessionId, $internalFixtureRunner);
        if (! ($context->moneySnapshot['price_confirmed'] ?? false)) {
            $fixturePath = (string) ($diagnosticContext['fixture_path'] ?? '');
            if ($fixturePath === '') {
                $price = $this->pricingService->initialPrice($connection, $context, $diagnosticContext);
            } else {
                $price = $this->pricingService->initialPrice($connection, $context, array_merge($diagnosticContext, ['fixture_path' => $fixturePath]));
            }
            if ($fixturePath !== '') {
                $context->moneySnapshot['bundles'] = $this->bundleParser->parseFromPriceXml((string) file_get_contents(
                    OneApiFixtureTransportScope::resolveReadableFixturePath($fixturePath)
                ));
            }
            $context->moneySnapshot['price_confirmed'] = true;
            if ($price !== []) {
                $context->transactionIdentifier = (string) ($price['transaction_identifier'] ?? $context->transactionIdentifier);
            }
            $this->contextStore->put($context);
        }

        $catalog = $this->ancillaryCatalog->loadCatalog($connection, $context, '<soapenv:Envelope/>', $diagnosticContext);
        $bundles = $context->moneySnapshot['bundles'] ?? [];
        $presented = $this->catalogPresenter->present($context, $bundles, $catalog['baggage'], $catalog['meals'], $catalog['seats']);
        $this->contextStore->put($context);

        return [
            'workflow_context_id' => $context->contextId,
            'bundles' => $presented['bundles'],
            'baggage' => $presented['baggage'],
            'meals' => $presented['meals'],
            'seats' => $presented['seats'],
            'final_price_required' => true,
            'indicative_only' => ! ($context->moneySnapshot['final_price_confirmed'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveSelectionsAndFinalPrice(
        SupplierConnection $connection,
        string $contextId,
        array $payload,
        array $diagnosticContext = [],
        ?User $actor = null,
        ?string $sessionId = null,
        bool $internalFixtureRunner = false,
    ): array {
        $context = $this->requireContext($contextId);
        $context = $this->authorizeContext($connection, $context, $actor, $sessionId, $internalFixtureRunner);
        $this->selectionValidator->validate($context, $payload, (bool) ($context->moneySnapshot['final_price_confirmed'] ?? false));

        $resolved = $this->selectionValidator->resolveSubmittedPayload($context, $payload);
        $context->selectedBundles = $resolved['bundles'];
        $context->selectedAncillaries = $this->selectionValidator->normalizedAncillaries($resolved);

        $priceFixture = (string) ($diagnosticContext['final_price_fixture_path'] ?? $diagnosticContext['fixture_path'] ?? '');
        if ($priceFixture !== '') {
            $diagnosticContext['fixture_path'] = OneApiFixtureTransportScope::resolveReadableFixturePath($priceFixture);
        }

        $finalPrice = $this->pricingService->initialPrice($connection, $context, $diagnosticContext);
        $settlement = $finalPrice['total_fare'] ?? $finalPrice['equi_base_fare'] ?? $finalPrice['base_fare'] ?? null;

        if (! is_array($settlement) || ($settlement['amount'] ?? '') === '') {
            throw new OneApiValidationException('price_unavailable', 422, 'Final supplier price is unavailable.');
        }

        $context->moneySnapshot = array_merge($context->moneySnapshot, [
            'final_price_confirmed' => true,
            'final_price_at' => now()->toIso8601String(),
            'supplier_settlement' => $settlement,
            'version' => ((int) ($context->moneySnapshot['version'] ?? 0)) + 1,
            'client_posted_total_ignored' => true,
        ]);
        $context->transactionIdentifier = (string) ($finalPrice['transaction_identifier'] ?? $context->transactionIdentifier);
        $this->contextStore->put($context);

        return [
            'ok' => true,
            'workflow_context_id' => $context->contextId,
            'supplier_settlement' => $settlement,
            'price_changed' => (bool) ($payload['acknowledge_reprice'] ?? false),
            'final_price_confirmed' => true,
        ];
    }

    public function assertReadyForBooking(string $contextId): OneApiWorkflowContext
    {
        $context = $this->requireContext($contextId);
        if (! ($context->moneySnapshot['final_price_confirmed'] ?? false)) {
            throw new OneApiValidationException('stale_offer', 422, 'Final supplier price is required before booking.');
        }

        return $context;
    }

    private function authorizeContext(
        SupplierConnection $connection,
        OneApiWorkflowContext $context,
        ?User $actor,
        ?string $sessionId,
        bool $internalFixtureRunner,
    ): OneApiWorkflowContext {
        if ($internalFixtureRunner) {
            $this->workflowGuard->authorizeInternalFixtureRunner($connection, $context);

            return $context;
        }
        if ($actor === null || $sessionId === null || $sessionId === '') {
            throw new OneApiValidationException('workflow_not_found', 404, 'Workflow is not available.');
        }

        return $this->workflowGuard->authorizeHttp($actor, $connection, $context, $sessionId);
    }

    private function requireContext(string $contextId): OneApiWorkflowContext
    {
        $context = $this->contextStore->get($contextId);
        if ($context === null) {
            throw new OneApiValidationException('stale_offer', 422, 'Checkout session expired.');
        }

        return $context;
    }
}
