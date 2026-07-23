<?php

namespace Tests\Feature\Suppliers;

use App\Data\SupplierBookingResultData;
use App\Enums\BookingCommunicationEvent;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Suppliers\OneApi\Checkout\OneApiCheckoutFlowService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Services\Suppliers\SupplierBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiCommunicationIntegrationTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_on_hold_booking_does_not_send_supplier_booking_created(): void
    {
        Mail::fake();
        $booking = $this->bookingWithFinalPrice();
        $result = new SupplierBookingResultData(
            success: true,
            status: 'success',
            provider: SupplierProvider::OneApi->value,
            pnr: 'PNR_FIXTURE_HOLD_001',
            safe_summary: [
                'supplier_booking_status' => 'on_hold',
                'suppress_supplier_booking_created' => true,
                'hold_deadline' => '2026-08-16T18:00:00',
            ],
        );

        $svc = app(SupplierBookingService::class);
        $ref = new \ReflectionMethod($svc, 'dispatchSupplierBookingCommunication');
        $ref->invoke($svc, $booking, $result);

        $this->assertSame(
            0,
            CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
                ->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->where('event', BookingCommunicationEvent::BookingStatusChanged->value)
                ->count()
        );
    }

    public function test_paid_booking_communication_is_idempotent_via_communication_service(): void
    {
        Mail::fake();
        $booking = $this->bookingWithFinalPrice();
        $communication = app(BookingCommunicationService::class);
        $before = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();
        $communication->sendSupplierBookingCreated($booking);
        $mid = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();
        $communication->sendSupplierBookingCreated($booking->fresh());
        $after = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();

        $this->assertGreaterThan($before, $mid);
        $this->assertSame($mid, $after);
    }

    public function test_queue_retry_idempotency_does_not_duplicate_supplier_booking_created(): void
    {
        Mail::fake();
        $booking = $this->bookingWithFinalPrice();
        $result = new SupplierBookingResultData(
            success: true,
            status: 'success',
            provider: SupplierProvider::OneApi->value,
            pnr: 'PNR_COMM_RETRY_001',
            safe_summary: ['supplier_booking_status' => 'ticketed'],
        );
        $svc = app(SupplierBookingService::class);
        $ref = new \ReflectionMethod($svc, 'dispatchSupplierBookingCommunication');
        $ref->invoke($svc, $booking, $result);
        $afterFirst = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();
        $ref->invoke($svc, $booking->fresh(), $result);
        $afterSecond = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();
        $this->assertGreaterThan(0, $afterFirst);
        $this->assertSame($afterFirst, $afterSecond);
    }

    public function test_reconciliation_retry_idempotency_does_not_duplicate_supplier_booking_created(): void
    {
        Mail::fake();
        $booking = $this->bookingWithFinalPrice();
        $communication = app(BookingCommunicationService::class);
        $communication->sendSupplierBookingCreated($booking);
        $afterFirst = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();
        $svc = app(SupplierBookingService::class);
        $ref = new \ReflectionMethod($svc, 'dispatchSupplierBookingCommunication');
        $ref->invoke($svc, $booking->fresh(), new SupplierBookingResultData(
            success: true,
            status: 'success',
            provider: SupplierProvider::OneApi->value,
            pnr: 'PNR_COMM_RETRY_002',
            safe_summary: ['supplier_booking_status' => 'ticketed', 'reconciliation_replay' => true],
        ));
        $afterSecond = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();
        $this->assertGreaterThan(0, $afterFirst);
        $this->assertSame($afterFirst, $afterSecond);
    }

    public function test_failed_modify_does_not_emit_ticket_issued_communication(): void
    {
        Mail::fake();
        $connection = $this->connection();
        $user = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $booking = Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'supplier' => SupplierProvider::OneApi->value,
            'pnr' => 'PNR_FIXTURE_HOLD_001',
            'meta' => ['supplier_connection_id' => $connection->id],
        ]);
        $result = app(SupplierBookingService::class)->payHeldOneApiReservation($booking, $connection, $user, [
            'fixture_paths' => [
                'read' => base_path('tests/Fixtures/Suppliers/OneApi/read_on_hold.xml'),
                'modify' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
            ],
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ]);
        $this->assertFalse($result->success);
        $this->assertSame(
            0,
            CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->where('event', BookingCommunicationEvent::TicketIssued->value)
                ->count()
        );
    }

    public function test_hold_payment_orchestrator_sends_ticket_issued_once(): void
    {
        Mail::fake();
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

        $this->assertTrue($second->success);
        $this->assertTrue($second->success);
        $this->assertSame('already_paid', $second->code);
        $this->assertGreaterThanOrEqual(
            1,
            CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->where('event', BookingCommunicationEvent::TicketIssued->value)
                ->count()
        );
        $this->assertSame(
            0,
            CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
                ->count()
        );
    }

    private function bookingWithFinalPrice(): Booking
    {
        $connection = $this->connection();
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr-comm', []);
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
                'live_payment_modification_enabled' => 'true',
            ],
        ]);
    }
}
