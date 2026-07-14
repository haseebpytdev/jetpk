<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('supplier', 32)->nullable()->after('agent_id');
            $table->string('route')->nullable()->after('supplier');
            $table->string('airline')->nullable()->after('route');
            $table->date('travel_date')->nullable()->after('airline');
            $table->string('payment_status', 32)->default('unpaid')->after('status');
            $table->string('source_channel', 32)->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'supplier',
                'route',
                'airline',
                'travel_date',
                'payment_status',
                'source_channel',
            ]);
        });
    }
};
