<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\User;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrItinerarySyncService;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\DB;

/**
 * Sabre GDS unticketed PNR cancellation orchestration: duplicate protection, cancelBooking workflow, post-cancel sync.
 */
final class SabreGdsCancelService
{
    /** @var array<string, mixed> */
    private array $currentCancelExecutionContext = [];

    public function __construct(
        private readonly SabreBookingCancelService $bookingCancelService,
        private readonly SabreGdsCancelReadiness $readiness,
        private readonly SabrePnrItinerarySyncService $pnrItinerarySyncService,
        private readonly SupplierBookingAttemptGuard $attemptGuard,
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    /**
     * @param  array<string, mixed>  $executionContext
     * @return array<string, mixed>
     */
    public function cancelForBooking(Booking $booking, bool $operatorConfirmed = false, array $executionContext = []): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        if ($this->platformModuleEnforcer->isSabreNdcDistributionChannel($distributionChannel)) {
            return $this->blockedOutcome(
                'sabre_ndc_channel_not_gds_cancel',
                SabreBookingCancelService::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'Sabre NDC bookings use a separate cancellation flow.',
            );
        }

        $readiness = $this->readiness->evaluate($booking);
        if (($readiness['cancelled'] ?? false) === true) {
            return $this->blockedOutcome(
                'already_cancelled',
                SabreBookingCancelService::CATEGORY_CANCEL_VERIFIED,
                'Booking is already cancelled.',
                success: true,
                blockedStatus: 'already_cancelled',
            );
        }

        if (($readiness['in_progress'] ?? false) === true) {
            return $this->blockedOutcome(
                'cancellation_in_progress',
                SabreBookingCancelService::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'Sabre cancellation is already in progress. Do not call cancelBooking again.',
            );
        }

        if (($readiness['ticketed'] ?? false) === true) {
            return $this->blockedOutcome(
                'ticketed_manual_review',
                SabreBookingCancelService::CATEGORY_TICKETED_REFUND_REQUIRED,
                'Ticketed bookings require manual void or refund handling by our team.',
            );
        }

        $lock = $this->attemptGuard->acquireLock($booking, SupplierProvider::Sabre->value, 'cancel_booking');
        if ($lock === null) {
            return $this->blockedOutcome(
                'cancellation_in_progress',
                SabreBookingCancelService::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'Sabre cancellation is already in progress. Do not call cancelBooking again.',
            );
        }

        try {
            $this->currentCancelExecutionContext = $executionContext;

            return $this->runLockedCancel($booking, $operatorConfirmed, $executionContext, $lock);
        } finally {
            $this->currentCancelExecutionContext = [];
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $executionContext
     * @return array<string, mixed>
     */
    protected function runLockedCancel(
        Booking $booking,
        bool $operatorConfirmed,
        array $executionContext,
        Lock $lock,
    ): array {
        unset($lock);

        $this->markInProgress($booking);

        $outcome = $this->bookingCancelService->cancelForBooking($booking, $operatorConfirmed, $executionContext);
        $classification = $this->resolveClassification($outcome);
        $verified = in_array($classification, [
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
        ], true) || ($outcome['status'] ?? '') === 'already_cancelled';

        if ($verified) {
            $outcome = $this->finalizeVerifiedCancel($booking, $outcome, $classification, $executionContext);
            $this->completeInProgressAttempt($booking, 'success', $outcome);
        } else {
            $this->clearInProgress($booking, 'failed', $outcome);
        }

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $executionContext
     * @return array<string, mixed>
     */
    protected function finalizeVerifiedCancel(
        Booking $booking,
        array $outcome,
        string $classification,
        array $executionContext,
    ): array {
        $booking->refresh();
        $syncSlice = ($executionContext['skip_post_cancel_retrieve'] ?? false) === true
            ? [
                'attempted' => false,
                'skipped' => true,
                'reason' => 'deferred_to_qr_unticketed_retrieve_phase',
            ]
            : $this->postCancelSync($booking);
        $segmentStatuses = $this->extractSegmentStatuses($booking, $syncSlice, $outcome);

        $persisted = $this->persistCancelMeta($booking, $outcome, $classification, $segmentStatuses, $syncSlice);
        $this->writeAdminAudit($booking, $executionContext, $persisted);

        if (($executionContext['defer_local_cancellation_closure'] ?? false) !== true) {
            app(SabreGdsCancellationReconciliationService::class)->reconcileFromStoredEvidence(
                $booking->fresh(),
                array_merge($executionContext, ['source' => 'sabre_gds_cancel_finalize']),
            );
        }

        return array_merge($outcome, [
            'sabre_gds_cancel' => $persisted,
            'post_cancel_sync' => $syncSlice,
            'airline_segment_statuses' => $segmentStatuses,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function postCancelSync(Booking $booking): array
    {
        try {
            $sync = $this->pnrItinerarySyncService->sync($booking, false);

            return [
                'attempted' => true,
                'synced' => (bool) ($sync['synced'] ?? false),
                'status' => (string) ($sync['reason_code'] ?? ($sync['synced'] ?? false ? 'synced' : 'not_synced')),
                'partial_sync' => (bool) ($sync['partial_sync'] ?? false),
                'post_cancel_segment_count' => isset($sync['candidate_segment_count']) && is_numeric($sync['candidate_segment_count'])
                    ? (int) $sync['candidate_segment_count']
                    : null,
            ];
        } catch (\Throwable) {
            return [
                'attempted' => true,
                'synced' => false,
                'status' => 'sync_failed',
                'partial_sync' => false,
                'post_cancel_segment_count' => null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $syncSlice
     * @param  array<string, mixed>  $outcome
     * @return list<string>
     */
    protected function extractSegmentStatuses(Booking $booking, array $syncSlice, array $outcome): array
    {
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $statuses = [];

        $snapshot = is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : [];
        foreach ((array) ($snapshot['segments'] ?? []) as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $status = strtoupper(trim((string) ($segment['segment_status'] ?? '')));
            if ($status !== '') {
                $statuses[] = $status;
            }
        }

        if ($statuses === []) {
            $post = is_array($outcome['post_cancel_verification'] ?? null) ? $outcome['post_cancel_verification'] : [];
            $segmentCount = (int) ($post['post_cancel_segment_count'] ?? $syncSlice['post_cancel_segment_count'] ?? 0);
            if ($segmentCount === 0) {
                $statuses[] = 'HX';
            }
        }

        return array_values(array_unique($statuses));
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  list<string>  $segmentStatuses
     * @param  array<string, mixed>  $syncSlice
     * @return array<string, mixed>
     */
    protected function persistCancelMeta(
        Booking $booking,
        array $outcome,
        string $classification,
        array $segmentStatuses,
        array $syncSlice,
    ): array {
        return DB::transaction(function () use ($booking, $outcome, $classification, $segmentStatuses, $syncSlice): array {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $post = is_array($outcome['post_cancel_verification'] ?? null) ? $outcome['post_cancel_verification'] : [];

            $slice = SensitiveDataRedactor::redact([
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
                'classification' => $classification,
                'safe_summary_category' => (string) ($outcome['safe_summary_category'] ?? SabreBookingCancelService::CATEGORY_CANCEL_VERIFIED),
                'supplier_cancel_verified' => true,
                'post_cancel_segment_count' => isset($post['post_cancel_segment_count']) && is_numeric($post['post_cancel_segment_count'])
                    ? (int) $post['post_cancel_segment_count']
                    : ($syncSlice['post_cancel_segment_count'] ?? null),
                'airline_segment_statuses' => $segmentStatuses,
                'post_cancel_sync_status' => (string) ($syncSlice['status'] ?? 'not_attempted'),
                'post_cancel_synced' => (bool) ($syncSlice['synced'] ?? false),
                'cancel_response_digest' => md5(json_encode([
                    'classification' => $classification,
                    'http_status' => $post['http_status'] ?? ($outcome['cancel_probe']['http_status'] ?? null),
                    'payload_style' => $outcome['payload_style'] ?? null,
                ])),
            ]);

            $meta[SabreGdsCancelReadiness::META_KEY] = $slice;
            $booking->forceFill(['meta' => $meta])->save();

            return $slice;
        });
    }

    /**
     * @param  array<string, mixed>  $executionContext
     * @param  array<string, mixed>  $persisted
     */
    protected function writeAdminAudit(Booking $booking, array $executionContext, array $persisted): void
    {
        try {
            $actor = $executionContext['actor'] ?? null;
            $userId = $actor instanceof User ? $actor->id : null;

            AuditLog::query()->create([
                'agency_id' => $booking->agency_id,
                'user_id' => $userId,
                'action' => 'booking.sabre_gds_cancel_confirmed',
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'properties' => [
                    'old_values' => [],
                    'new_values' => [
                        'classification' => $persisted['classification'] ?? null,
                        'post_cancel_segment_count' => $persisted['post_cancel_segment_count'] ?? null,
                        'airline_segment_statuses' => $persisted['airline_segment_statuses'] ?? [],
                        'post_cancel_sync_status' => $persisted['post_cancel_sync_status'] ?? null,
                    ],
                ],
            ]);
        } catch (\Throwable) {
            // Audit must not block cancellation completion.
        }
    }

    protected function markInProgress(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $meta[SabreGdsCancelReadiness::META_KEY] = array_merge(
                is_array($meta[SabreGdsCancelReadiness::META_KEY] ?? null) ? $meta[SabreGdsCancelReadiness::META_KEY] : [],
                [
                    'status' => 'in_progress',
                    'started_at' => now()->toIso8601String(),
                ],
            );
            $booking->forceFill(['meta' => $meta])->save();
        });

        if (($this->currentCancelExecutionContext['skip_internal_cancel_booking_attempt_rows'] ?? false) === true) {
            return;
        }

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => (int) (data_get($booking->meta, 'supplier_connection_id') ?? 0) ?: null,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_booking',
            'status' => 'in_progress',
            'request_payload' => null,
            'response_payload' => null,
            'safe_summary' => [
                'source' => 'sabre_gds_cancel_workflow',
                'phase' => 'in_progress',
            ],
            'attempted_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function completeInProgressAttempt(Booking $booking, string $terminalStatus, array $outcome): void
    {
        if (($this->currentCancelExecutionContext['skip_internal_cancel_booking_attempt_rows'] ?? false) === true) {
            return;
        }

        SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->where('action', 'cancel_booking')
            ->whereIn('status', SupplierBookingAttemptGuard::ACTIVE_STATUSES)
            ->whereNull('completed_at')
            ->update([
                'status' => $terminalStatus === 'success' ? 'success' : 'attempted',
                'completed_at' => now(),
                'safe_summary' => SensitiveDataRedactor::redact([
                    'source' => 'sabre_gds_cancel_workflow',
                    'phase' => 'completed',
                    'terminal_status' => $terminalStatus,
                    'safe_summary_category' => (string) ($outcome['safe_summary_category'] ?? ''),
                    'classification' => $this->resolveClassification($outcome),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function clearInProgress(Booking $booking, string $terminalStatus, array $outcome): void
    {
        DB::transaction(function () use ($booking, $terminalStatus, $outcome): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $existing = is_array($meta[SabreGdsCancelReadiness::META_KEY] ?? null)
                ? $meta[SabreGdsCancelReadiness::META_KEY]
                : [];

            if (($existing['status'] ?? '') !== 'cancelled') {
                $meta[SabreGdsCancelReadiness::META_KEY] = array_merge($existing, [
                    'status' => $terminalStatus,
                    'ended_at' => now()->toIso8601String(),
                    'safe_summary_category' => (string) ($outcome['safe_summary_category'] ?? ''),
                    'classification' => $this->resolveClassification($outcome),
                ]);
                $booking->forceFill(['meta' => $meta])->save();
            }

            if (($this->currentCancelExecutionContext['skip_internal_cancel_booking_attempt_rows'] ?? false) === true) {
                return;
            }

            SupplierBookingAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('provider', SupplierProvider::Sabre->value)
                ->where('action', 'cancel_booking')
                ->whereIn('status', SupplierBookingAttemptGuard::ACTIVE_STATUSES)
                ->whereNull('completed_at')
                ->update([
                    'status' => $terminalStatus === 'failed' ? 'failed' : 'attempted',
                    'completed_at' => now(),
                    'error_code' => $terminalStatus === 'failed'
                        ? (string) ($outcome['status'] ?? 'cancel_not_verified')
                        : null,
                    'safe_summary' => SensitiveDataRedactor::redact([
                        'source' => 'sabre_gds_cancel_workflow',
                        'phase' => 'completed',
                        'terminal_status' => $terminalStatus,
                        'safe_summary_category' => (string) ($outcome['safe_summary_category'] ?? ''),
                    ]),
                ]);
        });
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function resolveClassification(array $outcome): string
    {
        $post = is_array($outcome['post_cancel_verification'] ?? null) ? $outcome['post_cancel_verification'] : [];
        $classification = (string) ($post['classification'] ?? $outcome['classification'] ?? '');

        return trim($classification);
    }

    /**
     * @return array<string, mixed>
     */
    protected function blockedOutcome(
        string $status,
        string $category,
        string $message,
        bool $success = false,
        ?string $blockedStatus = null,
    ): array {
        return SensitiveDataRedactor::redact([
            'success' => $success,
            'status' => $blockedStatus ?? $status,
            'safe_summary_category' => $category,
            'message' => $message,
            'live_call_attempted' => false,
            'supplier_cancel_verified' => $success && $status === 'already_cancelled',
            'sabre_cancel_execution_blocked_reason' => $status,
            'sabre_cancel_precheck_status' => $status,
            'sabre_cancel_classification' => strtoupper($status),
            'classification' => strtoupper($status),
        ]);
    }
}
