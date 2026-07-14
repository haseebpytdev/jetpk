<?php

namespace Tests\Unit\Services\Suppliers\Sabre;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\BookingTicket;
use App\Models\SupplierConnection;
use App\Models\TicketingAttempt;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreGdsTicketingReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', true);
        Config::set('suppliers.sabre.ticketing_printer_lniata', 'TESTLN');
    }

    public function test_blocks_when_no_pnr(): void
    {
        $booking = $this->sabreBooking(['pnr' => null, 'supplier_reference' => null]);
        $readiness = app(SabreGdsTicketingReadiness::class);
        $result = $readiness->evaluate($booking, ['dry_run' => true]);

        $this->assertContains('missing_pnr_or_locator', $result['blockers']);
    }

    public function test_blocks_when_payment_unpaid(): void
    {
        $booking = $this->sabreBooking(['payment_status' => 'unpaid']);
        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertContains('payment_not_verified', $result['blockers']);
    }

    public function test_blocks_when_itinerary_not_synced(): void
    {
        $booking = $this->sabreBooking([
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'pnr_itinerary_sync' => ['status' => 'pending'],
                'pnr_itinerary_snapshot' => ['segments' => []],
            ],
        ]);
        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertContains('itinerary_not_synced', $result['blockers']);
    }

    public function test_ready_gds_booking_reports_issue_ticket_action(): void
    {
        $booking = $this->readySabreBooking();
        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertSame([], $result['blockers']);
        $this->assertSame(SabreGdsTicketingReadiness::ACTION_ISSUE_TICKET, $result['action_state']);
        $this->assertTrue($result['can_execute']);
        $this->assertTrue($result['itinerary_synced']);
        $this->assertFalse($result['ticketed']);
    }

    public function test_ticketed_booking_reports_ticketed_action(): void
    {
        $booking = $this->sabreBooking(['ticketing_status' => 'ticketed']);
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '0012345678901',
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'issued',
        ]);

        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertSame(SabreGdsTicketingReadiness::ACTION_TICKETED, $result['action_state']);
        $this->assertContains('duplicate_ticketing_guard', $result['blockers']);
    }

    public function test_cancelled_booking_blocked(): void
    {
        $booking = $this->sabreBooking(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);
        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertContains('booking_cancelled', $result['blockers']);
        $this->assertContains('pnr_cancelled_released', $result['blockers']);
        $this->assertSame(SabreGdsTicketingReadiness::ACTION_PNR_CANCELLED_RELEASED, $result['action_state']);
    }

    public function test_non_sabre_provider_blocked(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'duffel',
            'meta' => ['supplier_provider' => 'duffel', 'distribution_channel' => 'gds'],
        ]);

        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);
        $this->assertContains('supplier_not_sabre', $result['blockers']);
    }

    public function test_ndc_channel_blocked_from_gds_ticketing(): void
    {
        $booking = $this->sabreBooking([
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'ndc',
            ],
        ]);

        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertContains('sabre_ndc_channel_use_ndc_services', $result['blockers']);
        $this->assertContains('distribution_channel_not_gds', $result['blockers']);
        $this->assertFalse($result['can_execute']);
        $this->assertFalse($result['live_supplier_call_allowed']);
    }

    public function test_ticketing_in_progress_reports_pending_action(): void
    {
        $booking = $this->sabreBooking([
            'meta' => [
                SabreGdsTicketingReadiness::META_KEY => ['status' => 'in_progress'],
            ],
        ]);
        TicketingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'processing',
            'attempted_at' => now(),
        ]);

        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertSame(SabreGdsTicketingReadiness::ACTION_TICKETING_PENDING, $result['action_state']);
        $this->assertTrue($result['in_progress']);
    }

    public function test_blocks_when_env_disabled(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', false);

        $booking = $this->sabreBooking();
        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertContains('ticketing_disabled_by_env', $result['blockers']);
        $this->assertContains('ticketing_live_call_disabled', $result['blockers']);
        $this->assertFalse($result['live_supplier_call_allowed']);
    }

    public function test_pnr_cancelled_meta_reports_released_action_state(): void
    {
        $booking = $this->sabreBooking([
            'meta' => [
                SabreGdsCancelReadiness::META_KEY => [
                    'status' => 'cancelled',
                    'classification' => 'CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED',
                ],
                'pnr_itinerary_sync' => ['synced' => true, 'status' => 'synced'],
                'pnr_itinerary_snapshot' => [
                    'pnr' => 'ABC123',
                    'segments' => [['segment_status' => 'HX']],
                ],
            ],
        ]);

        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);

        $this->assertSame(SabreGdsTicketingReadiness::ACTION_PNR_CANCELLED_RELEASED, $result['action_state']);
        $this->assertFalse($result['can_execute']);
        $this->assertNotContains('itinerary_not_synced', $result['blockers']);
    }

    public function test_synced_boolean_counts_as_itinerary_synced(): void
    {
        $booking = $this->sabreBooking([
            'meta' => [
                'pnr_itinerary_sync' => ['synced' => true, 'status' => 'partial_resource_unavailable'],
                'pnr_itinerary_snapshot' => [
                    'pnr' => 'ABC123',
                    'segments' => [['segment_status' => 'HK']],
                ],
            ],
        ]);

        $readiness = app(SabreGdsTicketingReadiness::class);
        $this->assertTrue($readiness->isItinerarySynced($booking));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sabreBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->firstOrFail();

        $meta = array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'gds',
            'supplier_connection_id' => $conn->id,
            'customer_total' => 25000,
            'pnr_itinerary_sync' => ['status' => 'synced', 'is_ticketed' => false, 'ticket_numbers_present' => false],
            'pnr_itinerary_snapshot' => ['segments' => [['segment_status' => 'HK']]],
        ], (array) ($overrides['meta'] ?? []));
        unset($overrides['meta']);

        return Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'payment_status' => 'paid',
            'pnr' => 'ABC123',
            'supplier_reference' => 'ABC123',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => $meta,
        ], $overrides));
    }

    private function readySabreBooking(): Booking
    {
        $booking = $this->sabreBooking([
            'status' => BookingStatus::Paid,
            'selected_fare_total' => 25000,
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_type' => 'adult',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_lead_passenger' => true,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'booker@example.com',
            'phone' => '3001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }
}
