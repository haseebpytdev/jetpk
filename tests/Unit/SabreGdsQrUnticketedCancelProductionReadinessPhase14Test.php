<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedCancelIdentityResolver;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedCancelLifecycle;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedSupplierCreateAttemptRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SabreGdsQrUnticketedCancelProductionReadinessPhase14Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['suppliers.sabre.ticketing_enabled' => false]);
    }

    public function test_command_is_registered(): void
    {
        $booking = $this->seedMatchingSabreBooking('REGTEST1');
        $this->assertSame(0, Artisan::call('sabre:gds-qr-unticketed-cancel', [
            '--booking-id' => $booking->id,
            '--plan' => true,
        ]));
    }

    public function test_plan_mode_zero_db_mutation(): void
    {
        $booking = $this->seedMatchingSabreBooking('ABC123');
        $attemptsBefore = SupplierBookingAttempt::query()->count();
        Artisan::call('sabre:gds-qr-unticketed-cancel', [
            '--booking-id' => $booking->id,
            '--plan' => true,
        ]);
        $this->assertSame($attemptsBefore, SupplierBookingAttempt::query()->count());
        $output = Artisan::output();
        $this->assertStringContainsString('cancellation_planned=true', $output);
        $this->assertStringContainsString('maximum_cancellation_calls=1', $output);
        $this->assertStringContainsString('post_cancel_retrieve_planned=false', $output);
    }

    public function test_identity_resolver_accepts_matching_supplier_locator(): void
    {
        $booking = $this->seedMatchingSabreBooking('WL96PKN9');
        $identity = app(SabreGdsQrUnticketedCancelIdentityResolver::class)->resolve($booking, null);
        $this->assertTrue($identity['identity_checks_passed']);
        $this->assertTrue($identity['locator_present']);
        $this->assertTrue($identity['locator_matches']);
        $this->assertSame(0, $identity['ticket_number_count']);
    }

    public function test_locator_mismatch_blocks_identity(): void
    {
        $booking = $this->seedMatchingSabreBooking('WL96PKN9');
        SupplierBooking::query()->where('booking_id', $booking->id)->update(['pnr' => 'OTHER99']);
        $identity = app(SabreGdsQrUnticketedCancelIdentityResolver::class)->resolve($booking->fresh(), null);
        $this->assertFalse($identity['identity_checks_passed']);
        $this->assertContains('booking_supplier_pnr_mismatch', $identity['identity_blockers']);
    }

    public function test_fezjfp_is_rejected(): void
    {
        $booking = $this->seedMatchingSabreBooking('FEZJFP');
        $identity = app(SabreGdsQrUnticketedCancelIdentityResolver::class)->resolve($booking, null);
        $this->assertFalse($identity['identity_checks_passed']);
        $this->assertTrue($identity['locator_denylisted']);
    }

    public function test_send_gate_requires_booking_id_three_in_production(): void
    {
        $lifecycle = app(SabreGdsQrUnticketedCancelLifecycle::class);
        $this->app->instance('env', 'production');
        $gate = $lifecycle->evaluateGate([
            'confirm_production' => SabreGdsQrUnticketedCancelLifecycle::CONFIRM_PRODUCTION,
            'confirm_cancellation' => SabreGdsQrUnticketedCancelLifecycle::CONFIRM_CANCELLATION,
            'confirm_no_ticketing' => SabreGdsQrUnticketedCancelLifecycle::CONFIRM_NO_TICKETING,
        ], true, 99);
        $this->assertFalse($gate['allowed']);
        $this->assertContains('production_booking_id_must_be_3', $gate['reasons']);
    }

    public function test_create_attempt_completes_same_row_on_success(): void
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);
        $booking = Booking::factory()->create(['agency_id' => $agency->id]);
        $recorder = app(SabreGdsQrUnticketedSupplierCreateAttemptRecorder::class);
        $started = $recorder->recordStarted($booking, $connection, 'lifecycle-test', 'idem', ['segment_count' => 2]);
        $recorder->completeFromCheckoutResult($started->id, $booking, [
            'success' => true,
            'status' => 'pending_payment_or_ticketing',
            'live_call_attempted' => true,
            'pnr' => 'TESTPNR',
            'provider_booking_id' => 'TESTPNR',
            'http_status' => 200,
        ], 'scenario_runner');

        $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('action', 'create_pnr')->count());
        $started->refresh();
        $this->assertSame('success', $started->status);
        $this->assertNotNull($started->completed_at);
        $this->assertSame('TESTPNR', $started->supplier_reference);
    }

    /**
     * @return Booking
     */
    private function seedMatchingSabreBooking(string $pnr): Booking
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'supplier_reference' => $pnr,
            'status' => BookingStatus::Pending,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
            ],
        ]);
        SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'supplier_reference' => $pnr,
            'status' => 'pending_ticketing',
        ]);

        return $booking->fresh();
    }
}
