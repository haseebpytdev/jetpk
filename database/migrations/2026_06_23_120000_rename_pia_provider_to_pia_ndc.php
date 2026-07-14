<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('supplier_connections')->where('provider', 'pia')->update(['provider' => 'pia_ndc']);
        DB::table('supplier_bookings')->where('provider', 'pia')->update(['provider' => 'pia_ndc']);
        DB::table('supplier_booking_attempts')->where('provider', 'pia')->update(['provider' => 'pia_ndc']);
        DB::table('bookings')->where('supplier', 'pia')->update(['supplier' => 'pia_ndc']);
    }

    public function down(): void
    {
        DB::table('supplier_connections')->where('provider', 'pia_ndc')->update(['provider' => 'pia']);
        DB::table('supplier_bookings')->where('provider', 'pia_ndc')->update(['provider' => 'pia']);
        DB::table('supplier_booking_attempts')->where('provider', 'pia_ndc')->update(['provider' => 'pia']);
        DB::table('bookings')->where('supplier', 'pia_ndc')->update(['supplier' => 'pia']);
    }
};
