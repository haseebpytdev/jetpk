<?php

namespace App\Enums;

enum SupportTicketCategory: string
{
    case Booking = 'booking';
    case Payment = 'payment';
    case Cancellation = 'cancellation';
    case Refund = 'refund';
    case Documents = 'documents';
    case Technical = 'technical';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking',
            self::Payment => 'Payment',
            self::Cancellation => 'Cancellation',
            self::Refund => 'Refund',
            self::Documents => 'Documents',
            self::Technical => 'Technical',
            self::Other => 'Other',
        };
    }
}
