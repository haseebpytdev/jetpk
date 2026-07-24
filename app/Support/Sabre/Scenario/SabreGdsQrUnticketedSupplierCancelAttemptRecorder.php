<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Single-row cancel_pnr attempt tracking for QR unticketed cancellation lifecycle.
 */
final class SabreGdsQrUnticketedSupplierCancelAttemptRecorder
{
    public function recordStarted(
        Booking $booking,
        SupplierConnection $connection,
        string $lifecycleRunId,
        ?string $locatorSha256,
    ): SupplierBookingAttempt {
        return SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_pnr',
            'status' => 'started',
            'safe_summary' => SensitiveDataRedactor::redact([
                'source' => 'qr_unticketed_cancel_lifecycle',
                'lifecycle_run_id' => $lifecycleRunId,
                'action' => 'cancel_pnr',
                'status' => 'started',
                'retry_count' => 0,
                'locator_sha256' => $locatorSha256,
                'supplier_connection_id' => $connection->id,
            ]),
            'attempted_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    public function completeFromCancelOutcome(
        int $attemptId,
        Booking $booking,
        array $outcome,
        string $terminalStatus,
        ?string $classification = null,
    ): ?SupplierBookingAttempt {
        $attempt = SupplierBookingAttempt::query()
            ->where('id', $attemptId)
            ->where('booking_id', $booking->id)
            ->where('action', 'cancel_pnr')
            ->first();
        if ($attempt === null) {
            return null;
        }

        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $safe = array_merge($safe, SensitiveDataRedactor::redact([
            'status' => $terminalStatus,
            'cancellation_classification' => $classification,
            'safe_summary_category' => (string) ($outcome['safe_summary_category'] ?? ''),
            'supplier_call_attempted' => ($outcome['live_call_attempted'] ?? $outcome['supplier_call_attempted'] ?? false) === true,
            'retry_count' => 0,
        ]));

        $attempt->forceFill([
            'status' => $terminalStatus,
            'error_code' => $terminalStatus === 'success' ? null : (string) ($outcome['status'] ?? 'cancel_failed'),
            'error_message' => $terminalStatus === 'success'
                ? null
                : substr((string) ($outcome['message'] ?? 'Cancellation did not complete.'), 0, 2000),
            'safe_summary' => $safe,
            'completed_at' => now(),
        ])->save();

        return $attempt->fresh();
    }
}
