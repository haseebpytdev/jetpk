<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\PaymentGateway;
use App\Models\SupplierConnection;
use App\Services\Payments\PaymentTransactionService;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingStatusRefreshService;
use App\Support\Bookings\AdminPiaNdcReleaseOptionPnrPresenter;
use App\Support\Bookings\PiaNdcBookingStatusInterpreter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcStatusRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_interpreter_marks_released_when_no_segments_no_ttl_no_tickets(): void
    {
        $interpreted = PiaNdcBookingStatusInterpreter::interpret([
            'segment_count' => 0,
            'payment_time_limit' => null,
            'ticket_numbers' => [],
            'order_status' => 'CLOSED',
        ]);

        $this->assertTrue($interpreted['released']);
        $this->assertSame(PiaNdcBookingStatusInterpreter::STATUS_RELEASED, $interpreted['interpreted_status']);
    }

    public function test_interpreter_marks_active_option_pnr_when_segments_and_ttl_exist(): void
    {
        $interpreted = PiaNdcBookingStatusInterpreter::interpret([
            'segment_count' => 1,
            'payment_time_limit' => now()->addHours(2)->toIso8601String(),
            'ticket_numbers' => [],
            'order_status' => 'OPENED',
        ]);

        $this->assertTrue($interpreted['active_option_pnr']);
        $this->assertFalse($interpreted['released']);
        $this->assertSame(PiaNdcBookingStatusInterpreter::STATUS_ACTIVE_OPTION_PNR, $interpreted['interpreted_status']);
    }

    public function test_interpreter_marks_option_pnr_after_void_when_ticket_numbers_are_voided(): void
    {
        $interpreted = PiaNdcBookingStatusInterpreter::interpret([
            'segment_count' => 1,
            'payment_time_limit' => now()->addHours(2)->toIso8601String(),
            'ticket_numbers' => ['2142417439146'],
            'has_blocking_ticket_numbers' => false,
            'order_status' => 'OPENED',
        ]);

        $this->assertSame(PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID, $interpreted['interpreted_status']);
        $this->assertFalse($interpreted['ticketed']);
        $this->assertTrue($interpreted['has_ticket_numbers']);
        $this->assertTrue($interpreted['active_option_pnr']);
        $this->assertFalse($interpreted['released']);
    }

    public function test_refresh_marks_local_booking_released_when_retrieve_returns_empty_order(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->piaBooking($connection, [
            'supplier_booking_status' => 'option_pnr_created',
        ]);

        Http::fake([
            'example.test/*' => Http::response($this->closedRetrieveXml('9FD3SK'), 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $service = app(PiaNdcBookingStatusRefreshService::class);
        $result = $service->refreshBooking($booking, null, 'test');

        $this->assertTrue($result['success']);
        $booking->refresh();
        $this->assertSame('released', $booking->supplier_booking_status);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $this->assertTrue($context['option_pnr_released'] ?? false);
        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'action' => PiaNdcBookingStatusRefreshService::ACTION,
            'status' => 'success',
        ]);
    }

    public function test_refresh_marks_active_when_retrieve_returns_open_order(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->piaBooking($connection);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        app(PiaNdcBookingStatusRefreshService::class)->refreshBooking($booking, null, 'test');
        $booking->refresh();

        $this->assertSame('option_pnr_created', $booking->supplier_booking_status);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $this->assertSame(PiaNdcBookingStatusInterpreter::STATUS_ACTIVE_OPTION_PNR, $context['interpreted_status'] ?? null);
    }

    public function test_admin_release_hidden_after_refresh_marks_released(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->piaBooking($connection);

        app(PiaNdcBookingStatusRefreshService::class)->applyLocalReconciliation(
            booking: $booking,
            connection: $connection,
            normalized: [
                'order_id' => '9FD3SK',
                'segment_count' => 0,
                'payment_time_limit' => null,
                'ticket_numbers' => [],
                'order_status' => 'CLOSED',
            ],
            interpreted: PiaNdcBookingStatusInterpreter::interpret([
                'segment_count' => 0,
                'payment_time_limit' => null,
                'ticket_numbers' => [],
                'order_status' => 'CLOSED',
            ]),
            actor: null,
            source: 'test',
        );

        $panel = app(AdminPiaNdcReleaseOptionPnrPresenter::class)->panel($booking->fresh());
        $this->assertTrue($panel['show']);
        $this->assertFalse($panel['can_release']);
        $this->assertTrue($panel['option_pnr_released']);
    }

    public function test_abhipay_blocked_for_released_pia_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->configureAbhiPayGateway($agency);
        $connection = $this->piaConnection();

        $booking = Booking::factory()->for($agency)->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'pnr' => '9FD3SK',
            'supplier_reference' => '9FD3SK',
            'supplier_booking_status' => 'released',
            'payment_status' => 'unpaid',
            'status' => BookingStatus::Pending,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => [
                    'order_id' => '9FD3SK',
                    'owner_code' => 'PK',
                    'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_RELEASED,
                    'option_pnr_released' => true,
                    'segment_count' => 0,
                ],
                'pia_ndc_last_status_refresh' => [
                    'checked_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 24410,
            'taxes' => 0,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 24410,
            'currency' => 'PKR',
        ]);

        $service = app(PaymentTransactionService::class);
        $this->assertFalse($service->canStartAbhiPayForBooking($booking->fresh('fareBreakdown')));
        $this->assertSame(
            'Airline reservation must be active before online payment.',
            $service->abhiPayStartBlockedMessage($booking->fresh('fareBreakdown')),
        );
    }

    public function test_cli_release_updates_local_booking_when_booking_reference_provided(): void
    {
        $connection = $this->piaConnection();
        $booking = $this->piaBooking($connection, [
            'booking_reference' => 'AMGE23KH',
            'supplier_booking_status' => 'option_pnr_created',
        ]);

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelPreview_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push($this->closedRetrieveXml('9FD3SK'), 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--booking-reference' => 'AMGE23KH',
            '--execute-release' => true,
            '--confirm' => 'RELEASE_PIA_OPTION_PNR',
            '--reason' => 'cli reconcile test',
        ])->assertSuccessful();

        $booking->refresh();
        $this->assertSame('released', $booking->supplier_booking_status);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $this->assertTrue($context['option_pnr_released'] ?? false);
    }

    public function test_cli_release_does_not_auto_update_ambiguous_pnrs(): void
    {
        $connection = $this->piaConnection();
        File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/release-option-pnr-locks'));
        $this->piaBooking($connection, ['pnr' => '9FCSLN', 'supplier_reference' => '9FCSLN', 'meta' => [
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'supplier_connection_id' => $connection->id,
            'pia_ndc_context' => ['order_id' => '9FCSLN', 'owner_code' => 'PK'],
        ]]);
        $this->piaBooking($connection, ['pnr' => '9FCSLN', 'supplier_reference' => '9FCSLN', 'meta' => [
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'supplier_connection_id' => $connection->id,
            'pia_ndc_context' => ['order_id' => '9FCSLN', 'owner_code' => 'PK'],
        ]]);

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelPreview_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--execute-release' => true,
            '--confirm' => 'RELEASE_PIA_OPTION_PNR',
            '--reason' => 'ambiguous pnr test',
        ])
            ->expectsOutputToContain('Multiple local bookings share PNR/order 9FCSLN')
            ->assertSuccessful();
    }

    private function piaConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::PiaNdc,
            'base_url' => 'https://example.test/cranendc/v20.1/CraneNDCService',
            'credentials' => [
                'username' => 'test-user',
                'password' => 'test-pass',
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'owner_code' => 'PK',
                'currency' => 'PKR',
                'language_code' => 'EN',
            ],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function piaBooking(SupplierConnection $connection, array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'supplier' => SupplierProvider::PiaNdc->value,
            'pnr' => '9FD3SK',
            'supplier_reference' => '9FD3SK',
            'supplier_booking_status' => 'option_pnr_created',
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => [
                    'order_id' => '9FD3SK',
                    'owner_code' => 'PK',
                ],
            ],
        ], $overrides));
    }

    private function configureAbhiPayGateway(Agency $agency): PaymentGateway
    {
        return PaymentGateway::query()->create([
            'agency_id' => $agency->id,
            'code' => PaymentGateway::CODE_ABHIPAY,
            'name' => 'AbhiPay',
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-123',
            'merchant_secret_key' => 'secret-key-test-value',
            'base_url' => 'https://api.abhipay.com.pk/api/v3',
            'callback_url' => route('payments.abhipay.callback'),
        ]);
    }

    private function closedRetrieveXml(string $orderId): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<S:Body>'
            .'<ns3:IATA_OrderViewRS xmlns:ns3="http://www.iata.org/IATA/2015/00/2020.1/IATA_OrderViewRS">'
            .'<ns3:Response>'
            .'<ns3:DataLists><ns3:PaxList></ns3:PaxList><ns3:PaxSegmentList></ns3:PaxSegmentList></ns3:DataLists>'
            .'<ns3:Order><ns3:OrderID>'.$orderId.'</ns3:OrderID><ns3:OwnerCode>PK</ns3:OwnerCode><ns3:StatusCode>CLOSED</ns3:StatusCode></ns3:Order>'
            .'</ns3:Response>'
            .'</ns3:IATA_OrderViewRS>'
            .'</S:Body>'
            .'</S:Envelope>';
    }
}
