<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use App\Support\Bookings\SabreControlledPnrManualReviewApproval;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledApplyFreshPnrContextCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'app.env' => 'testing',
            'ota.controlled_final_pnr_freshness.max_minutes' => 15,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.public_auto_pnr_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $metaOverrides
     */
    protected function staleRefreshRequiredBooking(array $metaOverrides = []): Booking
    {
        $digest = [
            'status' => 'incomplete_no_locator',
            'application_status' => 'Incomplete',
            'has_record_locator' => false,
            'error_count' => 1,
            'warning_count' => 1,
            'errors' => [
                ['type' => 'error', 'code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
            ],
            'warnings' => [
                ['type' => 'warning', 'code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            ],
            'messages' => [],
            'source' => 'passenger_records_create',
            'recorded_at' => now()->toIso8601String(),
        ];

        $snapshot = $this->qrSellabilitySnapshot();

        return $this->booking53Style(array_merge([
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'JED',
                'depart_date' => '2026-07-23',
                'adults' => 1,
            ],
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
            'flight_offer_snapshot' => $snapshot,
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => true,
                'validating_carrier' => 'QR',
                'brand_code' => 'ECONVENIEN',
            ],
            'certified_route_selection' => [
                'category' => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
                'route_status' => SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
                'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
                'payload_style' => 'iati_like_cpnr_v2_4_gds',
            ],
            'last_revalidated_at' => now()->subHours(4)->toIso8601String(),
            'offer_freshness' => ['freshness_status' => 'stale'],
            SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => $digest,
        ], $this->approvalMetaForBooking(), $metaOverrides));
    }

    /**
     * F9P: F9N+F9O applied with stale final freshness (eligible for controlled re-run).
     *
     * @param  array<string, mixed>  $metaOverrides
     */
    protected function postF9oStaleForFreshRerun(array $metaOverrides = []): Booking
    {
        $snapshot = $this->bfmStrongCandidateSnapshot();
        $appliedAt = now()->subMinutes(53)->toIso8601String();

        return $this->staleRefreshRequiredBooking(array_merge([
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
            'flight_offer_snapshot' => $snapshot,
            'last_revalidated_at' => $appliedAt,
            'selected_offer_created_at' => $appliedAt,
            SabreControlledFreshPnrContextApply::META_KEY => [
                'applied' => true,
                'applied_at' => $appliedAt,
                'applied_by' => 'controlled_command',
            ],
            SabreControlledStrongRevalidationLinkageApply::META_KEY => [
                'applied' => true,
                'applied_at' => $appliedAt,
                'applied_by' => 'controlled_command',
                'segment_count_match' => true,
                'rbd_match' => true,
                'fare_basis_match' => true,
                'brand_match' => true,
                'validating_carrier_match' => true,
            ],
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => true,
                'strong_bfm_revalidation_linkage_applied' => true,
                'validating_carrier' => 'QR',
                'brand_code' => 'ECONVENIEN',
            ],
        ], $metaOverrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function bfmStrongCandidateSnapshot(): array
    {
        return [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'offer-f9p-rerun-bfm',
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'fare_family_code' => 'ECONVENIEN',
            'origin' => 'LHE',
            'destination' => 'JED',
            'fare_breakdown' => [
                'supplier_total' => 88415.63,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1],
            ],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'departure_at' => '2026-07-23T03:10:00',
                    'arrival_at' => '2026-07-23T06:40:00',
                    'carrier' => 'QR',
                    'flight_number' => '621',
                    'booking_class' => 'O',
                    'fare_basis_code' => 'OJPKP1RI',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'departure_at' => '2026-07-23T07:40:00',
                    'arrival_at' => '2026-07-23T10:10:00',
                    'carrier' => 'QR',
                    'flight_number' => '1190',
                    'booking_class' => 'O',
                    'fare_basis_code' => 'OJPKP1RI',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'distribution_channel' => 'GDS',
                    'shop_endpoint_path' => '/v4/offers/shop',
                    'itinerary_ref' => '2',
                    'pricing_information_index' => 0,
                    'validating_carrier' => 'QR',
                    'fare_basis_codes' => ['OJPKP1RI', 'OJPKP1RI'],
                    'booking_classes_by_segment' => ['O', 'O'],
                    'fare_component_refs' => [7, 8],
                    'leg_refs' => [7, 8],
                    'schedule_refs' => [7, 8],
                ],
                'sabre_shop_identifiers' => [
                    'itinerary_id' => '2',
                    'pricing_information_index' => 0,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrSellabilitySnapshot(): array
    {
        return [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'offer-f9n-qr-controlled',
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'origin' => 'LHE',
            'destination' => 'JED',
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'departure_at' => '2026-07-23T03:10:00',
                    'arrival_at' => '2026-07-23T06:40:00',
                    'carrier' => 'QR',
                    'flight_number' => '621',
                    'booking_class' => 'O',
                    'fare_basis_code' => 'OJPKP1RI',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'departure_at' => '2026-07-23T07:40:00',
                    'arrival_at' => '2026-07-23T10:10:00',
                    'carrier' => 'QR',
                    'flight_number' => '1190',
                    'booking_class' => 'O',
                    'fare_basis_code' => 'OJPKP1RI',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'pricing_information_ref' => 'pi-f9n-qr',
                    'offer_ref' => 'offer-f9n-qr',
                    'itinerary_ref' => 'itin-f9n-qr',
                    'validating_carrier' => 'QR',
                    'fare_basis_codes' => ['OJPKP1RI', 'OJPKP1RI'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function freshMatchingOffer(): array
    {
        return $this->qrSellabilitySnapshot();
    }

    protected function freshMatchingBfmOffer(): array
    {
        return $this->bfmStrongCandidateSnapshot();
    }

    protected function mockSearchReturning(array $offer, int $times = 1): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')
            ->times($times)
            ->andReturn(['offers' => [$offer], 'warnings' => []]);
        $this->app->instance(FlightSearchService::class, $mock);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonOutput(): array
    {
        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function test_reference_resolves_by_booking_reference(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $booking->forceFill(['booking_reference' => 'PAR-F9N-REF-TEST'])->save();
        $this->mockSearchReturning($this->freshMatchingOffer());

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--reference' => 'PAR-F9N-REF-TEST',
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertSame($booking->id, $decoded['booking_id']);
        $this->assertSame('controlled_fresh_context_apply_dry_run', $decoded['classification']);
    }

    public function test_dry_run_does_not_mutate_booking_meta(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $metaBefore = $booking->fresh()->meta;
        $this->mockSearchReturning($this->freshMatchingOffer());

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertSame('controlled_fresh_context_apply_dry_run', $decoded['classification']);
        $this->assertFalse($decoded['db_mutation_attempted']);
        $this->assertFalse($decoded['pnr_create_attempted']);
        $this->assertTrue($decoded['live_supplier_call_attempted']);

        $booking->refresh();
        $this->assertSame($metaBefore, $booking->meta);
        $this->assertNull(data_get($booking->meta, SabreControlledFreshPnrContextApply::META_KEY));
    }

    public function test_live_apply_requires_exact_confirm_in_production(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->staleRefreshRequiredBooking();
        $this->mockSearchReturning($this->freshMatchingOffer());

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertSame('controlled_fresh_context_apply_failed', $decoded['classification']);
        $this->assertFalse($decoded['context_applied']);
        $this->assertContains('missing_or_invalid_confirm_phrase', $decoded['blockers']);
    }

    public function test_blocks_when_pnr_exists(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $booking->forceFill(['pnr' => 'ABC123'])->save();
        $this->mockSearchReturning($this->freshMatchingOffer());

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertFalse($decoded['eligible']);
        $this->assertContains('existing_pnr_present', $decoded['blockers']);
        $this->assertFalse($decoded['would_apply']);
    }

    public function test_blocks_when_ticketed(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $booking->forceFill(['status' => BookingStatus::Ticketed])->save();
        $this->mockSearchReturning($this->freshMatchingOffer());

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertContains('ticketed_booking_blocked', $decoded['blockers']);
    }

    public function test_blocks_when_cancelled(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();
        $this->mockSearchReturning($this->freshMatchingOffer());

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertContains('cancelled_booking_blocked', $decoded['blockers']);
    }

    public function test_blocks_when_match_confidence_not_high(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $mismatch = $this->freshMatchingOffer();
        $mismatch['segments'][0]['flight_number'] = '999';
        $this->mockSearchReturning($mismatch);

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertFalse($decoded['eligible']);
        $this->assertContains('fresh_probe_match_not_found', $decoded['blockers']);
    }

    public function test_blocks_when_same_rbd_false(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $rbdMismatch = $this->freshMatchingOffer();
        $rbdMismatch['segments'][0]['booking_class'] = 'Y';
        $rbdMismatch['segments'][1]['booking_class'] = 'Y';
        $this->mockSearchReturning($rbdMismatch);

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertFalse($decoded['same_rbd_list']);
        $this->assertContains('fresh_probe_rbd_mismatch', $decoded['blockers']);
    }

    public function test_live_apply_writes_safe_meta_only_and_never_creates_pnr(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $this->mockSearchReturning($this->freshMatchingOffer(), 2);

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--confirm' => SabreControlledFreshPnrContextApply::confirmPhraseForBooking($booking),
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertSame('controlled_fresh_context_applied', $decoded['classification']);
        $this->assertTrue($decoded['context_applied']);
        $this->assertFalse($decoded['pnr_create_attempted']);
        $this->assertFalse($decoded['ticketing_attempted']);
        $this->assertFalse($decoded['cancellation_attempted']);
        $this->assertTrue($decoded['controlled_pnr_retry_after_fresh_context_apply_requires_new_approval']);

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $record = data_get($booking->meta, SabreControlledFreshPnrContextApply::META_KEY);
        $this->assertIsArray($record);
        $this->assertTrue($record['applied']);
        $this->assertSame('controlled_command', $record['applied_by']);
        $this->assertArrayNotHasKey('raw_payload', $record);
        $this->assertArrayNotHasKey('response_body', $record);
        $this->assertSame('refreshed', data_get($booking->meta, 'offer_refresh_status'));
    }

    public function test_post_apply_sellability_no_longer_stale_context(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $this->mockSearchReturning($this->freshMatchingOffer(), 2);

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--confirm' => SabreControlledFreshPnrContextApply::confirmPhraseForBooking($booking),
            '--json' => true,
        ]);

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertFalse($decoded['stale_context_risk']);
        $this->assertNotSame('refresh_required_before_retry', $decoded['recommended_lane']);
        $this->assertTrue($decoded['controlled_pnr_retry_after_fresh_context_apply_requires_new_approval']);
    }

    public function test_public_auto_pnr_and_ticketing_flags_unchanged(): void
    {
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.public_auto_pnr_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.cancel_enabled'));
    }

    public function test_blocks_without_manual_review_approval(): void
    {
        $booking = $this->staleRefreshRequiredBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        unset($meta[SabreControlledPnrManualReviewApproval::META_KEY]);
        $booking->forceFill(['meta' => $meta])->save();
        $this->mockSearchReturning($this->freshMatchingOffer());

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertContains('controlled_pnr_manual_review_not_approved', $decoded['blockers']);
    }

    public function test_final_freshness_rerun_dry_run_eligible_without_already_applied_blocker(): void
    {
        $booking = $this->postF9oStaleForFreshRerun();
        $this->mockSearchReturning($this->freshMatchingBfmOffer());

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertTrue($decoded['final_freshness_rerun']);
        $this->assertNotContains('fresh_context_already_applied', $decoded['blockers']);
    }

    public function test_final_freshness_rerun_live_preserves_strong_linkage_when_probe_matches(): void
    {
        $booking = $this->postF9oStaleForFreshRerun();
        $this->mockSearchReturning($this->freshMatchingBfmOffer(), 2);

        Artisan::call('sabre:controlled-apply-fresh-pnr-context', [
            '--booking' => (string) $booking->id,
            '--confirm' => SabreControlledFreshPnrContextApply::confirmPhraseForBooking($booking),
            '--json' => true,
        ]);

        $decoded = $this->decodeJsonOutput();
        $this->assertSame('controlled_fresh_context_applied', $decoded['classification']);
        $this->assertTrue($decoded['final_freshness_rerun']);
        $this->assertTrue($decoded['strong_linkage_preserved']);
        $this->assertFalse($decoded['strong_linkage_recheck_required']);
        $this->assertFalse($decoded['pnr_create_attempted']);

        $booking->refresh();
        $freshRecord = data_get($booking->meta, SabreControlledFreshPnrContextApply::META_KEY);
        $this->assertTrue($freshRecord['rerun'] ?? false);
        $strongRecord = data_get($booking->meta, SabreControlledStrongRevalidationLinkageApply::META_KEY);
        $this->assertTrue($strongRecord['applied']);
    }
}
