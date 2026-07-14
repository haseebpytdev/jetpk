<?php

namespace App\Support\Bookings;

class PaymentOperationalStatus
{
    /**
     * @return array{code: string, label: string, meaning: string}
     */
    public static function fromValue(?string $paymentStatus): array
    {
        $normalized = strtolower(trim((string) $paymentStatus));
        $code = match ($normalized) {
            'submitted', 'proof_under_review' => 'proof_submitted',
            'unpaid', 'proof_submitted', 'partial', 'paid', 'rejected', 'refunded', 'partial_refund', 'refund_pending' => $normalized,
            default => 'unpaid',
        };

        return [
            'code' => $code,
            'label' => self::label($code),
            'meaning' => self::meaning($code),
        ];
    }

    public static function label(string $code): string
    {
        return match ($code) {
            'unpaid' => 'Unpaid',
            'proof_submitted' => 'Proof submitted — under review',
            'partial' => 'Partially paid',
            'paid' => 'Paid — verified',
            'rejected' => 'Payment proof rejected',
            'refunded' => 'Refunded',
            'partial_refund' => 'Partially refunded',
            'refund_pending' => 'Refund pending',
            default => ucfirst(str_replace('_', ' ', $code)),
        };
    }

    public static function meaning(string $code): string
    {
        return match ($code) {
            'unpaid' => 'No verified payment yet. Upload proof or pay the balance due.',
            'proof_submitted' => 'Payment proof was submitted and is awaiting staff verification.',
            'partial' => 'Some payment has been verified; a balance remains.',
            'paid' => 'The full amount has been verified. No further payment is required.',
            'rejected' => 'The latest payment proof was rejected. You may submit proof again if a balance is due.',
            'partial_refund' => 'Part of the paid amount has been refunded.',
            'refunded' => 'The booking payment has been fully refunded.',
            'refund_pending' => 'A refund is being processed.',
            default => 'Payment status unavailable.',
        };
    }
}
