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

class OneApiCheckoutFlowFeatureTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public function test_final_price_endpoint_rejects_client_posted_amount(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->oneApiConnection($agency->id);

        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr-test', ['segments' => [], 'trip_type' => 'one_way']);
        $context->moneySnapshot = ['price_confirmed' => true];
        $store->put($context);

        $response = $this->actingAs($user)->postJson(route('booking.one-api.final-price'), [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
            'client_total' => 999,
            'bundles' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_catalog_and_final_price_fixture_flow(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->oneApiConnection($agency->id);

        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr-test', ['segments' => [['origin' => 'SHJ', 'destination' => 'KHI']], 'trip_type' => 'one_way']);
        $context->moneySnapshot = ['price_confirmed' => true];
        $store->put($context);

        $flow = app(OneApiCheckoutFlowService::class);
        $catalog = $flow->loadCatalog($connection, $context->contextId, [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ], null, null, true);
        $this->assertArrayHasKey('bundles', $catalog);

        $final = $flow->saveSelectionsAndFinalPrice($connection, $context->contextId, [
            'bundles' => [['bunldedServiceId' => 'BUNDLE_FIXTURE_001', 'ond_sequence' => 1]],
        ], ['final_price_fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml')], null, null, true);

        $this->assertTrue($final['final_price_confirmed'] ?? false);
    }

    private function oneApiConnection(int $agencyId): SupplierConnection
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
