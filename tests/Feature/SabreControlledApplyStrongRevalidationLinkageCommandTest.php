<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledApplyStrongRevalidationLinkageCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'app.env' => 'testing',
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.public_auto_pnr_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
        ]);
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
                    'carrier' => 'QR',
                    'flight_number' => '621',
                    'booking_class' => 'O',
                    'fare_basis_code' => 'OJPKP1RI',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'departure_at' => '2026-07-23T07:40:00',
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
     * @param  int|null  $minutesAgo  Minutes since F9N apply / revalidation (default 2).
     */
    protected function postF9nWeakLinkageBooking(array $metaOverrides = [], ?int $minutesAgo = 2): Booking
    {
        $snapshot = $this->bfmStrongCandidateSnapshot();

        $revalidatedAt = now()->subMinutes($minutesAgo ?? 2)->toIso8601String();

        return $this->booking53Style(array_merge([
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
            'revalidation_status' => 'success',
            'last_revalidated_at' => $revalidatedAt,
            'offer_refresh_status' => 'refreshed',
            'offer_refresh_reason' => 'inventory_refresh',
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
            ],
        ], $metaOverrides));
    }

    public function test_apply_dry_run_does_not_mutate_booking(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $metaBefore = $booking->meta;

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('classification=controlled_strong_linkage_apply_dry_run', $output);
        $this->assertStringContainsString('db_mutation_attempted=false', $output);

        $booking->refresh();
        $this->assertSame($metaBefore, $booking->meta);
    }

    public function test_apply_requires_exact_confirm(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
        ]);
        $this->assertStringContainsString('missing_or_invalid_confirm_phrase', Artisan::output());

        $metaBefore = $booking->fresh()->meta;
        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'WRONG-PHRASE',
        ]);
        $this->assertSame($metaBefore, $booking->fresh()->meta);
    }

    public function test_apply_blocks_when_pnr_present(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $booking->forceFill(['pnr' => 'ABC123'])->save();

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('existing_pnr_present', Artisan::output());
    }

    public function test_apply_blocks_when_ticketed(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $booking->forceFill(['status' => BookingStatus::Ticketed])->save();

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('ticketed_booking_blocked', Artisan::output());
    }

    public function test_apply_writes_only_safe_meta_and_does_not_create_pnr(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $confirm = SabreControlledStrongRevalidationLinkageApply::confirmPhraseForBooking($booking);

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--confirm' => $confirm,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('classification=controlled_strong_linkage_applied', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $record = $booking->meta[SabreControlledStrongRevalidationLinkageApply::META_KEY] ?? null;
        $this->assertIsArray($record);
        $this->assertTrue($record['applied'] ?? false);
        $this->assertSame('controlled_command', $record['applied_by'] ?? '');
    }

    public function test_post_apply_diagnostic_shows_weak_revalidation_risk_false_when_strong_present(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $confirm = SabreControlledStrongRevalidationLinkageApply::confirmPhraseForBooking($booking);

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--confirm' => $confirm,
        ]);

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertSame('strong', $decoded['current_revalidation_linkage_strength'] ?? '');
        $this->assertFalse($decoded['weak_revalidation_risk'] ?? true);
    }

    public function test_no_public_auto_pnr_flags_changed(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $confirm = SabreControlledStrongRevalidationLinkageApply::confirmPhraseForBooking($booking);

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--confirm' => $confirm,
        ]);

        $this->assertFalse(config('suppliers.sabre.public_auto_pnr_enabled'));
        $this->assertFalse(config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse(config('suppliers.sabre.cancel_enabled'));
    }

    public function test_apply_allowed_when_f9m_lane_refresh_required_but_f9o_candidate_complete(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking(), 26);

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('would_apply=true', $output);
        $this->assertStringContainsString('sellability_lane_used_as_hard_gate=false', $output);
        $this->assertStringContainsString('stale_context_risk_hard_blocker=false', $output);
        $this->assertStringContainsString('f9o_diagnostic_recommended_lane=strong_revalidation_apply_required', $output);
        $this->assertStringContainsString('sellability_recommended_lane=refresh_required_before_retry', $output);
        $this->assertStringNotContainsString('sellability_lane_not_weak_revalidation', $output);
    }

    public function test_stale_context_at_26_minutes_after_f9n_is_warning_not_hard_blocker(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking(), 26);

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('stale_context_risk=true', $output);
        $this->assertStringContainsString('stale_context_risk_hard_blocker=false', $output);
        $this->assertStringContainsString('eligible=true', $output);
    }

    public function test_stale_context_hard_blocker_when_context_very_old(): void
    {
        config(['ota.controlled_strong_linkage_apply.max_minutes_after_fresh_context_apply' => 180]);
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking(), 200);

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('stale_context_risk_hard_blocker=true', $output);
        $this->assertStringContainsString('stale_context_risk', $output);
        $this->assertStringContainsString('eligible=false', $output);
    }

    public function test_stale_context_hard_blocker_when_fresh_context_apply_absent(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $meta = $booking->meta;
        unset($meta[SabreControlledFreshPnrContextApply::META_KEY]);
        $booking->forceFill(['meta' => $meta])->save();

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('stale_context_risk_hard_blocker=true', $output);
        $this->assertStringContainsString('fresh_context_apply_missing', $output);
    }

    public function test_apply_blocks_when_strong_revalidation_candidate_false(): void
    {
        $snapshot = $this->bfmStrongCandidateSnapshot();
        unset($snapshot['raw_payload']['sabre_shop_context']['itinerary_ref']);
        unset($snapshot['raw_payload']['sabre_shop_identifiers']['itinerary_id']);

        $booking = $this->postF9nWeakLinkageBooking(array_merge($this->approvalMetaForBooking(), [
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
        ]));

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('eligible=false', $output);
        $this->assertStringContainsString('strong_linkage_candidate_absent', $output);
    }

    public function test_apply_blocks_when_brand_missing(): void
    {
        $snapshot = $this->bfmStrongCandidateSnapshot();
        unset($snapshot['brand_code'], $snapshot['fare_family_code']);

        $booking = $this->postF9nWeakLinkageBooking(array_merge($this->approvalMetaForBooking(), [
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
        ]));

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('eligible=false', $output);
        $this->assertStringContainsString('strong_linkage_candidate_absent', $output);
    }

    public function test_apply_blocks_when_cancelled(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('cancelled_booking_blocked', Artisan::output());
    }

    public function test_post_apply_shows_formal_linkage_or_applied_marker(): void
    {
        $booking = $this->postF9nWeakLinkageBooking($this->approvalMetaForBooking());
        $confirm = SabreControlledStrongRevalidationLinkageApply::confirmPhraseForBooking($booking);

        Artisan::call('sabre:controlled-apply-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--confirm' => $confirm,
        ]);

        $booking->refresh();
        $record = $booking->meta[SabreControlledStrongRevalidationLinkageApply::META_KEY] ?? null;
        $this->assertIsArray($record);
        $this->assertTrue($record['applied'] ?? false);

        Artisan::call('sabre:inspect-controlled-pnr-strong-revalidation-linkage', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertTrue(
            ($decoded['controlled_strong_revalidation_linkage_apply_present'] ?? false) === true
            || ($decoded['strong_linkage_matrix']['formal_revalidation_linkage_complete'] ?? false) === true,
        );
    }
}
