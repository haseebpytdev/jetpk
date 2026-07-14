<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\BookingTicket;
use App\Models\TicketingAttempt;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\TicketingReadinessPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreTicketingCapabilityReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_command_blocked_outside_local_and_testing(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:ticketing-capability-report', ['--booking' => '1']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('only runs when APP_ENV is local or testing', Artisan::output());
        $this->assertFalse(SabreInspectGate::allowed('production'));
    }

    public function test_booking_without_pnr_reports_pnr_absent_and_sync_action(): void
    {
        $booking = $this->sabreBookingBase();

        Artisan::call('sabre:ticketing-capability-report', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $report = $this->decodeJsonOutput(Artisan::output());

        $this->assertFalse($report['booking']['pnr_present']);
        $this->assertSame('sync_pnr_itinerary', $report['recommended_next_action']);
        $this->assertSame(
            TicketingReadinessPresenter::OVERALL_BLOCKED_MISSING_PNR,
            $report['e10_readiness']['overall_status'],
        );
    }

    public function test_pnr_without_snapshot_reports_itinerary_not_synced(): void
    {
        $booking = $this->sabreBookingBase([
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
            ],
        ]);

        Artisan::call('sabre:ticketing-capability-report', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $report = $this->decodeJsonOutput(Artisan::output());

        $this->assertTrue($report['booking']['pnr_present']);
        $this->assertSame(0, $report['pnr_itinerary']['pnr_itinerary_snapshot_segment_count']);
        $this->assertSame('sync_pnr_itinerary', $report['recommended_next_action']);
        $this->assertSame(
            TicketingReadinessPresenter::OVERALL_BLOCKED_ITINERARY_NOT_SYNCED,
            $report['e10_readiness']['overall_status'],
        );
    }

    public function test_hx_segment_reports_not_all_hk_and_resolve_segment_status(): void
    {
        $booking = $this->sabreBookingBase([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        ['segment_status' => 'HX'],
                    ],
                ],
                'pnr_itinerary_sync' => ['status' => 'synced', 'synced_at' => now()->toIso8601String()],
                'customer_total' => 15000,
            ],
        ]);

        Artisan::call('sabre:ticketing-capability-report', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $report = $this->decodeJsonOutput(Artisan::output());

        $this->assertFalse($report['pnr_itinerary']['all_segments_hk']);
        $this->assertContains('HX', $report['pnr_itinerary']['segment_statuses_sanitized']);
        $this->assertSame('resolve_segment_status', $report['recommended_next_action']);
    }

    public function test_synced_hk_and_paid_reports_ready_except_adapter_disabled(): void
    {
        $booking = $this->sabreBookingBase([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'balance_due' => 0,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        ['segment_status' => 'HK'],
                    ],
                ],
                'pnr_itinerary_sync' => ['status' => 'synced', 'synced_at' => now()->toIso8601String()],
                'customer_total' => 15000,
                'passenger_pricing' => [['type' => 'adult']],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 12000,
            'taxes' => 3000,
            'total' => 15000,
            'currency' => 'PKR',
        ]);

        Artisan::call('sabre:ticketing-capability-report', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $report = $this->decodeJsonOutput(Artisan::output());

        $this->assertTrue($report['pnr_itinerary']['all_segments_hk']);
        $this->assertSame(
            TicketingReadinessPresenter::OVERALL_READY_EXCEPT_TICKETING_DISABLED,
            $report['e10_readiness']['overall_status'],
        );
        $this->assertFalse($report['e10_readiness']['can_attempt_live_ticketing']);
        $this->assertFalse($report['adapter']['adapter_supported']);
        $this->assertFalse($report['config']['sabre_ticketing_enabled']);
        $this->assertSame('adapter_not_implemented', $report['recommended_next_action']);
    }

    public function test_ticketing_records_counts_and_latest_attempt_safely(): void
    {
        $booking = $this->sabreBookingBase(['pnr' => 'PNR1']);

        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '999-0000000001',
            'pnr' => 'PNR1',
            'provider' => SupplierProvider::Sabre->value,
            'airline_code' => 'PK',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        TicketingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'not_supported',
            'error_code' => 'api_docs_required',
            'error_message' => 'Sabre ticketing is not implemented.',
            'request_payload' => ['Authorization' => 'Bearer secret-token', 'passport' => 'AB1234567'],
            'response_payload' => ['raw' => 'must-not-appear'],
            'safe_summary' => ['reason' => 'api_docs_required'],
            'attempted_at' => now()->subMinute(),
        ]);

        TicketingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'failed',
            'error_code' => 'later_attempt',
            'attempted_at' => now(),
        ]);

        Artisan::call('sabre:ticketing-capability-report', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $report = $this->decodeJsonOutput(Artisan::output());
        $encoded = json_encode($report);

        $this->assertSame(1, $report['ticketing_records']['booking_tickets_count']);
        $this->assertSame('failed', $report['ticketing_records']['latest_ticketing_attempt']['status']);
        $this->assertSame('later_attempt', $report['ticketing_records']['latest_ticketing_attempt']['error_code']);
        $this->assertArrayNotHasKey('request_payload', $report['ticketing_records']['latest_ticketing_attempt'] ?? []);
        $this->assertStringNotContainsString('secret-token', $encoded);
        $this->assertStringNotContainsString('AB1234567', $encoded);
        $this->assertStringNotContainsString('must-not-appear', $encoded);
    }

    public function test_json_output_has_no_pii_or_raw_payload_keys(): void
    {
        $booking = $this->sabreBookingBase([
            'pnr' => 'SEC123',
            'booking_reference' => 'OTA-REF-001',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'contact_email' => 'secret@example.test',
            ],
        ]);

        Artisan::call('sabre:ticketing-capability-report', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $out = Artisan::output();
        $this->assertStringStartsWith('sabre_ticketing_capability_report_json=', $out);

        $report = $this->decodeJsonOutput($out);
        $encoded = json_encode($report);

        $this->assertArrayNotHasKey('pnr', $report['booking']);
        $this->assertStringNotContainsString('SEC123', $encoded);
        $this->assertStringNotContainsString('secret@example.test', $encoded);
        $this->assertStringNotContainsString('request_payload', $encoded);
        $this->assertStringNotContainsString('response_payload', $encoded);
        $this->assertStringNotContainsString('Authorization', $encoded);
    }

    public function test_non_sabre_booking_returns_unsupported_report(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => ['supplier_provider' => SupplierProvider::Duffel->value],
        ]);

        Artisan::call('sabre:ticketing-capability-report', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $report = $this->decodeJsonOutput(Artisan::output());

        $this->assertFalse($report['supported']);
        $this->assertSame('manual_ticketing_only', $report['recommended_next_action']);
        $this->assertArrayNotHasKey('e10_readiness', $report);
    }

    public function test_config_reports_ticketing_live_call_configured_with_default_false(): void
    {
        $booking = $this->sabreBookingBase(['pnr' => 'X1']);

        Artisan::call('sabre:ticketing-capability-report', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $report = $this->decodeJsonOutput(Artisan::output());

        $this->assertFalse($report['config']['sabre_ticketing_live_call_enabled']);
        $this->assertSame('configured', $report['config']['sabre_ticketing_live_call_source']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function sabreBookingBase(array $overrides = []): Booking
    {
        $meta = array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
        ], (array) ($overrides['meta'] ?? []));
        unset($overrides['meta']);

        $booking = Booking::factory()->create(array_merge([
            'supplier' => SupplierProvider::Sabre->value,
            'payment_status' => 'unpaid',
            'meta' => $meta,
        ], $overrides));

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'customer', 'fareBreakdown', 'tickets', 'latestTicketingAttempt']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonOutput(string $output): array
    {
        if (! preg_match('/sabre_ticketing_capability_report_json=(.+)/s', trim($output), $matches)) {
            $this->fail('Expected sabre_ticketing_capability_report_json= line in output: '.$output);
        }

        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
