<?php

namespace App\Support\Bookings;

class TicketingOperationalStatus
{
    /**
     * @return array{code: string, label: string, meaning: string}
     */
    public static function fromValues(
        ?string $ticketingStatus,
        ?string $paymentStatus = null,
        bool $hasPnr = false,
        bool $hasIssuedTickets = false,
        ?string $provider = null,
        ?string $cancellationStatus = null,
    ): array {
        $raw = strtolower(trim((string) $ticketingStatus));
        $paymentStatus = strtolower(trim((string) $paymentStatus));
        $provider = strtolower(trim((string) $provider));
        $cancellationStatus = strtolower(trim((string) $cancellationStatus));
        $providerSupported = in_array($provider, ['duffel', 'sabre', 'pia_ndc', 'airline_direct', 'iati'], true);

        $code = match (true) {
            in_array($cancellationStatus, ['requested', 'approved', 'processing'], true) && $hasIssuedTickets => 'void_requested',
            in_array($cancellationStatus, ['processed', 'voided', 'completed'], true) && $hasIssuedTickets => 'voided',
            in_array($raw, ['voided', 'ticket_voided'], true) => 'voided',
            $raw === 'ticket_void_requires_review' => 'void_requires_review',
            $raw === 'failed' => 'failed',
            in_array($raw, ['issued', 'ticketed', 'completed'], true) || $hasIssuedTickets => 'issued',
            in_array($raw, ['pending', 'in_progress', 'ticketing_pending'], true) && $hasPnr => 'pending',
            ! $providerSupported => 'not_supported',
            in_array($paymentStatus, ['paid', 'partial'], true) && $hasPnr => 'ready',
            default => 'not_started',
        };

        return [
            'code' => $code,
            'label' => self::label($code),
            'meaning' => self::meaning($code),
        ];
    }

    public static function label(string $code): string
    {
        return str_replace('_', ' ', $code);
    }

    public static function meaning(string $code): string
    {
        return match ($code) {
            'not_started' => 'Ticketing not attempted.',
            'ready' => 'Paid + PNR exists.',
            'pending' => 'Ticketing process started.',
            'issued' => 'Ticket numbers generated/stored.',
            'failed' => 'Ticketing failed.',
            'not_supported' => 'Provider ticketing not available.',
            'void_requested' => 'Void/cancel ticket requested.',
            'voided' => 'Ticket voided/manual record updated.',
            'void_requires_review' => 'Void attempt needs admin review.',
            default => 'Ticketing status unavailable.',
        };
    }
}
