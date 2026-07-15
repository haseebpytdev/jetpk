<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('supplier_api_booking_id', 191)->nullable()->after('supplier_reference');
        });

        Schema::table('supplier_bookings', function (Blueprint $table): void {
            $table->string('supplier_api_booking_id', 191)->nullable()->after('supplier_reference');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bookings', function (Blueprint $table): void {
            $table->dropColumn('supplier_api_booking_id');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('supplier_api_booking_id');
        });
    }
};
