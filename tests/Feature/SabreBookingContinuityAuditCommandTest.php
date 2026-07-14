<?php

namespace Tests\Feature;

use App\Console\Commands\SabreAuditBookingContinuityCommand;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreBookingContinuityAuditor;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Sprint 11K-B/C — passive Sabre booking continuity audit command with host outcome overlay.
 */
class SabreBookingContinuityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_local_runs_without_confirm_and_reports_readonly_safety_lines(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());

        $exit = Artisan::call('sabre:audit-booking-continuity', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('production_readonly_confirmed=false', $out);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $out);
        $this->assertStringContainsString('booking_status_updated=false', $out);
        $this->assertStringContainsString('readiness_recommendation=auto_pnr_safe', $out);
    }

    public function test_production_without_confirm_is_blocked(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:audit-booking-continuity', ['--booking' => '1']);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(
            '--confirm='.SabreAuditBookingContinuityCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            $out
        );
    }

    public function test_production_with_wrong_confirm_is_blocked(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:audit-booking-continuity', [
            '--booking' => '1',
            '--confirm' => 'WRONG-PHRASE',
        ]);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid --confirm phrase', $out);
    }

    public function test_production_with_readonly_confirm_runs_and_reports_safety_lines(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('app.env', 'production');
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());

        $exit = Artisan::call('sabre:audit-booking-continuity', [
            '--booking' => (string) $booking->id,
            '--confirm' => SabreAuditBookingContinuityCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('production_readonly_confirmed=true', $out);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $out);
        $this->assertStringContainsString('booking_status_updated=false', $out);
        $this->assertStringContainsString('readiness_recommendation=auto_pnr_safe', $out);
    }

    public function test_production_json_output_redacts_unsafe_data(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('app.env', 'production');
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());

        $exit = Artisan::call('sabre:audit-booking-continuity', [
            '--booking' => (string) $booking->id,
            '--confirm' => SabreAuditBookingContinuityCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            '--json' => true,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('production_readonly_confirmed=true', $out);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $out);
        $this->assertStringContainsString('booking_status_updated=false', $out);
        $this->assertStringContainsString('booking_continuity_audit_json=', $out);
        $this->assertStringNotContainsString('passport', strtolower($out));
        $this->assertStringNotContainsString('client_secret', strtolower($out));
        $this->assertStringNotContainsString('access_token', strtolower($out));
        $this->assertStringNotContainsString('b16@example.com', $out);
        $this->assertStringNotContainsString('+10000000000', $out);
        $this->assertStringNotContainsString('CreatePassengerNameRecordRQ', $out);
    }

    public function test_one_segment_complete_continuity_reports_auto_pnr_safe(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());

        Artisan::call('sabre:audit-booking-continuity', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();

        $this->assertStringContainsString('readiness_recommendation=auto_pnr_safe', $out);
        $this->assertStringContainsString('segment_count', $out);
        $this->assertStringContainsString('present', $out);
        $this->assertStringNotContainsString('passport', strtolower($out));
        $this->assertStringNotContainsString('client_secret', strtolower($out));
    }

    public function test_two_segment_same_carrier_complete_continuity_reports_auto_pnr_safe(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeTwoSegmentSnapshot());

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('auto_pnr_safe', $report['readiness_recommendation'] ?? null);
        $segmentRow = $this->rowByField($report, 'segment_count');
        $this->assertSame('present', $segmentRow['status'] ?? null);
        $this->assertSame('2', $segmentRow['values_by_source']['normalized_snapshot'] ?? null);
    }

    public function test_missing_rbd_reports_blocked_missing_rbd(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $snapshot = $this->completeOneSegmentSnapshot();
        unset($snapshot['segments'][0]['booking_class']);
        $raw = $snapshot['raw_payload'];
        unset($raw['sabre_booking_context']['booking_classes_by_segment']);
        unset($raw['sabre_shop_context']['booking_classes_by_segment']);
        $raw['sabre_shop_context']['booking_class'] = [];
        $snapshot['raw_payload'] = $raw;
        $booking = $this->makeSabreBooking($snapshot);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('blocked_missing_rbd', $report['readiness_recommendation'] ?? null);
    }

    public function test_missing_fare_basis_reports_blocked_missing_fare_basis(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $snapshot = $this->completeOneSegmentSnapshot();
        unset($snapshot['segments'][0]['fare_basis_code']);
        $raw = $snapshot['raw_payload'];
        unset($raw['sabre_booking_context']['fare_basis_codes_by_segment']);
        unset($raw['sabre_shop_context']['fare_basis_codes_by_segment']);
        $raw['sabre_shop_context']['fare_basis_codes'] = [];
        $snapshot['raw_payload'] = $raw;
        $snapshot['fare_breakdown']['fare_basis_codes'] = [];
        $booking = $this->makeSabreBooking($snapshot);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('blocked_missing_fare_basis', $report['readiness_recommendation'] ?? null);
    }

    public function test_validating_carrier_mismatch_reports_blocked_validating_carrier_mismatch(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $normalized = $this->completeOneSegmentSnapshot();
        $refreshed = $this->completeOneSegmentSnapshot();
        $refreshed['validating_carrier'] = 'QR';
        $refreshed['raw_payload']['sabre_shop_context']['validating_carrier'] = 'QR';
        $refreshed['raw_payload']['sabre_booking_context']['validating_carrier'] = 'QR';
        $booking = $this->makeSabreBooking($normalized, [
            'validated_offer_snapshot' => $refreshed,
            'offer_refresh_status' => 'refreshed',
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('blocked_validating_carrier_mismatch', $report['readiness_recommendation'] ?? null);
    }

    public function test_segment_count_mismatch_reports_blocked_segment_mismatch(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $normalized = $this->completeOneSegmentSnapshot();
        $refreshed = $this->completeTwoSegmentSnapshot();
        $booking = $this->makeSabreBooking($normalized, [
            'validated_offer_snapshot' => $refreshed,
            'offer_refresh_status' => 'refreshed',
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('blocked_segment_mismatch', $report['readiness_recommendation'] ?? null);
    }

    public function test_json_output_redacts_unsafe_data(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());

        Artisan::call('sabre:audit-booking-continuity', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);
        $out = Artisan::output();

        $this->assertStringContainsString('booking_continuity_audit_json=', $out);
        $this->assertStringNotContainsString('passport', strtolower($out));
        $this->assertStringNotContainsString('client_secret', strtolower($out));
        $this->assertStringNotContainsString('access_token', strtolower($out));
        $this->assertStringNotContainsString('b16@example.com', $out);
        $this->assertStringNotContainsString('+10000000000', $out);
        $this->assertStringNotContainsString('CreatePassengerNameRecordRQ', $out);
    }

    public function test_stale_revalidation_after_refresh_reports_blocked_stale_revalidation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $snapshot = $this->completeOneSegmentSnapshot();
        $validated = $this->completeOneSegmentSnapshot();
        $validated['raw_payload']['sabre_shop_context']['itinerary_ref'] = '99';
        $validated['raw_payload']['sabre_booking_context']['itinerary_reference'] = '99';
        $booking = $this->makeSabreBooking($snapshot, [
            'validated_offer_snapshot' => $validated,
            'offer_refresh_status' => 'refreshed',
            'sabre_revalidate_inspect' => [
                'captured_at' => now()->subHour()->toAtomString(),
                'revalidation_success' => true,
                'revalidated_total' => 500,
                'revalidated_currency' => 'USD',
            ],
            SabrePnrCertificationSupport::META_CERTIFICATION_REVALIDATE_LINKAGE => [
                'itinerary_reference' => '10',
                'pricing_information_index' => 2,
                'validating_carrier' => 'EK',
            ],
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('blocked_stale_revalidation', $report['readiness_recommendation'] ?? null);
    }

    public function test_successful_continuity_without_host_failure_remains_auto_pnr_safe(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => [
                'status' => 'pending_payment_or_ticketing',
                'live_call_attempted' => true,
                'error_code' => null,
            ],
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('auto_pnr_safe', $report['readiness_recommendation'] ?? null);
        $this->assertSame('auto_pnr_safe', $report['final_diagnostic_recommendation'] ?? null);
        $this->assertTrue($report['host_outcome_overlay']['host_outcome_present'] ?? false);
        $this->assertFalse($report['host_outcome_overlay']['host_rejected_after_local_continuity'] ?? true);
    }

    public function test_local_continuity_aligned_with_no_fares_host_outcome_blocks_diagnostic(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => $this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER,
                'source_layer' => SabreHostErrorClassifier::LAYER_AIRPRICE,
                'matched_signals' => ['message_contains:no_fares_rbd_carrier'],
            ]),
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('auto_pnr_safe', $report['readiness_recommendation'] ?? null);
        $this->assertSame(
            SabreBookingContinuityAuditor::FINAL_REC_BLOCKED_HOST_REJECTED,
            $report['final_diagnostic_recommendation'] ?? null
        );
        $this->assertSame(
            SabreBookingContinuityAuditor::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
            $report['host_outcome_overlay']['host_error_family'] ?? null
        );
        $this->assertTrue($report['host_outcome_overlay']['local_continuity_aligned'] ?? false);
        $this->assertTrue($report['host_outcome_overlay']['host_rejected_after_local_continuity'] ?? false);
    }

    public function test_local_continuity_aligned_with_nn_host_outcome_blocks_diagnostic(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => $this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
                'host_error_family' => SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
                'source_layer' => SabreHostErrorClassifier::LAYER_AIRBOOK_SELL,
                'matched_signals' => ['airline_segment_status:NN', 'halt_on_status_received:true'],
            ], [
                'airline_segment_status' => 'NN',
                'halt_on_status_received' => true,
            ]),
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('auto_pnr_safe', $report['readiness_recommendation'] ?? null);
        $this->assertSame(
            SabreBookingContinuityAuditor::FINAL_REC_BLOCKED_HOST_REJECTED,
            $report['final_diagnostic_recommendation'] ?? null
        );
        $this->assertSame(
            SabreBookingContinuityAuditor::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            $report['host_outcome_overlay']['host_error_family'] ?? null
        );
        $this->assertTrue($report['host_outcome_overlay']['host_rejected_after_local_continuity'] ?? false);
    }

    public function test_local_continuity_aligned_with_uc_host_outcome_blocks_diagnostic(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => $this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_HOST_SELL_REJECTED_UC,
                'source_layer' => SabreHostErrorClassifier::LAYER_AIRBOOK_SELL,
                'matched_signals' => ['airline_segment_status:UC'],
            ], [
                'airline_segment_status' => 'UC',
                'halt_on_status_received' => true,
            ]),
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('auto_pnr_safe', $report['readiness_recommendation'] ?? null);
        $this->assertSame(
            SabreBookingContinuityAuditor::FINAL_REC_BLOCKED_HOST_REJECTED,
            $report['final_diagnostic_recommendation'] ?? null
        );
        $this->assertSame(
            SabreBookingContinuityAuditor::HOST_ERROR_FAMILY_UC_SEGMENT_STATUS,
            $report['host_outcome_overlay']['host_error_family'] ?? null
        );
        $this->assertTrue($report['host_outcome_overlay']['host_rejected_after_local_continuity'] ?? false);
    }

    public function test_missing_host_outcome_does_not_falsely_block_auto_pnr_safe(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot());

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('auto_pnr_safe', $report['readiness_recommendation'] ?? null);
        $this->assertSame('auto_pnr_safe', $report['final_diagnostic_recommendation'] ?? null);
        $this->assertFalse($report['host_outcome_overlay']['host_outcome_present'] ?? true);
        $this->assertFalse($report['host_outcome_overlay']['host_rejected_after_local_continuity'] ?? true);
    }

    public function test_missing_rbd_still_reports_blocked_missing_rbd_over_host_overlay(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $snapshot = $this->completeOneSegmentSnapshot();
        unset($snapshot['segments'][0]['booking_class']);
        $raw = $snapshot['raw_payload'];
        unset($raw['sabre_booking_context']['booking_classes_by_segment']);
        unset($raw['sabre_shop_context']['booking_classes_by_segment']);
        $raw['sabre_shop_context']['booking_class'] = [];
        $snapshot['raw_payload'] = $raw;
        $booking = $this->makeSabreBooking($snapshot, [
            'sabre_checkout_outcome' => $this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_HOST_SELL_REJECTED_UC,
                'source_layer' => SabreHostErrorClassifier::LAYER_AIRBOOK_SELL,
            ]),
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('blocked_missing_rbd', $report['readiness_recommendation'] ?? null);
        $this->assertSame('blocked_missing_rbd', $report['final_diagnostic_recommendation'] ?? null);
    }

    public function test_json_output_includes_host_overlay_fields(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => $this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER,
                'source_layer' => SabreHostErrorClassifier::LAYER_AIRPRICE,
            ]),
        ]);

        Artisan::call('sabre:audit-booking-continuity', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);
        $out = Artisan::output();

        $this->assertStringContainsString('"host_outcome_overlay"', $out);
        $this->assertStringContainsString('"host_rejected_after_local_continuity":true', $out);
        $this->assertStringContainsString('"final_diagnostic_recommendation":"blocked_host_rejected_after_local_continuity"', $out);
        $this->assertStringContainsString('"host_error_family":"NO_FARES_RBD_CARRIER"', $out);
    }

    public function test_certified_route_pending_with_supplier_ref_does_not_block_host_rejection(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => [
                'status' => 'needs_review',
                'error_code' => SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
            ],
        ]);
        $booking->update(['supplier_reference' => 'AFIAPT']);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking->fresh());

        $this->assertSame('auto_pnr_safe', $report['readiness_recommendation'] ?? null);
        $this->assertSame('auto_pnr_safe', $report['final_diagnostic_recommendation'] ?? null);
        $this->assertFalse($report['host_outcome_overlay']['host_rejection_evidence_present'] ?? true);
        $this->assertFalse($report['host_outcome_overlay']['host_rejected_after_local_continuity'] ?? true);
        $this->assertSame(
            SabreBookingContinuityAuditor::HOST_OUTCOME_STATUS_SKIPPED,
            $report['host_outcome_overlay']['host_outcome_status'] ?? null
        );
    }

    public function test_certified_route_pending_maps_to_certified_route_pending_family(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => [
                'status' => 'needs_review',
                'error_code' => SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
            ],
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame(
            SabreBookingContinuityAuditor::HOST_ERROR_FAMILY_CERTIFIED_ROUTE_PENDING,
            $report['host_outcome_overlay']['host_error_family'] ?? null
        );
        $this->assertFalse($report['host_outcome_overlay']['host_rejection_evidence_present'] ?? true);
        $this->assertSame('auto_pnr_safe', $report['final_diagnostic_recommendation'] ?? null);
    }

    public function test_certified_route_pending_without_supplier_ref_stays_auto_pnr_safe_when_continuity_aligned(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => [
                'status' => 'needs_review',
                'error_code' => SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
            ],
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('auto_pnr_safe', $report['final_diagnostic_recommendation'] ?? null);
        $this->assertFalse($report['host_outcome_overlay']['host_rejected_after_local_continuity'] ?? true);
    }

    public function test_host_overlay_output_redaction_still_passes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'sabre_checkout_outcome' => array_merge($this->failedHostOutcome([
                'safe_reason_code' => SabreHostErrorClassifier::REASON_HOST_SELL_REJECTED_UC,
                'safe_summary' => 'CreatePassengerNameRecordRQ segment UC',
                'source_layer' => SabreHostErrorClassifier::LAYER_AIRBOOK_SELL,
            ]), [
                'response_error_messages' => ['PassengerName leak should not appear in audit json'],
            ]),
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);
        $json = json_encode($report, JSON_UNESCAPED_UNICODE);

        $this->assertIsString($json);
        $this->assertStringNotContainsString('CreatePassengerNameRecordRQ', $json);
        $this->assertStringNotContainsString('PassengerName', $json);
        $this->assertStringNotContainsString('continuity@example.com', $json);
        $this->assertArrayNotHasKey('response_error_messages', $report['host_outcome_overlay'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $classification
     * @param  array<string, mixed>  $checkoutExtra
     * @return array<string, mixed>
     */
    protected function failedHostOutcome(array $classification, array $checkoutExtra = []): array
    {
        return array_merge([
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'live_call_attempted' => true,
            'sabre_host_classification' => array_merge([
                'safe_summary' => 'Sabre host rejected the stored offer.',
                'recommended_admin_action' => 'Re-shop/revalidate before retrying.',
                'retry_policy' => SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER,
                'manual_review_required' => true,
                'matched_signals' => [],
            ], $classification),
        ], $checkoutExtra);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    protected function rowByField(array $report, string $field): array
    {
        foreach ((array) ($report['continuity_rows'] ?? []) as $row) {
            if (is_array($row) && ($row['field'] ?? '') === $field) {
                return $row;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function completeOneSegmentSnapshot(): array
    {
        return [
            'offer_id' => '11k-offer-1',
            'supplier_offer_id' => '11k-offer-1',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'distribution_channel' => 'GDS',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-10-01T10:00:00',
                    'arrival_at' => '2026-10-01T14:00:00',
                    'carrier' => 'EK',
                    'marketing_carrier' => 'EK',
                    'operating_carrier' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLOW',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 500,
                'currency' => 'USD',
                'base_fare' => 400,
                'taxes' => 100,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                'fare_basis_codes' => ['KLOW'],
            ],
            'raw_payload' => [
                'distribution_channel' => 'GDS',
                'shop_endpoint_path' => '/v4/offers/shop',
                'sabre_shop_context' => [
                    'distribution_channel' => 'GDS',
                    'shop_endpoint_path' => '/v4/offers/shop',
                    'itinerary_group_index' => 1,
                    'itinerary_index' => 0,
                    'itinerary_ref' => '10',
                    'itinerary_pricing_index' => 0,
                    'pricing_information_index' => 2,
                    'leg_refs' => [3],
                    'schedule_refs' => [9],
                    'fare_component_refs' => [1],
                    'validating_carrier' => 'EK',
                    'fare_basis_codes' => ['KLOW'],
                    'booking_classes_by_segment' => ['K'],
                    'fare_basis_codes_by_segment' => ['KLOW'],
                ],
                'sabre_booking_context' => [
                    'distribution_channel' => 'GDS',
                    'shop_endpoint_path' => '/v4/offers/shop',
                    'itinerary_reference' => '10',
                    'pricing_information_index' => 2,
                    'validating_carrier' => 'EK',
                    'booking_classes_by_segment' => ['K'],
                    'fare_basis_codes_by_segment' => ['KLOW'],
                    'segment_slice_count' => 1,
                ],
                'sabre_shop_identifiers' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function completeTwoSegmentSnapshot(): array
    {
        $snapshot = $this->completeOneSegmentSnapshot();
        $snapshot['offer_id'] = '11k-offer-2';
        $snapshot['supplier_offer_id'] = '11k-offer-2';
        $snapshot['segments'][] = [
            'origin' => 'DXB',
            'destination' => 'DOH',
            'departure_at' => '2026-10-01T18:00:00',
            'arrival_at' => '2026-10-01T18:45:00',
            'carrier' => 'EK',
            'marketing_carrier' => 'EK',
            'operating_carrier' => 'EK',
            'flight_number' => '847',
            'booking_class' => 'K',
            'fare_basis_code' => 'KLOW2',
        ];
        $snapshot['raw_payload']['sabre_shop_context']['booking_classes_by_segment'] = ['K', 'K'];
        $snapshot['raw_payload']['sabre_shop_context']['fare_basis_codes_by_segment'] = ['KLOW', 'KLOW2'];
        $snapshot['raw_payload']['sabre_shop_context']['fare_basis_codes'] = ['KLOW', 'KLOW2'];
        $snapshot['raw_payload']['sabre_shop_context']['leg_refs'] = [3, 4];
        $snapshot['raw_payload']['sabre_shop_context']['schedule_refs'] = [9, 10];
        $snapshot['raw_payload']['sabre_shop_context']['fare_component_refs'] = [1, 2];
        $snapshot['raw_payload']['sabre_booking_context']['booking_classes_by_segment'] = ['K', 'K'];
        $snapshot['raw_payload']['sabre_booking_context']['fare_basis_codes_by_segment'] = ['KLOW', 'KLOW2'];
        $snapshot['raw_payload']['sabre_booking_context']['segment_slice_count'] = 2;
        $snapshot['fare_breakdown']['fare_basis_codes'] = ['KLOW', 'KLOW2'];

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metaExtra
     */
    protected function makeSabreBooking(array $snapshot, array $metaExtra = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $snapshot['supplier_connection_id'] = $sabreConn->id;

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => '2026-10-01',
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ], $metaExtra),
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'continuity@example.com',
            'phone' => '+10000000001',
            'country' => 'US',
            'address_line' => null,
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 400,
            'taxes' => 100,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 500,
            'currency' => 'USD',
            'breakdown' => [],
        ]);

        return $booking;
    }
}
