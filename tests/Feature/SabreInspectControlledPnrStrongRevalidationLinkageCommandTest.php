<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreInspectControlledPnrStrongRevalidationLinkageCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.public_auto_pnr_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
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
            'supplier_offer_id' => 'offer-f9o-qr-bfm',
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'fare_family_code' => 'ECONVENIEN',
            'origin' => 'LHE',
            'destination' => 'JED',
            'fare_breakdown' => [
                'supplier_total' => 88415.63,
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
    protected function postF9nWeakLinkageBooking(array $metaOverrides = []): Booking
    {
        $snapshot = $this->bfmStrongCandidateSnapshot();
        $revalidatedAt = now()->subMinutes(2)->toIso8601String();

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
            'revalidation_status' => 'success',
            'selected_offer_revalidation_status' => 'success',
            'last_revalidated_at' => $revalidatedAt,
            'selected_offer_last_revalidated_at' => $revalidatedAt,
            'offer_refresh_status' => 'refreshed',
            'offer_refresh_reason' => 'inventory_refresh',
            'offer_refresh_refreshed_at' => $revalidatedAt,
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => false,
                'validating_carrier' => 'QR',
                'brand_code' => 'ECONVENIEN',
            ],
            SabreControlledFreshPnrContextApply::META_KEY => [
                'applied' => true,
                'applied_at' => $revalidatedAt,
                'applied_by' => 'controlled_command',
                'reason' => 'fresh_probe_ready_to_apply_after_no_fares_rbd_carrier',
            ],
        ], $metaOverrides));
    }

    public function test_inspect_command_is_read_only_and_outputs_linkage_diagnostics(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('strong_linkage_matrix=', $output);
        $this->assertStringContainsString('recommended_lane=', $output);
        $this->assertStringNotContainsString('request_payload', $output);
        Http::assertNothingSent();
    }

    public function test_production_requires_exact_readonly_confirm(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
        ]);
        $this->assertStringContainsString('Production requires --confirm=', Artisan::output());

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-LINKAGE',
        ]);
        $this->assertStringContainsString('current_revalidation_linkage_strength=', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_reference_lookup_uses_booking_reference(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $booking->forceFill(['booking_reference' => 'PAR-F9O-REF'])->save();

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--reference' => 'PAR-F9O-REF',
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertSame($booking->id, $decoded['booking_id']);
    }

    public function test_diagnostic_reports_legacy_weak_state_after_f9n(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertTrue($decoded['legacy_revalidation_signal_used']);
        $this->assertTrue($decoded['controlled_fresh_context_apply_present']);
        $this->assertFalse($decoded['stale_context_risk']);
    }

    public function test_diagnostic_identifies_strong_linkage_candidate_when_bfm_refs_complete(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $matrix = $decoded['strong_linkage_matrix'] ?? [];
        $this->assertTrue($matrix['itinerary_ref_present'] ?? false);
        $this->assertTrue($matrix['pricing_information_index_present'] ?? false);
        $this->assertTrue($matrix['validating_carrier_present'] ?? false);
        $this->assertTrue($matrix['strong_revalidation_candidate'] ?? false);
        $this->assertContains($decoded['recommended_lane'], [
            'strong_revalidation_apply_required',
            'strong_revalidation_linkage_ready',
        ]);
    }

    public function test_diagnostic_blocks_strong_candidate_when_validating_carrier_missing(): void
    {
        $snapshot = $this->bfmStrongCandidateSnapshot();
        unset($snapshot['validating_carrier']);
        unset($snapshot['raw_payload']['sabre_shop_context']['validating_carrier']);

        $booking = $this->postF9nWeakLinkageBooking(array_merge($this->approvalMetaForBooking(), [
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => false,
                'brand_code' => 'ECONVENIEN',
            ],
        ]));

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $blockers = $decoded['strong_linkage_matrix']['strong_revalidation_blockers'] ?? [];
        $this->assertContains('missing_validating_carrier', $blockers);
        $this->assertFalse($decoded['strong_linkage_matrix']['strong_revalidation_candidate'] ?? true);
    }

    public function test_probe_requires_stricter_confirm_in_production_and_does_not_mutate_booking(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $metaBefore = $booking->meta;

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--probe-revalidate' => true,
            '--confirm' => 'READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-LINKAGE',
        ]);
        $this->assertStringContainsString('Invalid --confirm phrase', Artisan::output());

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--probe-revalidate' => true,
            '--confirm' => 'READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-PROBE',
        ]);

        $booking->refresh();
        $this->assertSame($metaBefore, $booking->meta);
    }
}
