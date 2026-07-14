<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('cancellation_status')->nullable()->after('status');
            $table->string('refund_status')->nullable()->after('cancellation_status');
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
        });

        Schema::table('booking_tickets', function (Blueprint $table): void {
            $table->string('void_status')->nullable()->after('status');
            $table->timestamp('voided_at')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_tickets', function (Blueprint $table): void {
            $table->dropColumn(['void_status', 'voided_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_status', 'refund_status', 'cancelled_at']);
        });
    }
};
