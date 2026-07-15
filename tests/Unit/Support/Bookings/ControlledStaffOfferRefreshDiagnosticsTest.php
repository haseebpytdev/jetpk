<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\ControlledStaffOfferRefreshDiagnostics;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabrePnrFailureClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlledStaffOfferRefreshDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_search_context_recommends_fresh_search_and_blocks_retry(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'segments' => [['origin' => 'LHE', 'destination' => 'DXB', 'booking_class' => 'Y']],
                ],
            ],
        ]);

        $diagnostics = app(ControlledStaffOfferRefreshDiagnostics::class);
        $summary = $diagnostics->buildAttemptSafeSummary(
            $booking,
            'offer_validation_required',
            'missing_search_criteria',
            true,
            ['error' => 'missing_search_criteria', 'reasons' => ['missing_search_criteria']],
        );

        $this->assertTrue($summary['refresh_attempted']);
        $this->assertFalse($summary['refresh_available']);
        $this->assertFalse($summary['search_criteria_present']);
        $this->assertContains('search_criteria', $summary['missing_context_fields']);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_FRESH_SEARCH,
            $summary['recommended_staff_action'],
        );
        $this->assertStringContainsString(
            'fresh search/booking',
            (string) $summary['refresh_message'],
        );
        $this->assertFalse(
            SabrePnrFailureClassifier::isControlledStaffOfferValidationRetryable('missing_search_criteria', $summary),
        );

        $classified = SabrePnrFailureClassifier::classify('missing_search_criteria', $summary);
        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY,
            $classified['classification'],
        );
        $this->assertFalse($classified['retry_allowed']);
    }

    public function test_transient_refresh_exception_stores_safe_stage_diagnostics(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB', 'booking_class' => 'Y', 'carrier' => 'SV', 'flight_number' => '739'],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => ['offer_ref' => '1', 'leg_refs' => [1], 'schedule_refs' => [1]],
                    ],
                    'fare_breakdown' => ['supplier_total' => 100],
                ],
            ],
        ]);

        $summary = app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            'offer_refresh_failed',
            'offer_refresh_failed',
            true,
            [
                'error' => 'refresh_exception',
                'refresh_stage' => 'calling_flight_search',
                'fresh_search_attempted' => true,
                'fresh_search_result_present' => false,
                'match_attempted' => false,
                'match_found' => false,
                'refresh_exception_class' => 'RuntimeException',
                'refresh_exception_message_safe' => 'simulated supplier search failure',
                'raw_payload' => ['secret' => 'must-not-store'],
            ],
        );

        $this->assertSame('exception', $summary['refresh_status']);
        $this->assertSame('refresh_exception', $summary['refresh_reason_code']);
        $this->assertSame('calling_flight_search', $summary['refresh_stage']);
        $this->assertTrue($summary['fresh_search_attempted']);
        $this->assertFalse($summary['fresh_search_result_present']);
        $this->assertFalse($summary['match_attempted']);
        $this->assertSame('RuntimeException', $summary['refresh_exception_class']);
        $this->assertStringContainsString('simulated supplier search failure', (string) $summary['refresh_exception_message_safe']);
        $this->assertArrayNotHasKey('raw_payload', $summary);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_RETRY_AFTER_COOLDOWN,
            $summary['recommended_staff_action'],
        );
    }

    public function test_transient_refresh_failure_remains_retryable(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB', 'booking_class' => 'Y', 'carrier' => 'SV', 'flight_number' => '739'],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => ['offer_ref' => '1', 'leg_refs' => [1], 'schedule_refs' => [1]],
                    ],
                    'fare_breakdown' => ['supplier_total' => 100],
                ],
            ],
        ]);

        $summary = app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            'offer_refresh_failed',
            'offer_refresh_failed',
            true,
        );

        $this->assertTrue($summary['refresh_attempted']);
        $this->assertTrue($summary['refresh_available']);
        $this->assertSame('exception', $summary['refresh_status']);
        $this->assertSame('refresh_exception', $summary['refresh_reason_code']);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_RETRY_AFTER_COOLDOWN,
            $summary['recommended_staff_action'],
        );
        $this->assertTrue(
            SabrePnrFailureClassifier::isControlledStaffOfferValidationRetryable('offer_refresh_failed', $summary),
        );

        $classified = SabrePnrFailureClassifier::classify('offer_refresh_failed', $summary);
        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_TEMPORARY_PROVIDER_ERROR,
            $classified['classification'],
        );
        $this->assertTrue($classified['retry_allowed']);
    }

    public function test_fare_change_maps_to_fare_acceptance_required(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'segments' => [['origin' => 'LHE', 'destination' => 'DXB', 'booking_class' => 'Y']],
                    'fare_breakdown' => ['supplier_total' => 100],
                ],
            ],
        ]);

        $summary = app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            'offer_validation_required',
            SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
            true,
            ['match_found' => true, 'price_changed' => true, 'applied' => false],
        );

        $this->assertTrue($summary['refresh_price_changed']);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_FARE_ACCEPTANCE,
            $summary['recommended_staff_action'],
        );
        $this->assertSame(SabreOfferRefreshAcceptance::ADMIN_MESSAGE, $summary['refresh_message']);

        $classified = SabrePnrFailureClassifier::classify(
            SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
            $summary,
        );
        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_UPDATED_FARE_REQUIRES_ACCEPTANCE,
            $classified['classification'],
        );
        $this->assertFalse($classified['retry_allowed']);
    }

    public function test_safe_summary_excludes_raw_sabre_response_keys(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'segments' => [['origin' => 'LHE', 'destination' => 'DXB', 'booking_class' => 'Y']],
                ],
            ],
        ]);

        $summary = app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            'offer_validation_required',
            'offer_refresh_unavailable',
            true,
            [
                'match_found' => false,
                'reasons' => ['no_matching_offer_in_shop'],
                'fresh_offer' => ['raw_payload' => ['secret' => 'must-not-store']],
            ],
        );

        $this->assertArrayNotHasKey('fresh_offer', $summary);
        $this->assertArrayNotHasKey('response_payload', $summary);
        $this->assertArrayNotHasKey('raw_payload', $summary);
        $this->assertContains('no_matching_offer_in_shop', $summary['refresh_reasons']);
    }

    public function test_match_failure_records_match_attempted_without_exception(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'JED'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'JED', 'booking_class' => 'Y', 'carrier' => 'PK', 'flight_number' => '751'],
                    ],
                    'fare_breakdown' => ['supplier_total' => 100],
                ],
            ],
        ]);

        $summary = app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            'offer_validation_required',
            'offer_refresh_unavailable',
            true,
            [
                'match_found' => false,
                'match_attempted' => true,
                'fresh_search_attempted' => true,
                'fresh_search_result_present' => true,
                'refresh_stage' => 'matching_itinerary',
                'reasons' => ['no_matching_offer_in_shop'],
            ],
        );

        $this->assertTrue($summary['match_attempted']);
        $this->assertFalse($summary['match_found']);
        $this->assertSame('matching_itinerary', $summary['refresh_stage']);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_FRESH_SEARCH,
            $summary['recommended_staff_action'],
        );
    }

    public function test_exception_message_is_redacted_safely(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => ['supplier_provider' => 'sabre'],
        ]);

        $summary = app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            'offer_refresh_failed',
            'offer_refresh_failed',
            true,
            null,
            new \RuntimeException('Bearer abc123def456 search failure'),
        );

        $this->assertSame('exception', $summary['refresh_status']);
        $this->assertSame('RuntimeException', $summary['refresh_exception_class']);
        $message = (string) ($summary['refresh_exception_message_safe'] ?? '');
        $this->assertStringNotContainsString('abc123def456', $message);
        $this->assertStringContainsString('[REDACTED]', $message);
    }

    public function test_admin_actions_exposes_offer_refresh_diagnostics_panel(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => ['supplier_provider' => 'sabre'],
        ]);
        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'offer_refresh_failed',
            'error_message' => 'We could not confirm this fare with the airline.',
            'safe_summary' => [
                'source' => 'sabre_booking_service',
                'reason' => 'offer_refresh_failed',
                'reason_code' => 'offer_refresh_failed',
                'refresh_attempted' => true,
                'refresh_available' => true,
                'refresh_status' => 'exception',
                'refresh_reason_code' => 'refresh_exception',
                'refresh_message' => 'Offer refresh failed due to a temporary supplier issue. Wait a few minutes, then retry PNR creation.',
                'recommended_staff_action' => ControlledStaffOfferRefreshDiagnostics::ACTION_RETRY_AFTER_COOLDOWN,
                'live_call_attempted' => false,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $state = app(AdminBookingSupplierActions::class)->build($booking->fresh(), true, false);

        $panel = $state['offer_refresh_diagnostics'] ?? null;
        $this->assertIsArray($panel);
        $this->assertTrue($panel['show_panel']);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_RETRY_AFTER_COOLDOWN,
            $panel['recommended_staff_action'],
        );
        $this->assertContains('refresh_attempted', $state['safe_summary_display_keys']);
        $this->assertContains('recommended_staff_action', $state['safe_summary_display_keys']);
    }
}
