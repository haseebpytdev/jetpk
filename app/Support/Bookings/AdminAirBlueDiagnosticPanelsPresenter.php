<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierDiagnosticLog;

/**
 * Admin booking detail: sanitized AirBlue supplier diagnostics.
 */
class AdminAirBlueDiagnosticPanelsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function panel(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        if ($provider !== SupplierProvider::Airblue->value) {
            return ['show' => false];
        }

        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        $latestLog = $connectionId > 0
            ? SupplierDiagnosticLog::query()
                ->where('supplier_connection_id', $connectionId)
                ->where('provider', SupplierProvider::Airblue->value)
                ->orderByDesc('id')
                ->first()
            : null;

        return [
            'show' => true,
            'title' => 'AirBlue supplier',
            'fields' => [
                ['label' => 'Supplier', 'value' => 'AirBlue'],
                ['label' => 'API channel', 'value' => (string) ($context['api_channel'] ?? '—')],
                ['label' => 'Order ID', 'value' => (string) ($context['order_id'] ?? $booking->supplier_reference ?? '—')],
                ['label' => 'PNR', 'value' => (string) ($context['pnr'] ?? $booking->pnr ?? '—')],
                ['label' => 'Instance', 'value' => (string) ($context['instance'] ?? '—')],
                ['label' => 'Owner code', 'value' => (string) ($context['owner_code'] ?? '—')],
                ['label' => 'Ticketing status', 'value' => (string) ($context['ticketing_status'] ?? $booking->ticketing_status ?? '—')],
                ['label' => 'Payment time limit', 'value' => (string) ($context['payment_time_limit'] ?? '—')],
                ['label' => 'Ticket numbers', 'value' => $this->formatTickets($context['ticket_numbers'] ?? null)],
                ['label' => 'Last sync', 'value' => (string) ($context['last_sync_at'] ?? '—')],
                ['label' => 'Last correlation ID', 'value' => (string) ($latestLog?->correlation_id ?? $context['correlation_id'] ?? '—')],
                ['label' => 'Last provider call', 'value' => $latestLog?->created_at?->toDateTimeString() ?? '—'],
            ],
        ];
    }

    protected function formatTickets(mixed $tickets): string
    {
        if (! is_array($tickets) || $tickets === []) {
            return '—';
        }

        return implode(', ', array_map('strval', $tickets));
    }
}
