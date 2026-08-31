<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R7: independent Customer Group Booking gate (does not affect Flight guest booking).
 * Default TRUE preserves existing production eligibility for authenticated customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_checkout_settings')) {
            return;
        }

        Schema::table('commerce_checkout_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('commerce_checkout_settings', 'customer_group_booking_enabled')) {
                $table->boolean('customer_group_booking_enabled')->default(true)->after('card_payment_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commerce_checkout_settings')) {
            return;
        }

        Schema::table('commerce_checkout_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('commerce_checkout_settings', 'customer_group_booking_enabled')) {
                $table->dropColumn('customer_group_booking_enabled');
            }
        });
    }
};
