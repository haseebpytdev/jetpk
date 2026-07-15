<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Support\Bookings\SabreControlledPnrFareChangeAcceptance;
use App\Support\Bookings\SabreControlledPnrReadiness;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreAcceptControlledPnrFareChangeCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);
        Http::fake();
    }

    public function test_dry_run_does_not_mutate_db(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate($this->approvalMetaForBooking());
        $metaBefore = $booking->meta;

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--reason' => 'ops_review',
            '--accepted-by' => 'ops@test',
        ]);

        $output = Artisan::output();
        $booking->refresh();
        $this->assertSame($metaBefore, $booking->meta);
        $this->assertStringContainsString('classification=acceptance_dry_run_only', $output);
        $this->assertStringContainsString('db_mutation_attempted=false', $output);
        $this->assertStringNotContainsString('raw_payload', strtolower($output));
        Http::assertNothingSent();
    }

    public function test_without_exact_confirm_does_not_mutate_db(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate($this->approvalMetaForBooking());

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--reason' => 'ops_review',
            '--accepted-by' => 'ops@test',
        ]);

        $booking->refresh();
        $this->assertNull(app(SabreControlledPnrFareChangeAcceptance::class)->extractRecord(
            is_array($booking->meta) ? $booking->meta : []
        ));
        $this->assertFalse((bool) data_get($booking->meta, SabreOfferRefreshAcceptance::META_ACCEPTED));
        $this->assertStringContainsString('classification=acceptance_blocked_missing_confirmation', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_exact_confirm_writes_only_safe_meta_and_offer_refresh_accepted_marker(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate($this->approvalMetaForBooking());
        $metaBefore = is_array($booking->meta) ? $booking->meta : [];

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
            '--reason' => 'GF fare retry',
            '--accepted-by' => 'platform_ops',
        ]);

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $record = app(SabreControlledPnrFareChangeAcceptance::class)->extractRecord($meta);

        $this->assertNotNull($record);
        $this->assertTrue($record['accepted'] ?? false);
        $this->assertSame('controlled_pnr_create_retry', $record['accepted_for'] ?? '');
        $this->assertTrue((bool) ($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false));
        $this->assertTrue((bool) ($meta['offer_refresh_price_changed'] ?? false));
        $this->assertTrue((bool) ($meta['offer_refresh_requires_customer_confirmation'] ?? false));
        $this->assertTrue((bool) ($meta['defer_supplier_booking_to_manual_review'] ?? false) === (bool) ($metaBefore['defer_supplier_booking_to_manual_review'] ?? false)
            || ! isset($metaBefore['defer_supplier_booking_to_manual_review']));
        $this->assertArrayNotHasKey('raw_payload', $record);
        $this->assertStringContainsString('classification=acceptance_written', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_refuses_existing_pnr(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate($this->approvalMetaForBooking());
        $booking->forceFill(['pnr' => 'ABC123'])->save();

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('acceptance_blocked_ineligible', $output);
        $this->assertStringContainsString('existing_pnr_present', $output);
    }

    public function test_refuses_ticketed_booking(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate($this->approvalMetaForBooking());
        $booking->forceFill(['status' => BookingStatus::Ticketed])->save();

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertStringContainsString('ticketed_booking_blocked', Artisan::output());
    }

    public function test_refuses_cancelled_booking(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate($this->approvalMetaForBooking());
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertStringContainsString('cancelled_booking_blocked', Artisan::output());
    }

    public function test_refuses_when_f9c_manual_review_approval_missing(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate();

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertStringContainsString('controlled_pnr_manual_review_not_approved', Artisan::output());
    }

    public function test_refuses_when_no_fare_change_gate_exists(): void
    {
        $booking = $this->booking53Style($this->approvalMetaForBooking());

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertStringContainsString('fare_change_gate_not_active', Artisan::output());
    }

    public function test_readiness_before_f9e_blocks_fare_confirmation(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
            ],
        ));

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['controlled_pnr_fare_change_accepted']);
        $this->assertContains('offer_refresh_customer_confirmation_required', $result['blockers']);
        $this->assertContains('price_change_confirmation_required', $result['blockers']);
        $this->assertFalse($result['can_attempt_supplier_pnr']);
    }

    public function test_readiness_after_f9e_removes_fare_blockers_and_can_attempt(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
            ],
        ));

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
            '--reason' => 'GF fare retry',
            '--accepted-by' => 'platform_ops',
        ]);

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets']));

        $this->assertTrue($result['controlled_pnr_fare_change_accepted']);
        $this->assertTrue($result['controlled_pnr_manual_review_approved']);
        $this->assertNotContains('offer_refresh_customer_confirmation_required', $result['blockers']);
        $this->assertNotContains('price_change_confirmation_required', $result['blockers']);
        $this->assertTrue($result['can_attempt_supplier_pnr']);
        $this->assertContains('controlled_fare_change_accepted', $result['warnings']);
    }

    public function test_controlled_create_dry_run_after_f9e_shows_exact_confirmation_required(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
            ],
        ));

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
            '--reason' => 'GF fare retry',
            '--accepted-by' => 'platform_ops',
        ]);

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('controlled_pnr_fare_change_accepted=true', $output);
        $this->assertStringContainsString('historical_offer_refresh_price_changed=true', $output);
        $this->assertStringContainsString('historical_offer_refresh_requires_customer_confirmation=true', $output);
        $this->assertStringContainsString('exact_create_confirmation_required=true', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_mutation_flags_remain_disabled_after_acceptance(): void
    {
        $booking = $this->booking53StyleWithFareChangeGate($this->approvalMetaForBooking());

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets']));

        $this->assertTrue($result['ticketing_disabled']);
        $this->assertTrue($result['cancellation_disabled']);
        $this->assertFalse($result['mutation_flags_snapshot']['ticketing_enabled']);
        $this->assertFalse($result['mutation_flags_snapshot']['cancel_enabled']);
        $this->assertFalse($result['mutation_flags_snapshot']['verified_multiseg_auto_pnr_enabled']);
        $this->assertFalse($result['mutation_flags_snapshot']['cpnr_connecting_same_carrier_public_checkout_enabled']);
    }
}
