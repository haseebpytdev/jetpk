<?php

namespace App\Services\Suppliers\OneApi\Ancillaries;

use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;

/**
 * Baggage / meal / seat-map SOAP orchestration (fixture-friendly via transport binding).
 */
class OneApiAncillaryCatalogService
{
    public function __construct(
        private readonly OneApiSoapTransportContract $soapTransport,
        private readonly OneApiConfigResolver $configResolver,
        private readonly OneApiBaggageResponseParser $baggageParser,
        private readonly OneApiMealResponseParser $mealParser,
        private readonly OneApiSeatMapResponseParser $seatParser,
    ) {}

    /**
     * @return array{baggage: list<array<string, mixed>>, meals: list<array<string, mixed>>, seats: list<array<string, mixed>>}
     */
    public function loadCatalog(SupplierConnection $connection, OneApiWorkflowContext $context, string $priceXml = '<soapenv:Envelope/>', array $diagnosticContext = []): array
    {
        $this->configResolver->resolve($connection);
        $corr = $context->contextId;
        $fixture = (string) ($diagnosticContext['fixture_path'] ?? '');
        $base = $fixture !== '' ? dirname($fixture) : base_path('tests/Fixtures/Suppliers/OneApi');
        $fixturePaths = array_merge(
            is_array($diagnosticContext['fixture_paths'] ?? null) ? $diagnosticContext['fixture_paths'] : [],
            [
                'baggage' => $base.'/ancillary_baggage.xml',
                'meal' => $base.'/ancillary_meals.xml',
                'seat_map' => $base.'/ancillary_seats.xml',
            ],
        );
        $baggageCtx = array_merge($diagnosticContext, ['fixture_paths' => $fixturePaths, 'fixture_path' => $fixturePaths['baggage']]);
        $mealCtx = array_merge($diagnosticContext, ['fixture_paths' => $fixturePaths, 'fixture_path' => $fixturePaths['meal']]);
        $seatCtx = array_merge($diagnosticContext, ['fixture_paths' => $fixturePaths, 'fixture_path' => $fixturePaths['seat_map']]);

        $baggage = $this->baggageParser->parse((string) ($this->soapTransport->call($connection, 'baggage', $priceXml, $corr, $baggageCtx)['raw_xml'] ?? ''));
        $meals = $this->mealParser->parse((string) ($this->soapTransport->call($connection, 'meal', $priceXml, $corr, $mealCtx)['raw_xml'] ?? ''));
        $seats = $this->seatParser->parse((string) ($this->soapTransport->call($connection, 'seat_map', $priceXml, $corr, $seatCtx)['raw_xml'] ?? ''));

        return compact('baggage', 'meals', 'seats');
    }
}
