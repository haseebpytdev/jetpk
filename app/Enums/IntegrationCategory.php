<?php

namespace App\Enums;

enum IntegrationCategory: string
{
    case Flights = 'flights';
    case Payments = 'payments';
    case Hotels = 'hotels';
    case Groups = 'groups';
    case Visa = 'visa';
    case Umrah = 'umrah';
    case Messaging = 'messaging';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Flights => 'Flights',
            self::Payments => 'Payments',
            self::Hotels => 'Hotels',
            self::Groups => 'Groups',
            self::Visa => 'Visa',
            self::Umrah => 'Umrah',
            self::Messaging => 'Messaging',
            self::Other => 'Other',
        };
    }
}
