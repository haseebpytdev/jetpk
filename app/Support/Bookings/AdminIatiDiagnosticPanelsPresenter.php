<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierDiagnosticLog;

/**
 * Admin booking detail: sanitized IATI supplier diagnostics.
 */
class AdminIatiDiagnosticPanelsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function panel(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        if ($provider !== SupplierProvider::Iati->value) {
            return ['show' => false];
        }

        $iati = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        $latestLog = $connectionId > 0
            ? SupplierDiagnosticLog::query()
                ->where('supplier_connection_id', $connectionId)
                ->where('provider', SupplierProvider::Iati->value)
                ->orderByDesc('id')
                ->first()
            : null;

        $providerContext = IatiPersistedContextResolver::resolveProviderContext($meta, $booking);
        $lifecycle = app(IatiReservationLifecycleService::class)->presentation($booking);

        return [
            'show' => true,
            'title' => 'IATI supplier',
            'fields' => array_merge([
                ['label' => 'Supplier', 'value' => 'IATI'],
                ['label' => 'Reservation source', 'value' => (string) ($lifecycle['reservation_source_label'] ?? '—')],
                ['label' => 'Reservation lifecycle', 'value' => (string) ($lifecycle['lifecycle_label'] ?? '—')],
                ['label' => 'Local checkout expires', 'value' => (string) ($lifecycle['local_checkout_expires_at'] ?? '—')],
                ['label' => 'Supplier hold expires', 'value' => (string) ($lifecycle['supplier_hold_expires_at'] ?? '—')],
                ['label' => 'Fare change pending', 'value' => ($lifecycle['fare_change_requires_acceptance'] ?? false) ? 'Yes' : 'No'],
                ['label' => 'Carrier profile', 'value' => IatiPersistedContextResolver::isAirBlueBooking($meta, $providerContext) ? 'AirBlue (generic IATI path)' : 'Standard IATI'],
                ['label' => 'IATI order ID', 'value' => (string) ($iati['order_id'] ?? $booking->supplier_reference ?? '—')],
                ['label' => 'PNR', 'value' => (string) ($iati['pnr'] ?? $booking->pnr ?? '—')],
                ['label' => 'Airline locator', 'value' => (string) ($iati['airline_locator'] ?? '—')],
                ['label' => 'Mode', 'value' => $this->formatIatiMode((string) ($iati['mode'] ?? ''))],
                ['label' => 'Booking status', 'value' => (string) ($iati['status'] ?? $booking->status->value ?? '—')],
                ['label' => 'Ticketing status', 'value' => (string) ($iati['ticketing_status'] ?? $booking->ticketing_status ?? '—')],
                ['label' => 'Ticket numbers', 'value' => $this->formatTickets($iati['ticket_numbers'] ?? null)],
                ['label' => 'Last sync', 'value' => (string) ($iati['last_sync_status'] ?? '—').' @ '.(string) ($iati['last_sync_at'] ?? '—')],
                ['label' => 'Last provider error', 'value' => (string) ($iati['last_provider_error'] ?? '—')],
                ['label' => 'Last correlation ID', 'value' => (string) ($latestLog?->correlation_id ?? $iati['last_correlation_id'] ?? '—')],
                ['label' => 'Last provider call', 'value' => $latestLog?->created_at?->toDateTimeString() ?? '—'],
                ['label' => 'Offer validation', 'value' => (string) (IatiSelectedOfferReadiness::adminOfferValidationPresentation($booking)['label'] ?? '—')],
            ], IatiSupplierBookingEligibility::diagnosticFields($booking)),
            'offer_validation' => IatiSelectedOfferReadiness::adminOfferValidationPresentation($booking),
        ];
    }

    protected function formatTickets(mixed $tickets): string
    {
        if (! is_array($tickets) || $tickets === []) {
            return '—';
        }

        return implode(', ', array_map('strval', $tickets));
    }

    protected function formatIatiMode(string $mode): string
    {
        return match ($mode) {
            'option' => 'IATI Option / Hold Pending',
            'deferred_book' => 'Direct Book Required',
            'book' => 'Booked',
            '' => '—',
            default => ucfirst(str_replace('_', ' ', $mode)),
        };
    }
}
