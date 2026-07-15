<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Support\Bookings\SabreGdsAutoPnrLifecycleService;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreGdsAutoPnrLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_obsolete_iati_waiver_flags_when_refresh_succeeded(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'offer_refresh_status' => 'refreshed',
            ],
        ]);

        $service = app(SabreGdsAutoPnrLifecycleService::class);
        $decision = $service->reconcileObsoleteIatiWaiverFlags([
            'iati_like_selected' => true,
            'iati_like_expects_revalidation_waiver_or_refresh' => true,
            'iati_style_expects_revalidation_waiver_or_refresh' => true,
            'revalidation_skip_reason' => 'iati_cpnr_revalidation_waived',
            'refresh_status' => 'refreshed',
            'refresh_result' => 'ok',
            'refresh_attempted' => true,
        ], $booking);

        $this->assertArrayNotHasKey('iati_like_expects_revalidation_waiver_or_refresh', $decision);
        $this->assertArrayNotHasKey('iati_style_expects_revalidation_waiver_or_refresh', $decision);
        $this->assertSame('iati_cpnr_refresh_satisfied', $decision['revalidation_skip_reason']);
        $this->assertTrue($decision['refresh_satisfied_revalidation_waiver']);
    }

    public function test_reconcile_checkout_outcome_clears_skipped_revalidation_when_refresh_ok(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'offer_refresh_status' => 'refreshed',
            ],
        ]);

        $service = app(SabreGdsAutoPnrLifecycleService::class);
        $outcome = $service->reconcileCheckoutOutcomeRevalidationFlags($booking, [
            'revalidation_skipped_by_config' => true,
            'prebooking_revalidation_skipped_reason' => 'pnr_only_ticketing_disabled',
        ]);

        $this->assertFalse($outcome['revalidation_skipped_by_config']);
        $this->assertSame('offer_refresh_satisfied', $outcome['prebooking_revalidation_skipped_reason']);
    }

    public function test_persist_pnr_create_artifacts_stores_segment_status_and_expiry(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
            ],
        ]);

        $service = app(SabreGdsAutoPnrLifecycleService::class);
        $service->persistPnrCreateArtifacts($booking, [
            'pnr' => 'ABC123',
            'airline_segment_status' => 'HK',
            'time_limit_iso' => '2026-07-01T18:00:00Z',
        ]);

        $booking->refresh();
        $block = $booking->meta[SabreGdsAutoPnrLifecycleService::META_KEY] ?? [];
        $this->assertTrue($block['pnr_created']);
        $this->assertTrue($block['ticketing_pending']);
        $this->assertSame('HK', $block['airline_segment_status']);
        $this->assertSame('2026-07-01T18:00:00+00:00', $booking->meta[SabrePnrCertificationSupport::META_EXPIRES_AT] ?? null);
    }

    public function test_admin_resolve_reports_lifecycle_checkpoints(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'PNR123',
            'supplier_booking_status' => 'pending_payment_or_ticketing',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'offer_refresh_status' => 'refreshed',
                SabreGdsAutoPnrLifecycleService::META_KEY => [
                    'pnr_created' => true,
                    'itinerary_synced' => true,
                    'ticketing_pending' => true,
                    'airline_segment_status' => 'HK',
                ],
                'pnr_itinerary_sync' => ['status' => 'synced', 'synced_at' => now()->toIso8601String()],
            ],
        ]);

        $resolved = app(SabreGdsAutoPnrLifecycleService::class)->resolveForAdmin($booking);

        $this->assertTrue($resolved['applies']);
        $this->assertTrue($resolved['offer_refreshed']);
        $this->assertTrue($resolved['pnr_created']);
        $this->assertTrue($resolved['itinerary_synced']);
        $this->assertTrue($resolved['ticketing_pending']);
        $this->assertSame('HK', $resolved['airline_segment_status']);
        $this->assertCount(4, $resolved['rows']);
    }

    public function test_does_not_apply_to_sabre_ndc_scope(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'ndc',
            ],
        ]);

        $this->assertFalse(SabreGdsAutoPnrLifecycleService::appliesTo($booking));
    }
}
