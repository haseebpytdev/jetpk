<?php

namespace App\Support\OneApi;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;
use App\Services\Suppliers\OneApi\Checkout\OneApiCheckoutFlowService;
use App\Services\Suppliers\OneApi\Normalization\OneApiOfferTokenSigner;
use App\Services\Suppliers\OneApi\OneApiFareRevalidationService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use Illuminate\Support\Facades\Http;

/**
 * Fixture-backed ISA matrix case execution (no live network).
 */
class OneApiTestMatrixRunner
{
    public function __construct(
        private readonly OneApiOfferTokenSigner $offerTokenSigner,
        private readonly OneApiFareRevalidationService $revalidationService,
        private readonly OneApiCheckoutFlowService $checkoutFlow,
        private readonly OneApiBookingService $bookingService,
        private readonly OneApiWorkflowContextStore $workflowContextStore,
    ) {}

    /**
     * @param  array{flow: string, id: string, test_case: string, key: string}  $case
     * @return array<string, mixed>
     */
    public function runCase(SupplierConnection $connection, array $case, bool $dryRun = false): array
    {
        $key = $case['key'];
        $isConnection = str_contains($key, 'connection');
        $isReturn = str_starts_with($key, 'return');
        $isBundle = str_contains($key, 'bundle');
        $isAncillary = str_contains($key, 'ancillary');

        if ($dryRun) {
            return [
                'internal_case_key' => $key,
                'result' => 'dry_run',
                'flow' => $case['flow'],
                'variant' => $isBundle ? 'bundle' : ($isAncillary ? 'ancillary' : 'basic'),
                'connection' => $isConnection,
                'return' => $isReturn,
            ];
        }

        Http::fake([
            '*' => Http::response(json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)),
        ]);

        $fixturePrice = base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml');
        $fixtureBook = $isBundle
            ? base_path('tests/Fixtures/Suppliers/OneApi/book_paid.xml')
            : base_path('tests/Fixtures/Suppliers/OneApi/book_paid.xml');

        $offer = $this->buildFixtureOffer($connection, $isReturn, $isConnection);
        $reval = $this->revalidationService->revalidate($offer, $connection, $fixturePrice);
        if (! $reval->is_valid) {
            return $this->failRow($case, 'revalidation_failed');
        }

        $contextId = (string) ($reval->meta['one_api_workflow_context_id'] ?? '');
        $catalog = $this->checkoutFlow->loadCatalog($connection, $contextId, ['fixture_path' => $fixturePrice], null, null, true);

        $selectionPayload = ['bundles' => [], 'baggage' => [], 'meals' => [], 'seats' => []];
        if ($isBundle && $catalog['bundles'] !== []) {
            $bundleId = $catalog['bundles'][0]['selection_id'] ?? null;
            if (is_string($bundleId) && $bundleId !== '') {
                $selectionPayload = [
                    'bundle_selection_ids' => [$bundleId],
                ];
            }
        }
        if ($isAncillary && $catalog['seats'] !== []) {
            $seatId = null;
            foreach ($catalog['seats'] as $seat) {
                if (($seat['available'] ?? false) === true) {
                    $seatId = $seat['selection_id'] ?? null;
                    break;
                }
            }
            if (is_string($seatId) && $seatId !== '') {
                $selectionPayload = ['seat_selection_ids' => [$seatId]];
            }
        }

        $selectionPayload = array_merge(['bundles' => [], 'baggage' => [], 'meals' => [], 'seats' => []], $selectionPayload);

        $final = $this->checkoutFlow->saveSelectionsAndFinalPrice(
            $connection,
            $contextId,
            $selectionPayload,
            ['final_price_fixture_path' => $fixturePrice],
            null,
            null,
            true,
        );

        if (! ($final['final_price_confirmed'] ?? false)) {
            return $this->failRow($case, 'final_price_failed');
        }

        $context = $this->workflowContextStore->get($contextId);
        $tid = (string) ($context?->transactionIdentifier ?? '');
        if ($tid === '') {
            return $this->failRow($case, 'missing_tid');
        }

        $user = \App\Models\User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $booking = \App\Models\Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'supplier' => SupplierProvider::OneApi->value,
            'meta' => [
                'one_api_context' => ['workflow_context_id' => $contextId],
                'supplier_connection_id' => $connection->id,
            ],
        ]);
        $bookResult = $this->bookingService->createSupplierBooking($booking, $connection, $user, [
            'fixture_path' => $fixtureBook,
        ]);
        if (! $bookResult->success) {
            return $this->failRow($case, 'booking_failed:'.($bookResult->error_code ?? 'unknown'));
        }

        $pnr = (string) ($bookResult->pnr ?? '');
        $jsessionEvidence = 'JSESSIONID_MASKED';
        $cookies = app(\App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract::class);
        if (method_exists($cookies, 'cookiesForSession')) {
            $cookieList = $cookies->cookiesForSession($contextId);
            if ($cookieList !== []) {
                $jsessionEvidence = substr(hash('sha256', implode(';', $cookieList)), 0, 16);
            }
        }

        return [
            'FLOW' => $case['flow'],
            'ID' => $case['id'],
            'Test Case' => $case['test_case'],
            'PNR' => $pnr,
            'TRANSACTION IDENTIFIER' => $tid,
            'JSESSIONID' => $jsessionEvidence,
            'To be Validated by ISA' => 'Yes',
            'internal_case_key' => $key,
            'mode' => 'fixture',
            'variant' => $isBundle ? 'bundle' : ($isAncillary ? 'ancillary' : 'basic'),
            'result' => 'pass',
        ];
    }

    /**
     * @param  array{flow: string, id: string, test_case: string, key: string}  $case
     * @return array<string, mixed>
     */
    private function failRow(array $case, string $reason): array
    {
        return [
            'FLOW' => $case['flow'],
            'ID' => $case['id'],
            'Test Case' => $case['test_case'],
            'internal_case_key' => $case['key'],
            'result' => 'fail',
            'error' => $reason,
        ];
    }

    private function buildFixtureOffer(SupplierConnection $connection, bool $isReturn, bool $isConnection): \App\Data\NormalizedFlightOfferData
    {
        $segments = $isConnection
            ? [
                ['origin' => 'SHJ', 'destination' => 'KHI', 'marketing_carrier' => 'G9', 'operating_carrier' => 'G9', 'flight_number' => 'G9101', 'departure_local' => '2026-08-15T10:00:00', 'arrival_local' => '2026-08-15T14:00:00'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'marketing_carrier' => 'G9', 'operating_carrier' => 'G9', 'flight_number' => 'G9102', 'departure_local' => '2026-08-15T15:00:00', 'arrival_local' => '2026-08-15T17:00:00'],
            ]
            : [['origin' => 'SHJ', 'destination' => 'KHI', 'marketing_carrier' => 'G9', 'operating_carrier' => 'G9', 'flight_number' => 'G9101', 'departure_local' => '2026-08-15T10:00:00', 'arrival_local' => '2026-08-15T12:30:00']];

        $token = $this->offerTokenSigner->sign([
            'supplier' => SupplierProvider::OneApi->value,
            'connection_id' => $connection->id,
            'segments' => $segments,
            'trip_type' => $isReturn ? 'return' : 'one_way',
            'expires_at' => time() + 3600,
        ]);

        return \App\Data\NormalizedFlightOfferData::fromArray([
            'offer_id' => 'matrix_offer',
            'supplier_provider' => SupplierProvider::OneApi->value,
            'supplier_connection_id' => $connection->id,
            'airline_code' => 'G9',
            'airline_name' => 'Air Arabia',
            'origin' => 'SHJ',
            'destination' => 'KHI',
            'departure_at' => '2026-08-15T10:00:00',
            'arrival_at' => '2026-08-15T12:30:00',
            'duration_minutes' => 150,
            'stops' => $isConnection ? 1 : 0,
            'cabin' => 'economy',
            'refundable' => false,
            'segments' => $segments,
            'fare_breakdown' => ['base_fare' => 250, 'taxes' => 0, 'supplier_fees' => 0, 'supplier_total' => 250, 'currency' => 'AED'],
            'raw_payload' => ['provider_context' => ['signed_offer_token' => $token]],
        ]);
    }
}
