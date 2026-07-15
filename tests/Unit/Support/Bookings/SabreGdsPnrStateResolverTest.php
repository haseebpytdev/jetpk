<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\SabreGdsPnrCancellationStateResolver;
use App\Support\Bookings\SabreGdsPnrItinerarySyncResolver;
use App\Support\Bookings\TicketingReadinessPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreGdsPnrStateResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', true);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);
    }

    public function test_sync_resolver_true_when_synced_boolean_set(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'GLYTXF',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'pnr_itinerary_sync' => ['synced' => true, 'status' => 'partial_resource_unavailable'],
            ],
        ]);

        $this->assertTrue(app(SabreGdsPnrItinerarySyncResolver::class)->isSynced($booking));
    }

    public function test_sync_resolver_true_when_valid_snapshot_matches_current_pnr(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'GLYTXF',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'pnr_itinerary_sync' => ['status' => 'pending'],
                'pnr_itinerary_snapshot' => [
                    'pnr' => 'GLYTXF',
                    'segments' => [['segment_status' => 'HK']],
                ],
            ],
        ]);

        $this->assertTrue(app(SabreGdsPnrItinerarySyncResolver::class)->isSynced($booking));
    }

    public function test_ticketing_readiness_does_not_block_itinerary_when_canonical_sync_true(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'GLYTXF',
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'customer_total' => 25000,
                'pnr_itinerary_sync' => ['synced' => true],
                'pnr_itinerary_snapshot' => [
                    'pnr' => 'GLYTXF',
                    'segments' => [['segment_status' => 'HK']],
                ],
            ],
        ]);

        $readiness = app(SabreGdsTicketingReadiness::class)->evaluate($booking, [
            'dry_run' => true,
            'skip_e10_presenter' => true,
        ]);

        $this->assertNotContains('itinerary_not_synced', $readiness['blockers']);
        $this->assertTrue($readiness['itinerary_synced']);

        $presenter = TicketingReadinessPresenter::forBooking($booking);
        $this->assertNotSame(
            TicketingReadinessPresenter::OVERALL_BLOCKED_ITINERARY_NOT_SYNCED,
            $presenter['overall_status'],
        );
        $this->assertStringNotContainsString(
            'PNR itinerary not synced',
            $presenter['overall_label'],
        );
    }

    public function test_cancelled_pnr_suppresses_issue_ticket_and_release_actions(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'GLYTXF',
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'pnr_itinerary_sync' => ['status' => 'synced', 'synced' => true],
                'pnr_itinerary_snapshot' => [
                    'pnr' => 'GLYTXF',
                    'segments' => [['segment_status' => 'HX']],
                ],
                SabreGdsCancelReadiness::META_KEY => [
                    'status' => 'cancelled',
                    'classification' => 'CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED',
                ],
            ],
        ]);

        $ticketing = app(SabreGdsTicketingReadiness::class)->evaluate($booking, ['dry_run' => true]);
        $this->assertSame(SabreGdsTicketingReadiness::ACTION_PNR_CANCELLED_RELEASED, $ticketing['action_state']);
        $this->assertFalse($ticketing['can_execute']);
        $this->assertStringContainsString('released/cancelled', $ticketing['admin_message']);

        $cancel = app(SabreGdsCancelReadiness::class)->evaluate($booking);
        $this->assertSame(SabreGdsCancelReadiness::ACTION_CANCELLED, $cancel['action_state']);
        $this->assertFalse($cancel['can_execute']);

        $actions = app(AdminBookingSupplierActions::class)->build($booking, false, false);
        $this->assertFalse($actions['can_issue_ticket_action']);
        $this->assertFalse($actions['can_release_sabre_gds_pnr']);
        $this->assertTrue($actions['sabre_gds_pnr_cancelled_or_released']);
        $this->assertStringContainsString('refund/credit', (string) $actions['sabre_gds_manual_close_message']);
    }

    public function test_active_unticketed_sabre_gds_pnr_can_show_release_pnr_action(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'pnr_itinerary_sync' => ['status' => 'synced'],
                'pnr_itinerary_snapshot' => [
                    'pnr' => 'ABC123',
                    'segments' => [['segment_status' => 'HK']],
                ],
            ],
        ]);

        $actions = app(AdminBookingSupplierActions::class)->build($booking, false, false);
        $this->assertTrue($actions['can_release_sabre_gds_pnr']);
        $this->assertSame('Release PNR', $actions['release_sabre_gds_pnr_label']);
        $this->assertSame('RELEASE-PNR-FOR-BOOKING-'.$booking->id, $actions['release_sabre_gds_pnr_confirm_phrase']);
    }

    public function test_cancel_resolver_detects_successful_cancel_attempt(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
            ],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_booking',
            'status' => 'success',
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $this->assertTrue(app(SabreGdsPnrCancellationStateResolver::class)->isPnrCancelledOrReleased($booking));
    }

    public function test_public_checkout_auto_ticketing_remains_off(): void
    {
        $this->assertFalse((bool) config('suppliers.sabre.public_ticketing_enabled', false));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function gdsBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $meta = array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'gds',
        ], (array) ($overrides['meta'] ?? []));
        unset($overrides['meta']);

        return Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => $meta,
        ], $overrides));
    }
}
