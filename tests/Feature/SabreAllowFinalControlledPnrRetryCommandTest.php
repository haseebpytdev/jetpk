<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreAllowFinalControlledPnrRetryCommandTest extends TestCase
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
            'ota.controlled_final_pnr_retry_allowance.max_minutes' => 15,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.public_auto_pnr_enabled' => false,
        ]);
        Http::fake();
    }

    /**
     * @return array<string, mixed>
     */
    protected function bfmStrongCandidateSnapshot(): array
    {
        return [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'offer-f9q-qr-bfm',
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
     * @param  array<string, mixed>  $metaOverrides
     */
    protected function bookingReadyForAllowance(array $metaOverrides = [], int $minutesAgo = 5): Booking
    {
        $snapshot = $this->bfmStrongCandidateSnapshot();
        $revalidatedAt = now()->subMinutes($minutesAgo)->toIso8601String();

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
            'revalidation_status' => 'success',
            'last_revalidated_at' => $revalidatedAt,
            'selected_offer_created_at' => $revalidatedAt,
            'offer_refresh_status' => 'refreshed',
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => true,
                'strong_bfm_revalidation_linkage_applied' => true,
                'validating_carrier' => 'QR',
                'brand_code' => 'ECONVENIEN',
            ],
            'certified_route_selection' => [
                'category' => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
                'route_status' => SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
                'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
                'payload_style' => 'iati_like_cpnr_v2_4_gds',
            ],
            SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => [
                'status' => 'incomplete_no_locator',
                'application_status' => 'Incomplete',
                'error_count' => 1,
                'warning_count' => 1,
                'errors' => [
                    ['type' => 'error', 'code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
                ],
                'warnings' => [
                    ['type' => 'warning', 'code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                ],
            ],
            SabreControlledFreshPnrContextApply::META_KEY => [
                'applied' => true,
                'applied_at' => $revalidatedAt,
                'applied_by' => 'controlled_command',
            ],
            SabreControlledStrongRevalidationLinkageApply::META_KEY => [
                'applied' => true,
                'applied_at' => $revalidatedAt,
                'applied_by' => 'controlled_command',
                'segment_count_match' => true,
                'rbd_match' => true,
                'fare_basis_match' => true,
                'brand_match' => true,
                'validating_carrier_match' => true,
            ],
            SabreControlledPnrRetryAllowanceGate::META_KEY => ['used' => true],
            SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY => ['used' => true],
            SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::META_KEY => ['used' => true],
        ], $this->approvalMetaForBooking(), $metaOverrides));
    }

    public function test_dry_run_is_read_only(): void
    {
        $booking = $this->bookingReadyForAllowance();
        $metaBefore = $booking->meta;

        $exit = Artisan::call('sabre:allow-final-controlled-pnr-retry', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $booking->refresh();
        $output = Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('allowance_dry_run_only', $output);
        $this->assertStringContainsString('allowance_written=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertSame($metaBefore, $booking->meta);
        Http::assertNothingSent();
    }

    public function test_live_requires_exact_confirm_in_production(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->bookingReadyForAllowance();

        $exit = Artisan::call('sabre:allow-final-controlled-pnr-retry', [
            '--booking' => (string) $booking->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('allowance_blocked_missing_confirmation', Artisan::output());
        $booking->refresh();
        $this->assertFalse(SabreControlledFinalPnrRetryAllowanceGate::allowancePresentInMeta($booking->meta));
    }

    public function test_blocks_when_final_freshness_expired(): void
    {
        Carbon::setTestNow('2026-06-18 15:00:00');
        $booking = $this->bookingReadyForAllowance([], 20);

        Artisan::call('sabre:allow-final-controlled-pnr-retry', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('final_freshness_expired', Artisan::output());
        Carbon::setTestNow();
    }

    public function test_blocks_when_pnr_present(): void
    {
        $booking = $this->bookingReadyForAllowance();
        $booking->forceFill(['pnr' => 'ABC123'])->save();

        Artisan::call('sabre:allow-final-controlled-pnr-retry', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('existing_pnr_or_supplier_reference', Artisan::output());
    }

    public function test_blocks_when_ticketed(): void
    {
        $booking = $this->bookingReadyForAllowance();
        $booking->forceFill(['status' => BookingStatus::Ticketed])->save();

        Artisan::call('sabre:allow-final-controlled-pnr-retry', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('booking_ticketed', Artisan::output());
    }

    public function test_blocks_duplicate_active_allowance(): void
    {
        $booking = $this->bookingReadyForAllowance();
        $gate = app(SabreControlledFinalPnrRetryAllowanceGate::class);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY] = $gate->buildAllowanceRecord($booking, []);
        $booking->forceFill(['meta' => $meta])->save();

        Artisan::call('sabre:allow-final-controlled-pnr-retry', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('unused_allowance_already_active', Artisan::output());
    }

    public function test_live_writes_safe_meta_only(): void
    {
        config(['app.env' => 'testing']);
        $booking = $this->bookingReadyForAllowance();
        $confirm = SabreControlledFinalPnrRetryAllowanceGate::confirmPhraseForBooking($booking);

        $exit = Artisan::call('sabre:allow-final-controlled-pnr-retry', [
            '--booking' => (string) $booking->id,
            '--confirm' => $confirm,
        ]);

        $this->assertSame(0, $exit);
        $booking->refresh();
        $record = $booking->meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY] ?? null;
        $this->assertIsArray($record);
        $this->assertTrue($record['allowed']);
        $this->assertFalse($record['used']);
        $this->assertStringContainsString('sabre:controlled-create-pnr', Artisan::output());
        $this->assertArrayNotHasKey('response_payload', $record);
        Http::assertNothingSent();
    }

    public function test_mutation_flags_remain_disabled(): void
    {
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.cancel_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.public_auto_pnr_enabled', false));
    }

    public function test_blocks_after_f9q_final_retry_host_failure(): void
    {
        $booking = $this->bookingReadyForAllowance([
            SabreControlledFinalPnrRetryAllowanceGate::META_KEY => [
                'allowed' => true,
                'used' => true,
                'create_attempted' => true,
                'used_for' => SabreControlledFinalPnrRetryAllowanceGate::USED_FOR,
            ],
        ]);

        Artisan::call('sabre:allow-final-controlled-pnr-retry', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('allowance_eligible=false', $output);
        $this->assertStringContainsString('final_pnr_retry_not_ready', $output);
        $this->assertStringContainsString('post_final_retry_host_failure_contained', $output);
    }
}
