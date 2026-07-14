<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\PiaNdcReleaseOptionPnrService;

/**
 * Admin booking detail: controlled PIA NDC option PNR release action visibility.
 */
final class AdminPiaNdcReleaseOptionPnrPresenter
{
    public function __construct(
        private readonly PiaNdcReleaseOptionPnrService $releaseService,
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
        $canRelease = $this->releaseService->canReleaseBooking($booking);
        $booking->loadMissing('passengers');

        return [
            'show' => true,
            'can_release' => $canRelease,
            'title' => 'PIA NDC option PNR',
            'confirm_phrase' => PiaNdcReleaseOptionPnrService::RELEASE_CONFIRM_PHRASE,
            'pnr' => trim((string) ($booking->pnr ?? '')) !== '' ? (string) $booking->pnr : null,
            'order_id' => (string) ($context['order_id'] ?? $booking->supplier_reference ?? '—'),
            'supplier_reference' => trim((string) ($booking->supplier_reference ?? '')) !== '' ? (string) $booking->supplier_reference : null,
            'owner_code' => (string) ($context['owner_code'] ?? '—'),
            'order_status' => (string) ($context['order_status'] ?? '—'),
            'option_pnr_released' => ($context['option_pnr_released'] ?? false) === true,
            'payment_required_by' => $booking->payment_required_by?->format('d M Y H:i') ?? null,
            'passenger_count' => $booking->passengers->count(),
            'ticket_numbers' => is_array($context['ticket_numbers'] ?? null)
                ? implode(', ', array_map('strval', $context['ticket_numbers']))
                : '—',
        ];
    }
}
