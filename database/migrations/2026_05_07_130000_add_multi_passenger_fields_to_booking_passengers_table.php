<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_passengers', 'passenger_type')) {
                $table->string('passenger_type', 16)->default('adult')->after('passenger_index');
            }

            if (! Schema::hasColumn('booking_passengers', 'is_lead_passenger')) {
                $table->boolean('is_lead_passenger')->default(false)->after('passenger_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            if (Schema::hasColumn('booking_passengers', 'is_lead_passenger')) {
                $table->dropColumn('is_lead_passenger');
            }

            if (Schema::hasColumn('booking_passengers', 'passenger_type')) {
                $table->dropColumn('passenger_type');
            }
        });
    }
};
