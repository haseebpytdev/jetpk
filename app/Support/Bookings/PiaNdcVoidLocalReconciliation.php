<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingTicket;

/**
 * Local booking/ticket row reconciliation after PIA NDC void (PIA-NDC-OPS1.2).
 */
final class PiaNdcVoidLocalReconciliation
{
    public const TICKETING_STATUS_VOIDED = 'voided';

    public const TICKETING_STATUS_REQUIRES_REVIEW = 'ticket_void_requires_review';

    public const META_LAST_VOID_RESPONSE = 'last_void_response';

    /**
     * @param  array<string, mixed>  $voidResult
     */
    public static function applySuccessfulVoid(Booking $booking, array $voidResult): void
    {
        $booking->loadMissing('tickets');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $voidedAt = now();

        $ticketNumbers = is_array($voidResult['ticket_numbers'] ?? null)
            ? array_values(array_map('strval', $voidResult['ticket_numbers']))
            : (is_array($context['ticket_numbers'] ?? null) ? array_values(array_map('strval', $context['ticket_numbers'])) : []);

        foreach ($booking->tickets as $ticket) {
            $ticket->forceFill([
                'status' => 'voided',
                'void_status' => 'voided',
                'voided_at' => $voidedAt,
            ])->save();
        }

        if ($booking->tickets->isEmpty() && $ticketNumbers !== []) {
            foreach ($ticketNumbers as $ticketNumber) {
                BookingTicket::query()->create([
                    'agency_id' => $booking->agency_id,
                    'booking_id' => $booking->id,
                    'ticket_number' => $ticketNumber,
                    'pnr' => $booking->pnr,
                    'provider' => SupplierProvider::PiaNdc->value,
                    'status' => 'voided',
                    'void_status' => 'voided',
                    'voided_at' => $voidedAt,
                    'issued_at' => $voidedAt,
                ]);
            }
        }

        $sidecar = array_filter([
            'status' => 'success',
            'void_status' => self::TICKETING_STATUS_VOIDED,
            'operation' => $voidResult['operation'] ?? PiaNdcOperationLabels::displayForConfigKey('void_ticket'),
            'ticket_numbers' => $ticketNumbers !== [] ? $ticketNumbers : null,
            'order_status' => $voidResult['order_status'] ?? null,
            'diagnostic_path' => $voidResult['diagnostic_path'] ?? null,
            'completed_at' => $voidedAt->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $meta[self::META_LAST_VOID_RESPONSE] = $sidecar;
        if (! isset($meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET])) {
            $meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET] = $sidecar;
        }

        $booking->forceFill([
            'ticketing_status' => self::TICKETING_STATUS_VOIDED,
            'status' => $booking->status === BookingStatus::Ticketed ? BookingStatus::Ticketed : $booking->status,
            'meta' => $meta,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $voidResult
     */
    public static function applyAmbiguousVoid(Booking $booking, array $voidResult): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $sidecar = array_filter([
            'status' => 'requires_review',
            'void_status' => null,
            'ticketing_status' => self::TICKETING_STATUS_REQUIRES_REVIEW,
            'operation' => $voidResult['operation'] ?? null,
            'error_code' => $voidResult['error_code'] ?? 'void_unconfirmed',
            'error_message' => $voidResult['error_message'] ?? 'Void response is ambiguous; admin review required.',
            'order_status' => $voidResult['order_status'] ?? null,
            'diagnostic_path' => $voidResult['diagnostic_path'] ?? null,
            'completed_at' => now()->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $meta[self::META_LAST_VOID_RESPONSE] = $sidecar;
        $booking->forceFill([
            'ticketing_status' => self::TICKETING_STATUS_REQUIRES_REVIEW,
            'meta' => $meta,
        ])->save();
    }

    public static function isVoided(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $ticketingStatus = strtolower(trim((string) ($booking->ticketing_status ?? '')));

        if (in_array($ticketingStatus, [self::TICKETING_STATUS_VOIDED, 'ticket_voided'], true)) {
            return true;
        }

        if (($context['void_status'] ?? '') === self::TICKETING_STATUS_VOIDED) {
            return true;
        }

        $voidMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET] ?? null)
            ? $meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET]
            : (is_array($meta[self::META_LAST_VOID_RESPONSE] ?? null) ? $meta[self::META_LAST_VOID_RESPONSE] : []);
        if (($voidMeta['void_status'] ?? '') === self::TICKETING_STATUS_VOIDED || ($voidMeta['status'] ?? '') === 'success') {
            return true;
        }

        if ($booking->supplier_booking_status === 'option_pnr_after_void'
            && (($context['interpreted_status'] ?? '') === PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID
                || ($context['void_status'] ?? '') === self::TICKETING_STATUS_VOIDED)) {
            return true;
        }

        $booking->loadMissing('tickets');

        return $booking->tickets->contains(
            fn (BookingTicket $ticket): bool => in_array(strtolower(trim((string) $ticket->status)), ['voided'], true)
                || strtolower(trim((string) ($ticket->void_status ?? ''))) === 'voided',
        );
    }

    public static function requiresVoidReview(Booking $booking): bool
    {
        return strtolower(trim((string) ($booking->ticketing_status ?? ''))) === self::TICKETING_STATUS_REQUIRES_REVIEW;
    }

    /**
     * @return array{should_repair: bool, reason: string}
     */
    public static function voidRepairProposal(Booking $booking): array
    {
        $booking->loadMissing('tickets');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $voidMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET] ?? null)
            ? $meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET]
            : (is_array($meta[self::META_LAST_VOID_RESPONSE] ?? null) ? $meta[self::META_LAST_VOID_RESPONSE] : []);

        $voidEvidence = ($context['void_status'] ?? '') === self::TICKETING_STATUS_VOIDED
            || ($voidMeta['void_status'] ?? '') === self::TICKETING_STATUS_VOIDED
            || ($voidMeta['status'] ?? '') === 'success'
            || $booking->supplier_booking_status === 'option_pnr_after_void';

        if (! $voidEvidence) {
            return ['should_repair' => false, 'reason' => 'no_void_evidence'];
        }

        $ticketRowsNeedRepair = $booking->tickets->contains(
            fn (BookingTicket $ticket): bool => strtolower(trim((string) $ticket->status)) !== 'voided',
        );
        $bookingNeedsRepair = strtolower(trim((string) ($booking->ticketing_status ?? ''))) !== self::TICKETING_STATUS_VOIDED;

        if (! $ticketRowsNeedRepair && ! $bookingNeedsRepair) {
            return ['should_repair' => false, 'reason' => 'already_reconciled'];
        }

        return ['should_repair' => true, 'reason' => 'local_void_repair_needed'];
    }

    /**
     * @return array{applied: bool, reason: string, details: array<string, mixed>}
     */
    public static function repairLocalVoidState(Booking $booking): array
    {
        $proposal = self::voidRepairProposal($booking);
        if (! $proposal['should_repair']) {
            return ['applied' => false, 'reason' => $proposal['reason'], 'details' => []];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $voidMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET] ?? null)
            ? $meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET]
            : (is_array($meta[self::META_LAST_VOID_RESPONSE] ?? null) ? $meta[self::META_LAST_VOID_RESPONSE] : []);

        self::applySuccessfulVoid($booking, array_filter([
            'void_status' => self::TICKETING_STATUS_VOIDED,
            'ticket_numbers' => is_array($context['ticket_numbers'] ?? null) ? $context['ticket_numbers'] : null,
            'operation' => $voidMeta['operation'] ?? null,
            'order_status' => $context['order_status'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        return [
            'applied' => true,
            'reason' => 'local_void_repaired',
            'details' => [
                'ticketing_status' => self::TICKETING_STATUS_VOIDED,
                'ticket_rows' => $booking->fresh()?->tickets->count() ?? 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function diagnosticSnapshot(Booking $booking): array
    {
        $booking->loadMissing('tickets');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $voidMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET] ?? null)
            ? $meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET]
            : (is_array($meta[self::META_LAST_VOID_RESPONSE] ?? null) ? $meta[self::META_LAST_VOID_RESPONSE] : []);

        $ticketRows = $booking->tickets->map(static fn (BookingTicket $ticket): array => [
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'void_status' => $ticket->void_status,
            'voided_at' => $ticket->voided_at?->toIso8601String(),
        ])->values()->all();

        $active = self::isVoided($booking) ? 'voided' : (self::requiresVoidReview($booking) ? 'requires_review' : 'active');

        return [
            'local_ticketing_status' => (string) ($booking->ticketing_status ?? 'not_started'),
            'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? '—'),
            'context_void_status' => (string) ($context['void_status'] ?? '—'),
            'context_ticketing_status' => (string) ($context['ticketing_status'] ?? '—'),
            'has_blocking_ticket_numbers' => (bool) ($context['has_blocking_ticket_numbers'] ?? false),
            'supplier_ticket_numbers' => is_array($context['ticket_numbers'] ?? null) ? $context['ticket_numbers'] : [],
            'latest_void_attempt' => $voidMeta,
            'ticket_rows' => $ticketRows,
            'ticket_active_state' => $active,
            'is_voided' => self::isVoided($booking),
            'requires_void_review' => self::requiresVoidReview($booking),
        ];
    }
}
