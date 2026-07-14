<?php

namespace App\Support\Suppliers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time-safe repair for legacy {@see SupplierProvider::Pia} rows after Pia → PiaNdc rename.
 * Updates existing rows only — never creates supplier_connections.
 */
final class LegacySupplierProviderDataRepair
{
    public static function repairPiaProviderRows(): void
    {
        if (! Schema::hasTable('supplier_connections')) {
            return;
        }

        DB::table('supplier_connections')->where('provider', 'pia')->update(['provider' => 'pia_ndc']);

        if (Schema::hasTable('supplier_bookings')) {
            DB::table('supplier_bookings')->where('provider', 'pia')->update(['provider' => 'pia_ndc']);
        }

        if (Schema::hasTable('supplier_booking_attempts')) {
            DB::table('supplier_booking_attempts')->where('provider', 'pia')->update(['provider' => 'pia_ndc']);
        }

        if (Schema::hasTable('bookings')) {
            DB::table('bookings')->where('supplier', 'pia')->update(['supplier' => 'pia_ndc']);
        }
    }
}
