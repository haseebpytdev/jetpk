<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Security\SensitiveDataRedactor;
use Throwable;

/**
 * Local audit trail for PIA NDC CLI/admin ticket preview, ticketing, void, and release (R12R).
 */
final class PiaNdcOperationAuditRecorder
{
    public const META_TICKET_PREVIEW = 'pia_ndc_ticket_preview';

    public const META_TICKETING = 'pia_ndc_ticketing';

    public const META_VOID_TICKET = 'pia_ndc_void_ticket';

    public const META_RELEASE_OPTION_PNR = 'pia_ndc_release_option_pnr';

    public const ACTION_TICKET_PREVIEW = 'pia_ndc_ticket_preview';

    public const ACTION_TICKETING = 'pia_ndc_ticketing';

    public const ACTION_VOID_TICKET = 'pia_ndc_void_ticket';

    public const ACTION_RELEASE_OPTION_PNR = 'pia_ndc_release_option_pnr';

    /**
     * @param  array<string, mixed>  $summary
     */
    public function recordTicketPreview(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        array $summary,
    ): void {
        $this->record(
            booking: $booking,
            connection: $connection,
            actor: $actor,
            action: self::ACTION_TICKET_PREVIEW,
            metaKey: self::META_TICKET_PREVIEW,
            summary: PiaNdcOperationLabels::applyToSummary($summary, 'ticket_preview'),
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function recordTicketing(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        array $summary,
    ): void {
        $this->record(
            booking: $booking,
            connection: $connection,
            actor: $actor,
            action: self::ACTION_TICKETING,
            metaKey: self::META_TICKETING,
            summary: PiaNdcOperationLabels::applyToSummary($summary, 'order_change'),
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function recordVoidTicket(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        array $summary,
    ): void {
        $this->record(
            booking: $booking,
            connection: $connection,
            actor: $actor,
            action: self::ACTION_VOID_TICKET,
            metaKey: self::META_VOID_TICKET,
            summary: PiaNdcOperationLabels::applyToSummary($summary, 'void_ticket'),
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function recordReleaseOptionPnr(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        array $summary,
        ?string $operatorReason = null,
    ): void {
        $summary = PiaNdcOperationLabels::applyToSummary(
            array_merge($summary, [
                'operation' => PiaNdcOperationLabels::DISPLAY_ORDER_CANCEL_COMMIT,
            ]),
            'cancel_commit',
        );
        if ($operatorReason !== null && trim($operatorReason) !== '') {
            $summary['operator_reason'] = trim($operatorReason);
        }

        $this->record(
            booking: $booking,
            connection: $connection,
            actor: $actor,
            action: self::ACTION_RELEASE_OPTION_PNR,
            metaKey: self::META_RELEASE_OPTION_PNR,
            summary: $summary,
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function record(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        string $action,
        string $metaKey,
        array $summary,
    ): void {
        $status = $this->resolveAttemptStatus($summary);
        $sidecar = $this->buildMetaSidecar($summary, $status);
        $safeSummary = SensitiveDataRedactor::redact(array_merge($sidecar, [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'order_id' => $summary['order_id'] ?? null,
            'owner_code' => $summary['owner_code'] ?? null,
            'dry_run' => $summary['dry_run'] ?? false,
            'supplier_called' => $summary['supplier_called'] ?? null,
            'void_status' => $summary['void_status'] ?? null,
            'ticketing_status' => $summary['ticketing_status'] ?? $summary['status'] ?? null,
            'error_code' => $summary['error_code'] ?? null,
            'error_message' => $summary['error_message'] ?? null,
        ]));

        try {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => SupplierProvider::PiaNdc->value,
                'action' => $action,
                'status' => $status,
                'safe_summary' => $safeSummary,
                'supplier_reference' => trim((string) ($summary['order_id'] ?? $booking->supplier_reference ?? '')) ?: null,
                'error_code' => $status === 'success' ? null : (string) ($summary['error_code'] ?? 'operation_failed'),
                'error_message' => $status === 'success'
                    ? null
                    : (string) ($summary['error_message'] ?? 'PIA NDC operation did not succeed.'),
                'attempted_by' => $actor?->id,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $meta[$metaKey] = $sidecar;
            $booking->forceFill(['meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function buildMetaSidecar(array $summary, string $status): array
    {
        $ticketNumbers = is_array($summary['ticket_numbers'] ?? null) ? $summary['ticket_numbers'] : [];
        $attemptedAt = now()->toIso8601String();

        return array_filter([
            'status' => $status,
            'operation' => $summary['operation'] ?? null,
            'ticket_numbers' => $ticketNumbers !== [] ? array_values($ticketNumbers) : null,
            'has_blocking_ticket_numbers' => array_key_exists('has_blocking_ticket_numbers', $summary)
                ? (bool) $summary['has_blocking_ticket_numbers']
                : null,
            'order_status' => isset($summary['order_status']) ? (string) $summary['order_status'] : null,
            'diagnostic_path' => isset($summary['diagnostic_path']) ? (string) $summary['diagnostic_path'] : null,
            'attempted_at' => $attemptedAt,
            'completed_at' => $attemptedAt,
            'void_status' => isset($summary['void_status']) ? (string) $summary['void_status'] : null,
            'ticketing_status' => isset($summary['ticketing_status'])
                ? (string) $summary['ticketing_status']
                : (isset($summary['status']) ? (string) $summary['status'] : null),
            'error_code' => isset($summary['error_code']) ? (string) $summary['error_code'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function resolveAttemptStatus(array $summary): string
    {
        if (($summary['success'] ?? false) === true) {
            return 'success';
        }

        if (($summary['void_status'] ?? '') === 'voided') {
            return 'success';
        }

        if (($summary['ticketing_status'] ?? '') === 'ticket_void_requires_review') {
            return 'failed';
        }

        if (($summary['commit_success'] ?? null) === true || ($summary['success'] ?? null) === true) {
            return 'success';
        }

        return 'failed';
    }
}
