<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedCancelLifecycle;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelReplayLifecycle;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelRetrieveLifecycle;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SabreGdsQrUnticketedPostCancelZeroSegmentPhase16Test extends TestCase
{
    use RefreshDatabase;

    private const PRIOR_LIFECYCLE = SabreGdsQrUnticketedPostCancelReplayLifecycle::PRODUCTION_PRIOR_CANCELLATION_LIFECYCLE_RUN_ID;

    private const RETRIEVE_LIFECYCLE = SabreGdsQrUnticketedPostCancelReplayLifecycle::PRODUCTION_POST_CANCEL_RETRIEVE_LIFECYCLE_RUN_ID;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['suppliers.sabre.ticketing_enabled' => false]);
    }

    public function test_http_200_zero_segments_with_prior_cancel_confirms_closure(): void
    {
        [$context, $safe] = $this->productionEvidenceFixtures();
        $assessment = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class);
        $result = $assessment->assessFromPersistedSafeSummary($safe, $context, [
            'retrieve_request_dispatched' => true,
            'retrieve_response_received' => true,
            'supplier_retrieve_call_count' => 1,
        ]);

        $this->assertTrue($result['post_cancel_retrieve_confirmed']);
        $this->assertSame('retrieve_confirmed', $result['retrieve_outcome_state']);
        $this->assertSame(0, $result['active_segment_count']);
        $this->assertFalse($result['manual_reconciliation_required']);
        $this->assertSame(
            SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier::REASON_ZERO_SEGMENT_PRIOR_CANCEL_CONFIRMED,
            $result['assessment_reason'],
        );
    }

    public function test_locator_absent_in_safe_summary_does_not_block_zero_segment_closure(): void
    {
        [$context, $safe] = $this->productionEvidenceFixtures();
        $safe['airline_locator_present'] = false;
        $safe['sabre_record_locator_present'] = false;
        $assessment = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class);
        $result = $assessment->assessFromPersistedSafeSummary($safe, $context, [
            'supplier_retrieve_call_count' => 1,
        ]);
        $this->assertTrue($result['post_cancel_retrieve_confirmed']);
    }

    public function test_safe_to_map_preview_false_does_not_block_when_zero_segments_and_prior_cancel(): void
    {
        [$context, $safe] = $this->productionEvidenceFixtures();
        $safe['safe_to_map_preview'] = false;
        $result = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class)
            ->assessFromPersistedSafeSummary($safe, $context, ['supplier_retrieve_call_count' => 1]);
        $this->assertTrue($result['post_cancel_retrieve_confirmed']);
    }

    public function test_prior_cancellation_missing_blocks_closure(): void
    {
        [$context, $safe] = $this->productionEvidenceFixtures();
        $context['prior_cancellation_confirmed'] = false;
        $result = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class)
            ->assessFromPersistedSafeSummary($safe, $context, ['supplier_retrieve_call_count' => 1]);
        $this->assertFalse($result['post_cancel_retrieve_confirmed']);
    }

    public function test_resource_unavailable_blocks_closure(): void
    {
        [$context, $safe] = $this->productionEvidenceFixtures();
        $safe['resource_unavailable_present'] = true;
        $result = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class)
            ->assessFromPersistedSafeSummary($safe, $context, ['supplier_retrieve_call_count' => 1]);
        $this->assertFalse($result['post_cancel_retrieve_confirmed']);
    }

    public function test_non_200_blocks_closure(): void
    {
        [$context, $safe] = $this->productionEvidenceFixtures();
        $safe['http_status'] = 500;
        $result = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class)
            ->assessFromPersistedSafeSummary($safe, $context, ['supplier_retrieve_call_count' => 1]);
        $this->assertFalse($result['post_cancel_retrieve_confirmed']);
    }

    public function test_active_segments_block_closure(): void
    {
        [$context, $safe] = $this->productionEvidenceFixtures();
        $safe['segment_count'] = 1;
        $safe['mappable_segment_count'] = 1;
        $result = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class)
            ->assessFromPersistedSafeSummary($safe, $context, ['supplier_retrieve_call_count' => 1]);
        $this->assertFalse($result['post_cancel_retrieve_confirmed']);
    }

    public function test_dry_run_replay_zero_db_mutation(): void
    {
        [$booking, $supplierBooking, $attempt] = $this->seedProductionReplayFixtures();
        $attemptsBefore = SupplierBookingAttempt::query()->count();
        $lifecycle = app(SabreGdsQrUnticketedPostCancelReplayLifecycle::class);
        $result = $lifecycle->run([
            'apply_local_closure' => false,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBooking->id,
            'prior_cancellation_lifecycle_run_id' => self::PRIOR_LIFECYCLE,
            'post_cancel_retrieve_lifecycle_run_id' => self::RETRIEVE_LIFECYCLE,
            'retrieve_attempt_id' => $attempt->id,
        ]);
        $this->assertTrue($result['post_cancel_retrieve_confirmed']);
        $this->assertFalse($result['database_mutation_detected']);
        $this->assertSame($attemptsBefore, SupplierBookingAttempt::query()->count());
        $booking->refresh();
        $this->assertSame(BookingStatus::Pending, $booking->status);
    }

    public function test_apply_local_closure_updates_booking_supplier_booking_and_attempt_nine(): void
    {
        [$booking, $supplierBooking, $attempt] = $this->seedProductionReplayFixtures();
        $lifecycle = app(SabreGdsQrUnticketedPostCancelReplayLifecycle::class);
        $result = $lifecycle->run(array_merge($this->applyOptions($booking, $supplierBooking, $attempt->id), [
            'apply_local_closure' => true,
        ]));
        $this->assertArrayNotHasKey('error', $result, json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertTrue($result['cancellation_closure_verified']);
        $booking->refresh();
        $supplierBooking->refresh();
        $attempt->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('cancelled', $booking->cancellation_status);
        $this->assertSame('WL96PKN9', $booking->pnr);
        $this->assertSame('cancelled', $supplierBooking->status);
        $this->assertSame('success', $attempt->status);
        $this->assertNull($attempt->error_code);
        $this->assertTrue($attempt->safe_summary['classification_corrected'] ?? false);
        $this->assertSame('needs_review', $attempt->safe_summary['original_status'] ?? null);
        $this->assertSame('unmappable', $attempt->safe_summary['original_error_code'] ?? null);
    }

    public function test_replay_command_registered_dry_run(): void
    {
        [$booking, $supplierBooking, $attempt] = $this->seedProductionReplayFixtures();
        $this->assertSame(0, Artisan::call('sabre:gds-qr-unticketed-post-cancel-replay', [
            '--booking-id' => $booking->id,
            '--supplier-booking-id' => $supplierBooking->id,
            '--prior-cancellation-lifecycle-run-id' => self::PRIOR_LIFECYCLE,
            '--post-cancel-retrieve-lifecycle-run-id' => self::RETRIEVE_LIFECYCLE,
            '--retrieve-attempt-id' => $attempt->id,
            '--dry-run' => true,
        ]));
        $this->assertStringContainsString('retrieve_outcome_state=retrieve_confirmed', Artisan::output());
    }

    public function test_fezjfp_rejected_in_replay_identity(): void
    {
        [$booking, $supplierBooking, $attempt] = $this->seedProductionReplayFixtures('FEZJFP');
        $lifecycle = app(SabreGdsQrUnticketedPostCancelReplayLifecycle::class);
        $result = $lifecycle->run([
            'apply_local_closure' => false,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBooking->id,
            'prior_cancellation_lifecycle_run_id' => self::PRIOR_LIFECYCLE,
            'post_cancel_retrieve_lifecycle_run_id' => self::RETRIEVE_LIFECYCLE,
            'retrieve_attempt_id' => $attempt->id,
        ]);
        $this->assertSame('identity_checks_failed', $result['error']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function productionEvidenceFixtures(): array
    {
        $locatorSha = hash('sha256', 'WL96PKN9');
        $context = [
            'prior_cancellation_confirmed' => true,
            'prior_cancellation_ambiguous' => false,
            'prior_supplier_cancellation_call_count' => 1,
            'booking_id' => 3,
            'supplier_booking_id' => 2,
            'expected_booking_id' => 3,
            'expected_supplier_booking_id' => 2,
            'booking_pnr_present' => true,
            'supplier_pnr_present' => true,
            'locator_matches' => true,
            'locator_denylisted' => false,
            'locator_sha256' => $locatorSha,
            'prior_cancellation_artifact_locator_sha256' => $locatorSha,
            'ticket_number_count' => 0,
            'ticketing_enabled' => false,
        ];
        $safe = [
            'source' => 'sabre_sync_pnr_itinerary',
            'endpoint_path' => '/v1/trip/orders/getBooking',
            'http_status' => 200,
            'segment_count' => 0,
            'mappable_segment_count' => 0,
            'safe_to_map_preview' => false,
            'resource_unavailable_present' => false,
            'airline_locator_present' => false,
            'sabre_record_locator_present' => false,
        ];

        return [$context, $safe];
    }

    /**
     * @return array{0: Booking, 1: SupplierBooking, 2: SupplierBookingAttempt}
     */
    private function seedProductionReplayFixtures(string $pnr = 'WL96PKN9'): array
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);
        $locatorSha = hash('sha256', $pnr);
        $booking = Booking::factory()->create([
            'id' => 3,
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'supplier_reference' => $pnr,
            'status' => BookingStatus::Pending,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
                SabreGdsCancelReadiness::META_KEY => [
                    'supplier_cancel_verified' => true,
                    'classification' => SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
                ],
            ],
        ]);
        $supplierBooking = new SupplierBooking([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'supplier_reference' => $pnr,
            'status' => 'pending_ticketing',
        ]);
        $supplierBooking->id = 2;
        $supplierBooking->save();

        $attempt = new SupplierBookingAttempt([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'pnr_retrieve',
            'status' => 'needs_review',
            'error_code' => 'unmappable',
            'safe_summary' => [
                'source' => 'sabre_sync_pnr_itinerary',
                'http_status' => 200,
                'segment_count' => 0,
                'mappable_segment_count' => 0,
                'safe_to_map_preview' => false,
                'resource_unavailable_present' => false,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
        $attempt->id = 9;
        $attempt->save();

        Storage::disk('local')->put(
            SabreGdsQrUnticketedCancelLifecycle::ARTIFACT_DIRECTORY.'/'.self::PRIOR_LIFECYCLE.'-send.json',
            json_encode([
                'cancellation_outcome_state' => 'cancellation_confirmed',
                'supplier_cancellation_call_count' => 1,
                'manual_reconciliation_required' => false,
                'locator_sha256' => $locatorSha,
            ], JSON_THROW_ON_ERROR),
        );
        Storage::disk('local')->put(
            SabreGdsQrUnticketedPostCancelRetrieveLifecycle::ARTIFACT_DIRECTORY.'/'.self::RETRIEVE_LIFECYCLE.'-send.json',
            json_encode([
                'retrieve_request_dispatched' => true,
                'retrieve_response_received' => true,
                'supplier_retrieve_call_count' => 1,
            ], JSON_THROW_ON_ERROR),
        );

        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_pnr',
            'status' => 'success',
            'completed_at' => now(),
        ]);

        return [$booking->fresh(), $supplierBooking, $attempt];
    }

    /**
     * @return array<string, mixed>
     */
    private function applyOptions(Booking $booking, SupplierBooking $supplierBooking, int $attemptId): array
    {
        Config::set('app.env', 'production');
        $this->app->detectEnvironment(static fn (): string => 'production');

        return [
            'booking_id' => $booking->id,
            'supplier_booking_id' => SabreGdsQrUnticketedPostCancelReplayLifecycle::PRODUCTION_SUPPLIER_BOOKING_ID,
            'prior_cancellation_lifecycle_run_id' => self::PRIOR_LIFECYCLE,
            'post_cancel_retrieve_lifecycle_run_id' => self::RETRIEVE_LIFECYCLE,
            'retrieve_attempt_id' => SabreGdsQrUnticketedPostCancelReplayLifecycle::PRODUCTION_RETRIEVE_ATTEMPT_ID,
            'confirm_local_closure' => SabreGdsQrUnticketedPostCancelReplayLifecycle::CONFIRM_LOCAL_CLOSURE,
            'confirm_replay_booking' => SabreGdsQrUnticketedPostCancelReplayLifecycle::CONFIRM_REPLAY_BOOKING,
        ];
    }
}
