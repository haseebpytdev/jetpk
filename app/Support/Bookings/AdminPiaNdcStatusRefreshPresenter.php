<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingStatusRefreshService;
use Carbon\Carbon;

/**
 * Admin booking detail: PIA NDC supplier status refresh panel (R12L).
 */
final class AdminPiaNdcStatusRefreshPresenter
{
    public function __construct(
        private readonly PiaNdcBookingStatusRefreshService $refreshService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function panel(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return ['show' => false];
        }

        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $refreshMeta = is_array($meta['pia_ndc_last_status_refresh'] ?? null) ? $meta['pia_ndc_last_status_refresh'] : [];
        $interpretedStatus = (string) ($refreshMeta['interpreted_status'] ?? $context['interpreted_status'] ?? '—');
        $segmentCount = $refreshMeta['segment_count'] ?? $context['segment_count'] ?? null;
        $hasTicketNumbers = ($refreshMeta['has_ticket_numbers'] ?? $context['has_ticket_numbers'] ?? false) === true;
        $released = ($refreshMeta['released'] ?? $context['option_pnr_released'] ?? false) === true
            || in_array($interpretedStatus, [
                PiaNdcBookingStatusInterpreter::STATUS_RELEASED,
                PiaNdcBookingStatusInterpreter::STATUS_NO_ACTIVE_SEGMENTS,
            ], true);

        return [
            'show' => true,
            'can_refresh' => $this->refreshService->canRefreshBooking($booking),
            'show_stale_warning' => $this->refreshService->shouldWarnStaleStatus($booking),
            'stale_warning' => 'Supplier status may be stale — refresh PIA status.',
            'last_checked_at' => $this->formatTimestamp((string) ($refreshMeta['checked_at'] ?? '')),
            'interpreted_status' => $interpretedStatus,
            'segment_count' => $segmentCount,
            'payment_required_by' => $booking->payment_required_by?->format('d M Y H:i') ?? null,
            'has_ticket_numbers' => $hasTicketNumbers ? 'Yes' : 'No',
            'released' => $released,
            'order_status' => (string) ($refreshMeta['order_status'] ?? $context['order_status'] ?? '—'),
        ];
    }

    private function formatTimestamp(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDayDateTimeString();
        } catch (\Throwable) {
            return $value;
        }
    }
}
