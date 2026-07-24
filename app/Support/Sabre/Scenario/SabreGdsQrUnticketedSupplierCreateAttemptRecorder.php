<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Records a single auditable create_pnr attempt before live dispatch (QR unticketed lifecycle).
 */
final class SabreGdsQrUnticketedSupplierCreateAttemptRecorder
{
    /**
     * @param  array<string, mixed>  $authoritativeDiagnostics
     */
    public function recordStarted(
        Booking $booking,
        SupplierConnection $connection,
        string $lifecycleRunId,
        string $idempotencyKey,
        array $authoritativeDiagnostics,
    ): SupplierBookingAttempt {
        $safeRequestHash = hash('sha256', json_encode([
            'lifecycle_run_id' => $lifecycleRunId,
            'idempotency_key' => $idempotencyKey,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'segment_count' => $authoritativeDiagnostics['segment_count'] ?? null,
            'pricing_amount' => $authoritativeDiagnostics['pricing_amount'] ?? null,
            'pricing_currency' => $authoritativeDiagnostics['pricing_currency'] ?? null,
            'authoritative_candidate_ordinal' => $authoritativeDiagnostics['authoritative_candidate_ordinal'] ?? null,
        ], JSON_THROW_ON_ERROR));

        return SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'started',
            'error_code' => null,
            'error_message' => null,
            'safe_summary' => SensitiveDataRedactor::redact([
                'source' => 'qr_unticketed_lifecycle',
                'lifecycle_run_id' => $lifecycleRunId,
                'idempotency_key' => $idempotencyKey,
                'action' => 'create_pnr',
                'status' => 'started',
                'retry_count' => 0,
                'safe_request_hash' => $safeRequestHash,
                'supplier_connection_id' => $connection->id,
                'live_call_attempted' => false,
                'pnr_attempted' => false,
                'create_request_dispatched' => false,
            ]),
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result  Sabre createBooking / checkout result
     */
    public function completeFromCheckoutResult(
        int $attemptId,
        Booking $booking,
        array $result,
        string $attemptSource,
    ): ?SupplierBookingAttempt {
        $attempt = SupplierBookingAttempt::query()
            ->where('id', $attemptId)
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->first();
        if ($attempt === null) {
            return null;
        }

        $status = (string) ($result['status'] ?? '');
        $success = ($result['success'] ?? false) === true;
        $pnr = trim((string) ($result['pnr'] ?? ''));
        $supplierRef = trim((string) ($result['provider_booking_id'] ?? $result['pnr'] ?? ''));

        $terminalStatus = 'failed';
        $errorCode = null;
        $errorMessage = null;
        if ($status === 'pending_payment_or_ticketing' && $success) {
            $terminalStatus = 'success';
        } elseif ($status === 'needs_review') {
            $terminalStatus = 'needs_review';
            $errorCode = (string) ($result['error_code'] ?? 'needs_review');
            $errorMessage = (string) ($result['message'] ?? 'Sabre PNR needs review.');
        } elseif ($status === 'failed' || ! $success) {
            $terminalStatus = 'failed';
            $errorCode = (string) ($result['error_code'] ?? $result['reason_code'] ?? 'create_pnr_failed');
            $errorMessage = (string) ($result['message'] ?? 'Sabre PNR create failed.');
        }

        $safeSummary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $safeSummary = array_merge($safeSummary, SensitiveDataRedactor::redact([
            'source' => $attemptSource,
            'status' => $terminalStatus,
            'live_call_attempted' => ($result['live_call_attempted'] ?? false) === true,
            'pnr_attempted' => ($result['live_call_attempted'] ?? false) === true,
            'create_request_dispatched' => ($result['live_call_attempted'] ?? false) === true,
            'http_status' => $result['http_status'] ?? null,
            'payload_schema' => $result['payload_schema'] ?? $result['pnr_strategy_used'] ?? null,
            'booking_schema' => $result['booking_schema'] ?? null,
            'reason_code' => $result['reason_code'] ?? $result['error_code'] ?? null,
            'retry_count' => 0,
        ]));

        $attempt->forceFill([
            'status' => $terminalStatus,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'supplier_reference' => $supplierRef !== '' ? substr($supplierRef, 0, 191) : null,
            'safe_summary' => $safeSummary,
            'completed_at' => now(),
        ])->save();

        return $attempt->fresh();
    }
}
