<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_hold_sessions') || ! Schema::hasColumn('booking_hold_sessions', 'supplier_offer_id')) {
            return;
        }

        Schema::table('booking_hold_sessions', function (Blueprint $table): void {
            $table->text('supplier_offer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_hold_sessions') || ! Schema::hasColumn('booking_hold_sessions', 'supplier_offer_id')) {
            return;
        }

        Schema::table('booking_hold_sessions', function (Blueprint $table): void {
            $table->string('supplier_offer_id')->nullable()->change();
        });
    }
};
