<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\SabreControlledPnrContextDigest;
use App\Support\Bookings\SabreControlledPnrReadiness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledPnrContextDigestTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_booking_53_style_classified_as_usable_without_strict_linkage(): void
    {
        $booking = $this->booking53Style();
        $result = app(SabreControlledPnrContextDigest::class)->classify($booking);

        $this->assertFalse($result['has_strong_revalidation_linkage']);
        $this->assertTrue($result['has_legacy_success_revalidation_signal']);
        $this->assertTrue($result['has_payload_ready_context']);
        $this->assertTrue($result['has_certified_route_selection']);
        $this->assertTrue($result['has_usable_controlled_pnr_context']);
        $this->assertSame('usable_controlled_pnr_context', $result['controlled_pnr_context_reason_code']);
        $this->assertContains('controlled_certified_context_used', $result['context_warnings']);
        $this->assertContains('legacy_revalidation_signal_used', $result['context_warnings']);
    }

    public function test_missing_pricing_snapshot_blocks(): void
    {
        $booking = $this->booking53Style(['pricing_snapshot' => []]);
        $result = app(SabreControlledPnrContextDigest::class)->classify($booking);

        $this->assertFalse($result['has_usable_controlled_pnr_context']);
        $this->assertContains('missing_pricing_snapshot', $result['context_blockers']);
    }

    public function test_missing_certified_route_selection_blocks(): void
    {
        $booking = $this->booking53Style(['certified_route_selection' => []]);
        $result = app(SabreControlledPnrContextDigest::class)->classify($booking);

        $this->assertFalse($result['has_usable_controlled_pnr_context']);
        $this->assertContains('missing_certified_route_selection', $result['context_blockers']);
    }

    public function test_existing_pnr_blocks(): void
    {
        $booking = $this->booking53Style();
        $booking->forceFill(['pnr' => 'TQMNEV'])->save();

        $result = app(SabreControlledPnrContextDigest::class)->classify($booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets']));

        $this->assertFalse($result['has_usable_controlled_pnr_context']);
        $this->assertContains('existing_pnr_present', $result['context_blockers']);
    }

    public function test_stale_high_risk_offer_blocks(): void
    {
        $booking = $this->booking53Style([
            'offer_freshness' => [
                'high_risk_cached_offer' => true,
            ],
        ]);
        $result = app(SabreControlledPnrContextDigest::class)->classify($booking);

        $this->assertFalse($result['has_usable_controlled_pnr_context']);
        $this->assertContains('stale_pricing', $result['context_blockers']);
    }

    public function test_price_change_confirmation_required_blocks(): void
    {
        $booking = $this->booking53Style([
            'offer_refresh_price_changed' => true,
            'offer_refresh_requires_customer_confirmation' => true,
            'offer_refresh_accepted' => false,
        ]);
        $result = app(SabreControlledPnrContextDigest::class)->classify($booking);

        $this->assertFalse($result['has_usable_controlled_pnr_context']);
        $this->assertContains('offer_refresh_customer_confirmation_required', $result['context_blockers']);
    }

    public function test_readiness_moves_away_from_missing_revalidation_context_for_booking_53_style(): void
    {
        config([
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
        ]);

        $booking = $this->booking53Style();
        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['has_revalidation_context']);
        $this->assertNotContains('missing_revalidation_context', $result['blockers']);
        $this->assertContains('controlled_certified_context_used', $result['warnings']);
        $this->assertContains('legacy_revalidation_signal_used', $result['warnings']);
    }
}
