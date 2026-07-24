<?php

namespace Tests\Feature\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;
use App\Services\Suppliers\OneApi\Checkout\OneApiCheckoutFlowService;
use App\Services\Suppliers\OneApi\Reservation\OneApiHoldPaymentService;
use App\Services\Suppliers\OneApi\Reservation\OneApiReadRequestBuilder;
use App\Services\Suppliers\OneApi\Reservation\OneApiReservationReadOrchestrator;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Services\Suppliers\SupplierBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiHoldReadPaymentMatrixTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public function test_hold_feature_flag_matrix_blocks_hold_when_disabled(): void
    {
        $connection = $this->connection(['on_hold_enabled' => false]);
        $booking = $this->bookingWithContext($connection);
        $user = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $result = app(OneApiBookingService::class)->createSupplierBooking($booking, $connection, $user, [
            'booking_fulfillment' => 'hold',
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/book_on_hold.xml'),
        ]);
        $this->assertFalse($result->success);
        $this->assertSame('hold_not_enabled', $result->error_code);
    }

    public function test_read_request_contains_vendor_wire_spellings(): void
    {
        $connection = $this->connection();
        $xml = app(OneApiReadRequestBuilder::class)->build($connection, 'PNR_FIXTURE_HOLD_001');
        foreach (['OTA_ReadRQ', 'Type="14"', 'LoadTravelerInfo', 'LoadAirItinery', 'LoadPriceInfoTotals', 'LoadFullFilment'] as $needle) {
            $this->assertStringContainsString($needle, $xml);
        }
    }

    public function test_read_ownership_matrix_denies_cross_agency_actor(): void
    {
        $connection = $this->connection();
        $owner = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $intruder = User::factory()->create();
        $this->expectException(\App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException::class);
        app(OneApiReservationReadOrchestrator::class)->readForActor(
            $intruder,
            $connection,
            'PNR_FIXTURE_HOLD_001',
            Booking::factory()->create(['agency_id' => $connection->agency_id]),
            ['fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/read_on_hold.xml')],
        );
    }

    public function test_read_fixture_parses_payment_and_ticketing_status(): void
    {
        $connection = $this->connection();
        $user = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $read = app(OneApiReservationReadOrchestrator::class)->readForActor(
            $user,
            $connection,
            'PNR_FIXTURE_HOLD_001',
            null,
            ['fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/read_on_hold.xml')],
        );
        $this->assertTrue($read['found']);
        $this->assertSame('OnHold', $read['ticketing_status']);
        $this->assertSame('250.00', $read['payment_amount']);
    }

    public function test_hold_payment_feature_flag_matrix_rejects_when_disabled(): void
    {
        $connection = $this->connection(['hold_payment_enabled' => false]);
        $booking = Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'supplier' => SupplierProvider::OneApi->value,
            'pnr' => 'PNR_FIXTURE_HOLD_001',
        ]);
        $this->assertFalse(app(OneApiHoldPaymentService::class)->canPayHeldReservation($booking, $connection));
    }

    public function test_hold_payment_orchestrator_idempotent_and_requires_flags(): void
    {
        $connection = $this->connection();
        $user = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $booking = Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'supplier' => SupplierProvider::OneApi->value,
            'pnr' => 'PNR_FIXTURE_HOLD_001',
            'meta' => ['supplier_connection_id' => $connection->id],
        ]);
        $fixtures = [
            'fixture_paths' => [
                'read' => base_path('tests/Fixtures/Suppliers/OneApi/read_on_hold.xml'),
                'modify' => base_path('tests/Fixtures/Suppliers/OneApi/hold_payment_modify.xml'),
                'read_after_modify' => base_path('tests/Fixtures/Suppliers/OneApi/read_after_hold_paid.xml'),
            ],
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/hold_payment_modify.xml'),
        ];
        $svc = app(SupplierBookingService::class);
        $first = $svc->payHeldOneApiReservation($booking, $connection, $user, $fixtures);
        $second = $svc->payHeldOneApiReservation($booking->fresh(), $connection, $user, $fixtures);
        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame('already_paid', $second->code);
    }

    public function test_config_resolver_hold_flags(): void
    {
        $connection = $this->connection(['on_hold_enabled' => 'true', 'hold_payment_enabled' => 'yes']);
        $config = app(OneApiConfigResolver::class)->resolve($connection);
        $this->assertTrue($config['on_hold_enabled']);
        $this->assertTrue($config['hold_payment_enabled']);
    }

    private function bookingWithContext(SupplierConnection $connection): Booking
    {
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr-hold-flag', []);
        $flow = app(OneApiCheckoutFlowService::class);
        $flow->loadCatalog($connection, $context->contextId, [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ], null, null, true);
        $flow->saveSelectionsAndFinalPrice($connection, $context->contextId, [], [
            'final_price_fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ], null, null, true);

        return Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'supplier' => SupplierProvider::OneApi->value,
            'meta' => [
                'one_api_context' => ['workflow_context_id' => $context->contextId],
                'supplier_connection_id' => $connection->id,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentialOverrides
     */
    private function connection(array $credentialOverrides = []): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => array_merge([
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
            ], $credentialOverrides),
        ]);
    }
}
