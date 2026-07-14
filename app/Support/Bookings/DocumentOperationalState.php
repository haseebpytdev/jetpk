<?php

namespace App\Support\Bookings;

class DocumentOperationalState
{
    public static function typeLabel(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return match ($type) {
            'booking_confirmation',
            'invoice',
            'payment_receipt',
            'ticket_itinerary',
            'refund_note',
            'cancellation_confirmation' => str_replace('_', ' ', $type),
            default => $type !== '' ? str_replace('_', ' ', $type) : 'document',
        };
    }

    /**
     * @return array{code: string, label: string}
     */
    public static function statusForDocument(?string $status, bool $hasFilePath = false, bool $isVoided = false): array
    {
        $status = strtolower(trim((string) $status));

        $code = match (true) {
            $isVoided => 'voided',
            $status === 'failed' => 'failed',
            $status === 'generated' && $hasFilePath => 'generated',
            $status === 'generated' && ! $hasFilePath => 'sent',
            default => 'not_generated',
        };

        return [
            'code' => $code,
            'label' => str_replace('_', ' ', $code),
        ];
    }
}
