<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingTicket;

/**
 * Void/refund readiness for GDS ticket servicing (controlled, default off).
 */
final class SabreGdsTicketServicingReadiness
{
    /**
     * @return array<string, mixed>
     */
    public function evaluateVoid(Booking $booking, ?string $ticketNumber = null): array
    {
        return $this->evaluate($booking, 'void', $ticketNumber);
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluateRefund(Booking $booking, ?string $ticketNumber = null): array
    {
        return $this->evaluate($booking, 'refund', $ticketNumber);
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluate(Booking $booking, string $action, ?string $ticketNumber): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $blockers = [];

        if ($provider !== SupplierProvider::Sabre->value) {
            $blockers[] = 'supplier_not_sabre';
        }

        $ticket = $this->resolveTicket($booking, $ticketNumber);
        if ($ticket === null) {
            $blockers[] = 'ticket_not_found';
        }

        $envKey = $action === 'void' ? 'void_enabled' : 'refund_enabled';
        $liveKey = $action === 'void' ? 'void_live_call_enabled' : 'refund_live_call_enabled';

        if (! (bool) config('suppliers.sabre.'.$envKey, false)) {
            $blockers[] = $action.'_disabled_by_env';
        }

        if ($action === 'void' && ! (bool) config('suppliers.sabre.'.$liveKey, false)) {
            $blockers[] = $action.'_live_call_disabled';
        }

        $confirmPhrase = strtoupper($action).'-TICKET-FOR-BOOKING-'.$booking->id
            .($ticketNumber !== null && $ticketNumber !== '' ? '-'.$ticketNumber : '');

        $liveAllowed = $provider === SupplierProvider::Sabre->value
            && $ticket !== null
            && (bool) config('suppliers.sabre.'.$envKey, false)
            && (bool) config('suppliers.sabre.'.$liveKey, false);

        return [
            'action' => $action,
            'eligible' => $provider === SupplierProvider::Sabre->value
                && $ticket !== null
                && (bool) config('suppliers.sabre.'.$envKey, false),
            'live_supplier_call_allowed' => $liveAllowed,
            'manual_workflow_allowed' => $action === 'refund'
                && $provider === SupplierProvider::Sabre->value
                && $ticket !== null
                && (bool) config('suppliers.sabre.refund_enabled', false),
            'blockers' => $blockers,
            'ticket_number' => $ticket?->ticket_number,
            'confirm_phrase' => $confirmPhrase,
            'config' => [
                $envKey => (bool) config('suppliers.sabre.'.$envKey, false),
                $liveKey => (bool) config('suppliers.sabre.'.$liveKey, false),
            ],
        ];
    }

    private function resolveTicket(Booking $booking, ?string $ticketNumber): ?BookingTicket
    {
        $query = BookingTicket::query()->where('booking_id', $booking->id);
        if ($ticketNumber !== null && $ticketNumber !== '') {
            $query->where('ticket_number', $ticketNumber);
        }

        return $query->orderByDesc('id')->first();
    }

    public static function confirmPhraseMatches(Booking $booking, string $action, ?string $ticketNumber, ?string $confirm): bool
    {
        $expected = strtoupper($action).'-TICKET-FOR-BOOKING-'.$booking->id
            .($ticketNumber !== null && $ticketNumber !== '' ? '-'.$ticketNumber : '');

        return is_string($confirm) && trim($confirm) === $expected;
    }
}
