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
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelRetrieveLifecycle;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment;
use App\Support\Sabre\Scenario\SabreGdsQrUnticketedSupplierCancelAttemptRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SabreGdsQrUnticketedPostCancelRetrievePhase15Test extends TestCase
{
    use RefreshDatabase;

    private const PRIOR_LIFECYCLE = '5f265d7f-834f-4f4b-8376-4df358a4e9d7';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['suppliers.sabre.ticketing_enabled' => false]);
    }

    public function test_command_is_registered(): void
    {
        [$booking] = $this->seedBookingWithPriorCancellation('WL96PKN9');
        $this->assertSame(0, Artisan::call('sabre:gds-qr-unticketed-post-cancel-retrieve', [
            '--booking-id' => $booking->id,
            '--supplier-booking-id' => $booking->supplierBookings->first()->id,
            '--prior-cancellation-lifecycle-run-id' => self::PRIOR_LIFECYCLE,
            '--plan' => true,
        ]));
    }

    public function test_plan_mode_zero_supplier_calls_and_zero_db_mutation(): void
    {
        [$booking, $supplierBooking] = $this->seedProductionBookingWithPriorCancellation();
        $attemptsBefore = SupplierBookingAttempt::query()->count();

        Artisan::call('sabre:gds-qr-unticketed-post-cancel-retrieve', [
            '--booking-id' => $booking->id,
            '--supplier-booking-id' => $supplierBooking->id,
            '--prior-cancellation-lifecycle-run-id' => self::PRIOR_LIFECYCLE,
            '--plan' => true,
        ]);

        $this->assertSame($attemptsBefore, SupplierBookingAttempt::query()->count());
        $output = Artisan::output();
        $this->assertStringContainsString('retrieve_planned=true', $output);
        $this->assertStringContainsString('maximum_retrieve_calls=1', $output);
        $this->assertStringContainsString('cancellation_planned=false', $output);
        $booking->refresh();
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNull($booking->cancellation_status);
    }

    public function test_identity_resolver_emits_locator_sha256_for_wl96pkn9(): void
    {
        [$booking] = $this->seedBookingWithPriorCancellation('WL96PKN9');
        $identity = app(\App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelRetrieveIdentityResolver::class)
            ->resolve($booking, null, self::PRIOR_LIFECYCLE, false);
        $this->assertSame(hash('sha256', 'WL96PKN9'), $identity['locator_sha256']);
    }

    public function test_fezjfp_is_rejected(): void
    {
        [$booking, $supplierBooking] = $this->seedBookingWithPriorCancellation('FEZJFP');
        $lifecycle = app(SabreGdsQrUnticketedPostCancelRetrieveLifecycle::class);
        $result = $lifecycle->run([
            'send' => false,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBooking->id,
            'prior_cancellation_lifecycle_run_id' => self::PRIOR_LIFECYCLE,
        ]);
        $this->assertSame('identity_checks_failed', $result['error']);
    }

    public function test_locator_mismatch_blocks_retrieval(): void
    {
        [$booking, $supplierBooking] = $this->seedBookingWithPriorCancellation('WL96PKN9');
        SupplierBooking::query()->where('id', $supplierBooking->id)->update(['pnr' => 'OTHER99']);
        $lifecycle = app(SabreGdsQrUnticketedPostCancelRetrieveLifecycle::class);
        $result = $lifecycle->run([
            'send' => false,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBooking->id,
            'prior_cancellation_lifecycle_run_id' => self::PRIOR_LIFECYCLE,
        ]);
        $this->assertSame('identity_checks_failed', $result['error']);
    }

    public function test_missing_prior_cancellation_blocks_retrieval(): void
    {
        [$booking, $supplierBooking] = $this->seedMatchingSabreBooking('WL96PKN9');
        $lifecycle = app(SabreGdsQrUnticketedPostCancelRetrieveLifecycle::class);
        $result = $lifecycle->run([
            'send' => false,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBooking->id,
            'prior_cancellation_lifecycle_run_id' => self::PRIOR_LIFECYCLE,
        ]);
        $this->assertSame('identity_checks_failed', $result['error']);
    }

    public function test_ambiguous_prior_cancellation_blocks_retrieval(): void
    {
        [$booking, $supplierBooking] = $this->seedBookingWithPriorCancellation('WL96PKN9');
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $booking->meta['supplier_connection_id'],
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_pnr',
            'status' => 'needs_review',
            'attempted_at' => now(),
        ]);
        $lifecycle = app(SabreGdsQrUnticketedPostCancelRetrieveLifecycle::class);
        $result = $lifecycle->run([
            'send' => false,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBooking->id,
            'prior_cancellation_lifecycle_run_id' => self::PRIOR_LIFECYCLE,
        ]);
        $this->assertSame('identity_checks_failed', $result['error']);
    }

    public function test_segment_assessment_accepts_sync_result_map_preview(): void
    {
        $assessment = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class);
        $result = $assessment->assessFromSyncResult([
            'synced' => true,
            'map_preview' => [
                'candidate_segment_count' => 1,
                'candidate_rows' => [['segment_status' => 'HX']],
            ],
        ]);
        $this->assertTrue($result['post_cancel_retrieve_confirmed']);
    }

    public function test_segment_assessment_zero_active_confirms_closure(): void
    {
        $assessment = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class);
        $result = $assessment->assessFromPreview([
            'candidate_segment_count' => 2,
            'candidate_rows' => [
                ['segment_status' => 'HX'],
                ['segment_status' => 'HX'],
            ],
        ]);
        $this->assertTrue($result['post_cancel_retrieve_confirmed']);
        $this->assertSame(0, $result['active_segment_count']);
    }

    public function test_active_segments_prevent_closure(): void
    {
        $assessment = app(SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment::class);
        $result = $assessment->assessFromPreview([
            'candidate_segment_count' => 1,
            'candidate_rows' => [
                ['segment_status' => 'HK'],
            ],
        ]);
        $this->assertFalse($result['post_cancel_retrieve_confirmed']);
        $this->assertGreaterThan(0, $result['active_segment_count']);
    }

    public function test_retrieve_ambiguity_requires_manual_reconciliation(): void
    {
        [$booking, $supplierBooking] = $this->seedProductionBookingWithPriorCancellation();

        $lifecycle = app(SabreGdsQrUnticketedPostCancelRetrieveLifecycle::class);
        $result = $lifecycle->run(array_merge($this->productionSendOptions($booking, $supplierBooking), [
            'test_sync_result' => [
                'error' => 'get_booking_empty',
                'synced' => false,
            ],
        ]));

        $this->assertTrue($result['manual_reconciliation_required']);
        $this->assertSame('retrieve_ambiguous', $result['retrieve_outcome_state']);
        $booking->refresh();
        $this->assertSame(BookingStatus::Pending, $booking->status);
    }

    public function test_confirmed_closure_updates_booking_and_supplier_booking_atomically(): void
    {
        [$booking, $supplierBooking] = $this->seedProductionBookingWithPriorCancellation();

        $lifecycle = app(SabreGdsQrUnticketedPostCancelRetrieveLifecycle::class);
        $result = $lifecycle->run(array_merge($this->productionSendOptions($booking, $supplierBooking), [
            'test_sync_result' => [
                'synced' => true,
                'http_status' => 200,
                'candidate_segment_count' => 1,
                'map_preview' => [
                    'candidate_segment_count' => 1,
                    'candidate_rows' => [['segment_status' => 'HX']],
                ],
            ],
        ]));

        $this->assertTrue($result['post_cancel_retrieve_confirmed']);
        $this->assertTrue($result['cancellation_closure_verified']);
        $booking->refresh();
        $supplierBooking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('cancelled', $booking->cancellation_status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame('WL96PKN9', $booking->pnr);
        $this->assertSame('cancelled', $supplierBooking->status);
    }

    public function test_cancel_attempt_recorder_updates_same_row(): void
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);
        $booking = Booking::factory()->create(['agency_id' => $agency->id]);
        $recorder = app(SabreGdsQrUnticketedSupplierCancelAttemptRecorder::class);
        $started = $recorder->recordStarted($booking, $connection, 'lifecycle', hash('sha256', 'TEST'));
        $recorder->completeFromCancelOutcome($started->id, $booking, [
            'live_call_attempted' => true,
            'success' => true,
            'safe_summary_category' => SabreBookingCancelService::CATEGORY_CANCEL_VERIFIED,
        ], 'success', SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED);

        $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('action', 'cancel_pnr')->count());
        $started->refresh();
        $this->assertSame('success', $started->status);
        $this->assertNotNull($started->completed_at);
    }

    /**
     * @return array{0: Booking, 1: SupplierBooking}
     */
    private function seedBookingWithPriorCancellation(string $pnr): array
    {
        [$booking, $supplierBooking] = $this->seedMatchingSabreBooking($pnr);
        $this->seedConfirmedCancelMeta($booking);
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $booking->meta['supplier_connection_id'],
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_pnr',
            'status' => 'success',
            'safe_summary' => [
                'lifecycle_run_id' => self::PRIOR_LIFECYCLE,
                'classification' => SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        return [$booking->fresh(), $supplierBooking];
    }

    private function seedConfirmedCancelMeta(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreGdsCancelReadiness::META_KEY] = [
            'supplier_cancel_verified' => true,
            'classification' => SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
            'status' => 'cancelled',
        ];
        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @return array{0: Booking, 1: SupplierBooking}
     */
    private function seedMatchingSabreBooking(string $pnr): array
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'supplier_reference' => $pnr,
            'status' => BookingStatus::Pending,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
            ],
        ]);
        $supplierBooking = SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => $pnr,
            'supplier_reference' => $pnr,
            'status' => 'pending_ticketing',
        ]);

        return [$booking->fresh(['supplierBookings']), $supplierBooking];
    }

    /**
     * @return array{0: Booking, 1: SupplierBooking}
     */
    private function seedProductionBookingWithPriorCancellation(): array
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre->value]);
        $booking = Booking::factory()->create([
            'id' => 3,
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'WL96PKN9',
            'supplier_reference' => 'WL96PKN9',
            'status' => BookingStatus::Pending,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
            ],
        ]);
        $supplierBooking = new SupplierBooking([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => 'WL96PKN9',
            'supplier_reference' => 'WL96PKN9',
            'status' => 'pending_ticketing',
        ]);
        $supplierBooking->id = SabreGdsQrUnticketedPostCancelRetrieveLifecycle::PRODUCTION_TARGET_SUPPLIER_BOOKING_ID;
        $supplierBooking->save();
        $this->seedConfirmedCancelMeta($booking);
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_pnr',
            'status' => 'success',
            'safe_summary' => [
                'lifecycle_run_id' => self::PRIOR_LIFECYCLE,
                'classification' => SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        return [$booking->fresh(['supplierBookings']), $supplierBooking];
    }

    /**
     * @return array<string, mixed>
     */
    private function productionSendOptions(Booking $booking, SupplierBooking $supplierBooking): array
    {
        Config::set('app.env', 'production');
        $this->app->detectEnvironment(static fn (): string => 'production');

        return [
            'send' => true,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBooking->id,
            'prior_cancellation_lifecycle_run_id' => self::PRIOR_LIFECYCLE,
            'confirm_production' => SabreGdsQrUnticketedPostCancelRetrieveLifecycle::CONFIRM_PRODUCTION,
            'confirm_retrieve' => SabreGdsQrUnticketedPostCancelRetrieveLifecycle::CONFIRM_RETRIEVE,
            'confirm_no_ticketing' => SabreGdsQrUnticketedPostCancelRetrieveLifecycle::CONFIRM_NO_TICKETING,
        ];
    }
}
