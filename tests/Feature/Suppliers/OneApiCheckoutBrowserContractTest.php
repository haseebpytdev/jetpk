<?php

namespace Tests\Feature\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Checkout\OneApiCheckoutFlowService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiCheckoutBrowserContractTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public function test_passenger_page_includes_one_api_assets_only_for_one_api_offer(): void
    {
        $html = view('frontend.bookings.one-api.extras', [
            'workflowContextId' => 'ctx-test',
            'supplierConnectionId' => 1,
            'o' => ['supplier_connection_id' => 1],
        ])->render();

        $this->assertStringContainsString('data-one-api-checkout', $html);
        $this->assertStringContainsString('data-one-api-confirm-price', $html);
        $this->assertStringNotContainsString('TID_', $html);
    }

    public function test_catalog_returns_opaque_selection_ids(): void
    {
        $agency = Agency::factory()->create();
        $connection = $this->connection($agency->id);
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr', ['segments' => [['origin' => 'SHJ', 'destination' => 'KHI']]], $agency->id);
        $flow = app(OneApiCheckoutFlowService::class);
        $catalog = $flow->loadCatalog($connection, $context->contextId, [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ], null, null, true);
        $this->assertNotEmpty($catalog['bundles']);
        $this->assertArrayHasKey('selection_id', $catalog['bundles'][0]);
        $this->assertStringStartsWith('oa_sel_', $catalog['bundles'][0]['selection_id']);
    }

    public function test_tampered_selection_id_rejected(): void
    {
        $agency = Agency::factory()->create();
        $connection = $this->connection($agency->id);
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr', [], $agency->id);
        $context->moneySnapshot = ['price_confirmed' => true, 'catalog_registry' => []];
        $store->put($context);

        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $this->actingAs($user)->postJson(route('booking.one-api.final-price'), [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
            'bundle_selection_ids' => ['oa_sel_invalidtamper000000000'],
        ])->assertStatus(422);
    }

    public function test_unavailable_seat_selection_rejected(): void
    {
        $agency = Agency::factory()->create();
        $connection = $this->connection($agency->id);
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr', [], $agency->id);
        $context->moneySnapshot = [
            'price_confirmed' => true,
            'catalog_registry' => [
                'oa_sel_seatblocked' => [
                    'type' => 'seat',
                    'passenger_ref' => 'A1',
                    'segment_ref' => '1',
                    'seat_number' => '12B',
                    'available' => false,
                ],
            ],
        ];
        $store->put($context);

        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $this->actingAs($user)->postJson(route('booking.one-api.final-price'), [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
            'seat_selection_ids' => ['oa_sel_seatblocked'],
        ])->assertStatus(422);
    }

    private function connection(int $agencyId): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'agency_id' => $agencyId,
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'ONE_API_TEST_AGENT',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => 'https://example.test/soap',
            ],
        ]);
    }
}
