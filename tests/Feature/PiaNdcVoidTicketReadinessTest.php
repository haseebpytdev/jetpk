<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcOrderOperationPreflight;
use App\Services\Suppliers\PiaNdc\PiaNdcResponseNormalizer;
use App\Services\Suppliers\PiaNdc\PiaNdcVoidTicketService;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlParser;
use App\Support\Bookings\AdminPiaNdcReleaseOptionPnrPresenter;
use App\Support\Bookings\AdminPiaNdcStatusRefreshPresenter;
use App\Support\Bookings\PiaNdcBookingStatusInterpreter;
use App\Support\Suppliers\PiaNdcSupplierConnectionNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PiaNdcVoidTicketReadinessTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_void_fixture_ticket_number_with_coupon_v_is_not_blocking(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doVoidTicket_res.xml')) ?: '';
        $parsed = (new PiaNdcXmlParser)->parse($xml);
        $tickets = $parsed['parsed']['ticket_doc_infos'] ?? [];

        $this->assertNotEmpty($tickets);
        $this->assertSame('2142417439146', $tickets[0]['ticket_number'] ?? null);
        $this->assertSame(['V'], $tickets[0]['coupon_status_codes'] ?? null);

        $normalizer = new PiaNdcResponseNormalizer;
        $this->assertFalse($normalizer->hasBlockingTicketNumbers($tickets));

        $normalized = $normalizer->normalizeVoidResponse($parsed);
        $this->assertSame('voided', $normalized['void_status'] ?? null);
        $this->assertFalse($normalized['has_blocking_ticket_numbers'] ?? true);

        $interpreted = PiaNdcBookingStatusInterpreter::interpret([
            'segment_count' => 1,
            'payment_time_limit' => '2025-05-27T19:10:43+05:00',
            'ticket_numbers' => ['2142417439146'],
            'has_blocking_ticket_numbers' => false,
            'order_status' => 'OPENED',
        ]);

        $this->assertSame(PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID, $interpreted['interpreted_status']);
        $this->assertFalse($interpreted['ticketed']);
        $this->assertTrue($interpreted['has_ticket_numbers']);
        $this->assertTrue($interpreted['active_option_pnr']);
    }

    public function test_void_success_updates_local_booking_as_option_pnr_after_void(): void
    {
        $this->platformAdmin();
        [$booking, $connection] = $this->piaBooking([
            'ticket_numbers' => ['2142417439146'],
            'ticketing_status' => 'ticketed',
            'ticket_doc_infos' => [[
                'ticket_number' => '2142417439146',
                'coupon_status_codes' => ['O'],
            ]],
        ]);

        $ticketedRetrieveXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderChange_OW_res.xml')) ?: '';
        $voidXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doVoidTicket_res.xml')) ?: '';
        $retrieveCalls = 0;

        Http::fake(function ($request) use (&$retrieveCalls, $ticketedRetrieveXml, $voidXml) {
            $body = (string) $request->body();
            if (str_contains($body, 'OrderRetrieve')) {
                $retrieveCalls++;

                return Http::response(
                    $retrieveCalls === 1 ? $ticketedRetrieveXml : $voidXml,
                    200,
                    ['Content-Type' => 'text/xml; charset=utf-8'],
                );
            }

            return Http::response($voidXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        });

        app(PiaNdcVoidTicketService::class)->voidTicket($booking, $connection);
        $booking->refresh();

        $this->assertSame('option_pnr_after_void', $booking->supplier_booking_status);
        $this->assertSame('voided', $booking->ticketing_status);
        $booking->load('tickets');
        $this->assertNotEmpty($booking->tickets);
        $this->assertSame('voided', $booking->tickets->first()?->status);
        $this->assertNotNull($booking->tickets->first()?->voided_at);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $this->assertSame('voided', $context['void_status'] ?? null);
        $this->assertSame('voided', $context['ticketing_status'] ?? null);
        $this->assertSame(PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID, $context['interpreted_status'] ?? null);
        $this->assertContains('2142417439146', $context['ticket_numbers'] ?? []);
        $this->assertFalse($context['option_pnr_released'] ?? true);
        $this->assertFalse($context['has_blocking_ticket_numbers'] ?? true);
    }

    public function test_release_allowed_after_void_when_segments_remain(): void
    {
        $connection = $this->piaConnection();
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'supplier_reference' => '7UU0J9',
            'supplier_booking_status' => 'option_pnr_after_void',
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => [
                    'order_id' => '7UU0J9',
                    'owner_code' => 'PK',
                    'void_status' => 'voided',
                    'ticketing_status' => 'voided',
                    'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID,
                    'segment_count' => 1,
                    'ticket_numbers' => ['2142417439146'],
                    'ticket_doc_infos' => [[
                        'ticket_number' => '2142417439146',
                        'coupon_status_codes' => ['V'],
                    ]],
                    'has_blocking_ticket_numbers' => false,
                ],
            ],
        ]);

        $panel = app(AdminPiaNdcReleaseOptionPnrPresenter::class)->panel($booking->fresh());
        $this->assertTrue($panel['show']);
        $this->assertTrue($panel['can_release']);
        $this->assertFalse($panel['option_pnr_released']);
    }

    public function test_ticketing_preflight_real_ticket_numbers_present_method_exists(): void
    {
        $preflight = app(PiaNdcOrderOperationPreflight::class);
        $this->assertTrue($preflight->realTicketNumbersPresent([
            'ticket_doc_infos' => [[
                'ticket_number' => '2142417439146',
                'coupon_status_codes' => ['O'],
            ]],
        ]));
        $this->assertFalse($preflight->realTicketNumbersPresent([
            'ticket_doc_infos' => [[
                'ticket_number' => '2142417439146',
                'coupon_status_codes' => ['V'],
            ]],
        ]));
    }

    public function test_admin_status_presenter_renders_has_ticket_numbers_without_error(): void
    {
        $connection = $this->piaConnection();
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'supplier_reference' => '7UU0J9',
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => [
                    'order_id' => '7UU0J9',
                    'owner_code' => 'PK',
                    'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID,
                    'has_ticket_numbers' => true,
                    'segment_count' => 1,
                ],
            ],
        ]);

        $panel = app(AdminPiaNdcStatusRefreshPresenter::class)->panel($booking);
        $this->assertTrue($panel['show']);
        $this->assertSame('Yes', $panel['has_ticket_numbers']);
    }

    public function test_pia_payment_type_defaults_to_mco(): void
    {
        $payload = PiaNdcSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::PiaNdc->value,
            'environment' => 'sandbox',
            'credentials' => [
                'username' => 'user',
                'password' => 'pass',
                'agency_id' => 'AG',
                'agency_name' => 'Test Agency',
                'owner_code' => 'PK',
            ],
        ]);

        $this->assertSame('MCO', $payload['credentials']['payment_type'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $contextOverrides
     * @return array{0: Booking, 1: SupplierConnection}
     */
    private function piaBooking(array $contextOverrides = []): array
    {
        $connection = SupplierConnection::factory()->create([
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
                'mco_invoice_number' => 'MCO-TEST-001',
                'payment_type' => 'MCO',
            ],
            'is_active' => true,
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'supplier_reference' => '7UU0J9',
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => array_merge([
                    'order_id' => '7UU0J9',
                    'owner_code' => 'PK',
                ], $contextOverrides),
            ],
        ]);

        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'supplier_reference' => '7UU0J9',
            'pnr' => '7UU0J9',
            'status' => 'ticketed',
            'raw_summary' => ['seeded' => true],
            'created_at_supplier' => now(),
        ]);

        return [$booking, $connection];
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
}
