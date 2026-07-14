<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('hold_session_id')->nullable()->after('agent_id')->constrained('booking_hold_sessions')->nullOnDelete();
            $table->string('supplier_hold_status', 32)->nullable()->after('supplier_reference');
            $table->timestamp('price_guarantee_expires_at')->nullable()->after('supplier_hold_status');
            $table->timestamp('payment_required_by')->nullable()->after('price_guarantee_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hold_session_id');
            $table->dropColumn([
                'supplier_hold_status',
                'price_guarantee_expires_at',
                'payment_required_by',
            ]);
        });
    }
};
