<?php

namespace App\Enums;

enum IatiSupplierReservationSource: string
{
    case None = 'none';
    case LocalOnly = 'local_only';
    case SupplierHold = 'supplier_hold';
    case SupplierBooked = 'supplier_booked';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::LocalOnly => 'Local Only',
            self::SupplierHold => 'Supplier Hold',
            self::SupplierBooked => 'Supplier Booked',
        };
    }

    public function adminLabel(): string
    {
        return match ($this) {
            self::None => 'None',
            self::LocalOnly => 'Local Only',
            self::SupplierHold => 'Supplier Hold',
            self::SupplierBooked => 'Supplier Booked',
        };
    }
}
