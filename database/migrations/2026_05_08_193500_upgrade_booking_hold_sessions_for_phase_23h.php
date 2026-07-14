<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_hold_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_hold_sessions', 'requires_instant_payment')) {
                $table->boolean('requires_instant_payment')->nullable()->after('hold_status');
            }
            if (! Schema::hasColumn('booking_hold_sessions', 'local_checkout_expires_at')) {
                $table->timestamp('local_checkout_expires_at')->nullable()->after('payment_required_by');
            }
            if (! Schema::hasColumn('booking_hold_sessions', 'passenger_pricing')) {
                $table->json('passenger_pricing')->nullable()->after('passenger_counts');
            }
            if (! Schema::hasColumn('booking_hold_sessions', 'passenger_pricing_available')) {
                $table->boolean('passenger_pricing_available')->default(false)->after('passenger_pricing');
            }
            if (! Schema::hasColumn('booking_hold_sessions', 'safe_error')) {
                $table->text('safe_error')->nullable()->after('hold_order_snapshot');
            }
            if (! Schema::hasColumn('booking_hold_sessions', 'meta')) {
                $table->json('meta')->nullable()->after('safe_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_hold_sessions', function (Blueprint $table): void {
            $drop = [];
            foreach ([
                'requires_instant_payment',
                'local_checkout_expires_at',
                'passenger_pricing',
                'passenger_pricing_available',
                'safe_error',
                'meta',
            ] as $col) {
                if (Schema::hasColumn('booking_hold_sessions', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
