<?php

namespace Tests\Feature;

use App\Support\Bookings\SabreControlledPnrManualReviewApproval;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreApproveControlledPnrCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);
        Http::fake();
    }

    public function test_dry_run_does_not_mutate_db(): void
    {
        $booking = $this->booking53Style();
        $metaBefore = $booking->meta;

        Artisan::call('sabre:approve-controlled-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--reason' => 'burn_in_review',
            '--approved-by' => 'ops@test',
        ]);

        $output = Artisan::output();
        $booking->refresh();
        $this->assertSame($metaBefore, $booking->meta);
        $this->assertStringContainsString('classification=approval_dry_run_only', $output);
        $this->assertStringContainsString('db_mutation_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_without_exact_confirm_does_not_mutate_db(): void
    {
        $booking = $this->booking53Style();

        Artisan::call('sabre:approve-controlled-pnr', [
            '--booking' => (string) $booking->id,
            '--reason' => 'burn_in_review',
            '--approved-by' => 'ops@test',
        ]);

        $booking->refresh();
        $this->assertNull(app(SabreControlledPnrManualReviewApproval::class)->extractRecord(
            is_array($booking->meta) ? $booking->meta : []
        ));
        $this->assertStringContainsString('classification=approval_blocked_missing_confirmation', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_exact_confirm_writes_only_controlled_pnr_manual_review_meta(): void
    {
        $booking = $this->booking53Style();
        $metaBefore = is_array($booking->meta) ? $booking->meta : [];

        Artisan::call('sabre:approve-controlled-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'APPROVE-CONTROLLED-PNR-FOR-BOOKING-'.$booking->id,
            '--reason' => 'GF burn_in',
            '--approved-by' => 'platform_ops',
        ]);

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $record = app(SabreControlledPnrManualReviewApproval::class)->extractRecord($meta);

        $this->assertNotNull($record);
        $this->assertTrue($record['approved']);
        $this->assertSame('controlled_pnr_create', $record['approved_for']);
        $this->assertSame('artisan', $record['approval_source']);
        $this->assertSame((string) $booking->reference_code, $record['approval_booking_reference']);

        foreach (array_keys($metaBefore) as $key) {
            if ($key === SabreControlledPnrManualReviewApproval::META_KEY) {
                continue;
            }
            $this->assertSame($metaBefore[$key], $meta[$key], "Unexpected meta mutation on key {$key}");
        }

        $output = Artisan::output();
        $this->assertStringContainsString('approval_written=true', $output);
        $this->assertStringContainsString('db_mutation_attempted=true', $output);
        Http::assertNothingSent();
    }

    public function test_approval_refused_when_existing_pnr(): void
    {
        $booking = $this->booking53Style();
        $booking->forceFill(['pnr' => 'EXISTING'])->save();

        Artisan::call('sabre:approve-controlled-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'APPROVE-CONTROLLED-PNR-FOR-BOOKING-'.$booking->id,
        ]);

        $booking->refresh();
        $this->assertFalse(app(SabreControlledPnrManualReviewApproval::class)->isApproved(
            is_array($booking->meta) ? $booking->meta : []
        ));
        $this->assertStringContainsString('existing_pnr_present', Artisan::output());
    }

    public function test_approval_refused_when_controlled_context_unusable(): void
    {
        $booking = $this->booking53Style([
            'normalized_offer_snapshot' => [],
            'pricing_snapshot' => [],
            SabreSafeRefreshContext::META_KEY => [],
            'certified_route_selection' => [],
        ]);

        Artisan::call('sabre:approve-controlled-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('approval_eligible=false', $output);
        $this->assertStringContainsString('controlled_context_unusable', $output);
    }

    public function test_mutation_flags_remain_disabled_after_approval(): void
    {
        $booking = $this->booking53Style();

        Artisan::call('sabre:approve-controlled-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'APPROVE-CONTROLLED-PNR-FOR-BOOKING-'.$booking->id,
        ]);

        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.cancel_enabled'));
        $this->assertFalse((bool) config(
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled'
        ));
    }
}
