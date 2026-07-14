<?php

namespace App\Support\Bookings;

use App\Models\Booking;

final class AdminPiaNdcDiagnosticPanelsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];

        return [
            'provider' => 'pia_ndc',
            'order_id' => (string) ($context['order_id'] ?? $booking->supplier_reference ?? ''),
            'owner_code' => (string) ($context['owner_code'] ?? ''),
            'ticketing_status' => (string) ($context['ticketing_status'] ?? ''),
            'payment_time_limit' => (string) ($context['payment_time_limit'] ?? ''),
            'last_sync_at' => (string) ($context['last_sync_at'] ?? ''),
            'ticket_preview' => is_array($context['ticket_preview'] ?? null) ? $context['ticket_preview'] : null,
        ];
    }
}
