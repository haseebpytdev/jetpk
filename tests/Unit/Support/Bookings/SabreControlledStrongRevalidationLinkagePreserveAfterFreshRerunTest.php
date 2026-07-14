<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledStrongRevalidationLinkagePreserveAfterFreshRerunTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_preserve_or_invalidate_invalidates_when_matrix_refs_drift(): void
    {
        $booking = $this->booking53Style([
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => true,
                'strong_bfm_revalidation_linkage_applied' => true,
            ],
            SabreControlledStrongRevalidationLinkageApply::META_KEY => [
                'applied' => true,
                'applied_at' => now()->subHour()->toIso8601String(),
                'applied_by' => 'controlled_command',
            ],
        ]);

        $prior = $booking->meta[SabreControlledStrongRevalidationLinkageApply::META_KEY];
        $apply = app(SabreControlledStrongRevalidationLinkageApply::class);

        $outcome = $apply->preserveOrInvalidateAfterFreshRerun($booking, $prior, [
            'segment_count_match' => true,
            'rbd_match' => false,
            'fare_basis_match' => true,
            'brand_match' => true,
            'validating_carrier_present' => true,
        ]);

        $this->assertFalse($outcome['strong_linkage_preserved']);
        $this->assertTrue($outcome['strong_linkage_recheck_required']);

        $booking->refresh();
        $record = $booking->meta[SabreControlledStrongRevalidationLinkageApply::META_KEY];
        $this->assertFalse($record['applied']);
        $this->assertTrue($record['recheck_required']);
        $this->assertSame('fresh_rerun_refs_changed', $record['invalidated_reason']);
    }

    public function test_preserve_or_invalidate_preserves_when_matrix_still_matches(): void
    {
        $booking = $this->booking53Style([
            SabreControlledStrongRevalidationLinkageApply::META_KEY => [
                'applied' => true,
                'applied_at' => now()->subHour()->toIso8601String(),
                'applied_by' => 'controlled_command',
            ],
        ]);

        $prior = $booking->meta[SabreControlledStrongRevalidationLinkageApply::META_KEY];
        $apply = app(SabreControlledStrongRevalidationLinkageApply::class);

        $outcome = $apply->preserveOrInvalidateAfterFreshRerun($booking, $prior, [
            'segment_count_match' => true,
            'rbd_match' => true,
            'fare_basis_match' => true,
            'brand_match' => true,
            'validating_carrier_present' => true,
        ]);

        $this->assertTrue($outcome['strong_linkage_preserved']);
        $this->assertFalse($outcome['strong_linkage_recheck_required']);

        $booking->refresh();
        $record = $booking->meta[SabreControlledStrongRevalidationLinkageApply::META_KEY];
        $this->assertTrue($record['applied']);
        $this->assertArrayHasKey('preserved_after_fresh_rerun_at', $record);
    }
}
