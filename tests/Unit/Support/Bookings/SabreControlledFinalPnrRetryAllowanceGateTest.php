<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Booking;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledFinalPnrRetryAllowanceGateTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'ota.controlled_final_pnr_retry_allowance.max_minutes' => 15,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
        ]);
    }

    public function test_build_allowance_record_has_safe_shape(): void
    {
        $booking = Booking::factory()->make(['id' => 53, 'booking_reference' => 'PAR-TEST53']);
        $record = app(SabreControlledFinalPnrRetryAllowanceGate::class)->buildAllowanceRecord($booking, [
            'final_pnr_retry_ready' => true,
        ]);

        $this->assertTrue($record['allowed']);
        $this->assertFalse($record['used']);
        $this->assertSame(SabreControlledFinalPnrRetryAllowanceGate::ALLOWED_BY, $record['allowed_by']);
        $this->assertSame(SabreControlledFinalPnrRetryAllowanceGate::REASON, $record['reason']);
        $this->assertSame('CREATE-PNR-FOR-BOOKING-53', $record['requires_exact_create_confirm']);
        $this->assertFalse($record['ticketing_enabled']);
        $this->assertFalse($record['cancellation_enabled']);
        $this->assertArrayNotHasKey('response_payload', $record);
    }

    public function test_is_allowance_valid_in_meta_rejects_expired(): void
    {
        Carbon::setTestNow('2026-06-18 15:00:00');
        $booking = Booking::factory()->make(['id' => 53, 'booking_reference' => 'PAR-TEST53']);
        $meta = [
            SabreControlledFinalPnrRetryAllowanceGate::META_KEY => [
                'allowed' => true,
                'used' => false,
                'booking_reference' => 'PAR-TEST53',
                'expires_at' => '2026-06-18T14:44:00+00:00',
            ],
        ];

        $this->assertFalse(SabreControlledFinalPnrRetryAllowanceGate::isAllowanceValidInMeta($meta, $booking));

        Carbon::setTestNow();
    }

    public function test_is_allowance_valid_in_meta_accepts_active(): void
    {
        $booking = Booking::factory()->make(['id' => 53, 'booking_reference' => 'PAR-TEST53']);
        $meta = [
            SabreControlledFinalPnrRetryAllowanceGate::META_KEY => [
                'allowed' => true,
                'used' => false,
                'booking_reference' => 'PAR-TEST53',
                'expires_at' => '2099-01-01T00:00:00+00:00',
            ],
        ];

        $this->assertTrue(SabreControlledFinalPnrRetryAllowanceGate::isAllowanceValidInMeta($meta, $booking));
    }

    public function test_record_usage_marks_used_once(): void
    {
        $booking = $this->booking53Style();
        $gate = app(SabreControlledFinalPnrRetryAllowanceGate::class);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY] = $gate->buildAllowanceRecord($booking, []);
        $booking->forceFill(['meta' => $meta])->save();

        $gate->recordUsage($booking);
        $booking->refresh();
        $record = $booking->meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY];
        $this->assertTrue($record['used']);
        $this->assertSame(SabreControlledFinalPnrRetryAllowanceGate::USED_FOR, $record['used_for']);
        $this->assertTrue($record['create_attempted']);

        $gate->recordUsage($booking->fresh());
        $booking->refresh();
        $this->assertSame($record['used_at'], $booking->meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY]['used_at']);
    }

    public function test_all_prior_retry_allowances_consumed_requires_f9f_f9j_f9l(): void
    {
        $gate = app(SabreControlledFinalPnrRetryAllowanceGate::class);
        $meta = [
            SabreControlledPnrRetryAllowanceGate::META_KEY => ['used' => true],
            SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY => ['used' => true],
        ];
        $this->assertFalse($gate->allPriorRetryAllowancesConsumed($meta));

        $meta[SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::META_KEY] = ['used' => true];
        $this->assertTrue($gate->allPriorRetryAllowancesConsumed($meta));
    }

    public function test_assess_post_final_retry_containment_from_used_allowance_and_digest_warnings(): void
    {
        $gate = app(SabreControlledFinalPnrRetryAllowanceGate::class);
        $booking = $this->booking53Style([
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
            SabreControlledFinalPnrRetryAllowanceGate::META_KEY => [
                'allowed' => true,
                'used' => true,
                'create_attempted' => true,
                'used_for' => SabreControlledFinalPnrRetryAllowanceGate::USED_FOR,
            ],
        ]);

        $containment = $gate->assessPostFinalRetryContainment($booking);

        $this->assertTrue($containment['contained']);
        $this->assertTrue($containment['controlled_final_pnr_retry_allowance_used']);
        $this->assertTrue($containment['final_controlled_create_attempted']);
        $this->assertTrue($containment['final_controlled_create_failed']);
        $this->assertTrue($containment['post_final_retry_host_failure']);
        $this->assertSame('NO_FARES_RBD_CARRIER', $containment['post_final_retry_host_failure_code']);
        $this->assertTrue($containment['no_safe_retry_without_remediation']);
        $this->assertSame(
            SabreControlledFinalPnrRetryAllowanceGate::POST_FINAL_RETRY_CONTAINMENT_BLOCKERS,
            $containment['blockers'],
        );
    }

    public function test_apply_post_final_retry_containment_output_alignment(): void
    {
        $gate = app(SabreControlledFinalPnrRetryAllowanceGate::class);
        $containment = $gate->assessPostFinalRetryContainment($this->booking53Style([
            SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => [
                'warnings' => [
                    ['message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                ],
            ],
            SabreControlledFinalPnrRetryAllowanceGate::META_KEY => [
                'used' => true,
                'create_attempted' => true,
                'used_for' => SabreControlledFinalPnrRetryAllowanceGate::USED_FOR,
            ],
        ]));

        $aligned = $gate->applyPostFinalRetryContainmentOutputAlignment([
            'eligible' => true,
            'can_attempt_supplier_pnr' => true,
            'live_supplier_call_allowed' => true,
            'exact_create_confirmation_required' => true,
            'recommended_next_action' => 'Controlled PNR create may be attempted with explicit admin/command confirmation only.',
            'blockers' => [],
        ], $containment);

        $this->assertFalse($aligned['eligible']);
        $this->assertFalse($aligned['can_attempt_supplier_pnr']);
        $this->assertFalse($aligned['live_supplier_call_allowed']);
        $this->assertFalse($aligned['exact_create_confirmation_required']);
        $this->assertSame(
            SabreControlledFinalPnrRetryAllowanceGate::POST_FINAL_RETRY_CONTAINMENT_RECOMMENDED_NEXT_ACTION,
            $aligned['recommended_next_action'],
        );
        $this->assertSame(
            SabreControlledFinalPnrRetryAllowanceGate::POST_FINAL_RETRY_CONTAINMENT_BLOCKED_MESSAGE,
            $aligned['blocked_message'],
        );
        $this->assertSame(
            SabreControlledFinalPnrRetryAllowanceGate::POST_FINAL_RETRY_CONTAINMENT_BLOCKERS,
            $aligned['blockers'],
        );
        $this->assertSame(
            SabreControlledFinalPnrRetryAllowanceGate::POST_FINAL_RETRY_CONTAINMENT_ERROR_CODE,
            $aligned['error_code'],
        );
        $this->assertSame(
            SabreControlledFinalPnrRetryAllowanceGate::POST_FINAL_RETRY_CONTAINMENT_BLOCKED_MESSAGE,
            $aligned['error_message'],
        );
    }

    public function test_record_host_failure_outcome_persists_safe_meta(): void
    {
        $gate = app(SabreControlledFinalPnrRetryAllowanceGate::class);
        $booking = $this->booking53Style([
            SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => [
                'status' => 'incomplete_no_locator',
                'warnings' => [
                    ['message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                ],
            ],
            SabreControlledFinalPnrRetryAllowanceGate::META_KEY => [
                'allowed' => true,
                'used' => true,
                'create_attempted' => true,
            ],
        ]);

        $gate->recordHostFailureOutcome($booking, [
            'success' => false,
            'error_code' => 'sabre_booking_application_error',
            'application_error_digest_available' => true,
        ]);

        $booking->refresh();
        $record = $booking->meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY];
        $this->assertTrue($record['final_controlled_create_failed']);
        $this->assertTrue($record['post_final_retry_host_failure']);
        $this->assertSame('NO_FARES_RBD_CARRIER', $record['post_final_retry_host_failure_code']);
        $this->assertTrue($record['no_safe_retry_without_remediation']);
    }
}
