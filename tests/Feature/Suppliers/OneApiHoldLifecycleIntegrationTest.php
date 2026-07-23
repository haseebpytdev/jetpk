<?php

namespace Tests\Feature\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;
use App\Services\Suppliers\OneApi\Checkout\OneApiCheckoutFlowService;
use App\Services\Suppliers\OneApi\Reservation\OneApiHoldPaymentService;
use App\Services\Suppliers\OneApi\Reservation\OneApiRetrieveService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiHoldLifecycleIntegrationTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public function test_hold_read_modify_fixture_lifecycle(): void
    {
        $connection = $this->connection();
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr-hold', []);
        $flow = app(OneApiCheckoutFlowService::class);
        $flow->loadCatalog($connection, $context->contextId, [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ], null, null, true);
        $flow->saveSelectionsAndFinalPrice($connection, $context->contextId, [], [
            'final_price_fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ], null, null, true);

        $user = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $booking = Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'supplier' => SupplierProvider::OneApi->value,
            'meta' => ['one_api_context' => ['workflow_context_id' => $context->contextId]],
        ]);

        $book = app(OneApiBookingService::class)->createSupplierBooking($booking, $connection, $user, [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/book_on_hold.xml'),
        ]);
        $this->assertTrue($book->success);
        $this->assertSame('PNR_FIXTURE_HOLD_001', $book->pnr);

        $booking->forceFill(['pnr' => $book->pnr])->save();

        $read = app(OneApiRetrieveService::class)->getReservationByPnr(
            $connection,
            (string) $book->pnr,
            $context->contextId,
            ['fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/read_on_hold.xml')],
        );
        $this->assertNotEmpty($read);

        $modify = app(OneApiHoldPaymentService::class)->payHeldReservation($booking, $connection, [
            'fixture_paths' => [
                'read' => base_path('tests/Fixtures/Suppliers/OneApi/read_on_hold.xml'),
                'modify' => base_path('tests/Fixtures/Suppliers/OneApi/hold_payment_modify.xml'),
            ],
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/hold_payment_modify.xml'),
        ]);
        $this->assertArrayHasKey('raw_xml', $modify);
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
                'on_hold_enabled' => true,
                'hold_payment_enabled' => true,
                'live_payment_modification_enabled' => true,
            ],
        ]);
    }
}
