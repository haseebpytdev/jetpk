<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;

/**
 * Corrects persisted pnr_retrieve attempt row after Phase 16 zero-segment replay closure (in-place).
 */
final class SabreGdsQrUnticketedPostCancelRetrieveAttemptCorrectionService
{
    public function applySuccessCorrection(int $attemptId, int $bookingId, string $replayLifecycleRunId): ?SupplierBookingAttempt
    {
        return DB::transaction(function () use ($attemptId, $bookingId, $replayLifecycleRunId): ?SupplierBookingAttempt {
            $attempt = SupplierBookingAttempt::query()
                ->where('id', $attemptId)
                ->where('booking_id', $bookingId)
                ->where('provider', SupplierProvider::Sabre->value)
                ->where('action', 'pnr_retrieve')
                ->lockForUpdate()
                ->first();
            if ($attempt === null) {
                return null;
            }

            $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
            $originalStatus = (string) ($attempt->status ?? '');
            $originalErrorCode = (string) ($attempt->error_code ?? '');

            $safe = array_merge($safe, SensitiveDataRedactor::redact([
                'classification_corrected' => true,
                'classification_correction_phase' => 16,
                'original_status' => $originalStatus,
                'original_error_code' => $originalErrorCode,
                'closure_verified_from_prior_cancel_and_zero_segments' => true,
                'replay_lifecycle_run_id' => $replayLifecycleRunId,
            ]));

            $attempt->forceFill([
                'status' => 'success',
                'error_code' => null,
                'error_message' => null,
                'safe_summary' => $safe,
                'completed_at' => $attempt->completed_at ?? now(),
            ])->save();

            return $attempt->fresh();
        });
    }
}
