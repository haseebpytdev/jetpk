<?php

namespace Tests\Feature\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;
use App\Services\Suppliers\OneApi\Checkout\OneApiCheckoutFlowService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiPaidBookingIntegrationTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public function test_fixture_paid_booking_through_service_parses_pnr(): void
    {
        $connection = $this->connection();
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr', []);
        $flow = app(OneApiCheckoutFlowService::class);
        $catalog = $flow->loadCatalog($connection, $context->contextId, [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ], null, null, true);
        $bundleId = $catalog['bundles'][0]['selection_id'] ?? null;
        $this->assertNotNull($bundleId);
        $flow->saveSelectionsAndFinalPrice($connection, $context->contextId, [
            'bundle_selection_ids' => [$bundleId],
        ], ['final_price_fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml')], null, null, true);

        $user = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $booking = Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'supplier' => SupplierProvider::OneApi->value,
            'meta' => [
                'one_api_context' => ['workflow_context_id' => $context->contextId],
                'supplier_connection_id' => $connection->id,
            ],
        ]);

        $result = app(OneApiBookingService::class)->createSupplierBooking($booking, $connection, $user, [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/book_paid.xml'),
        ]);
        $this->assertTrue($result->success);
        $this->assertSame('PNR_FIXTURE_001', $result->pnr);
    }

    private function connection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
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
