<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'fare_revalidated_at')) {
                $after = Schema::hasColumn('bookings', 'payment_required_by') ? 'payment_required_by' : 'ticketed_at';
                $table->timestamp('fare_revalidated_at')->nullable()->after($after);
            }
            if (! Schema::hasColumn('bookings', 'selected_fare_total')) {
                $table->decimal('selected_fare_total', 12, 2)->nullable()->after('fare_revalidated_at');
            }
            if (! Schema::hasColumn('bookings', 'revalidated_fare_total')) {
                $table->decimal('revalidated_fare_total', 12, 2)->nullable()->after('selected_fare_total');
            }
            if (! Schema::hasColumn('bookings', 'fare_change_accepted_at')) {
                $table->timestamp('fare_change_accepted_at')->nullable()->after('revalidated_fare_total');
            }
            if (! Schema::hasColumn('bookings', 'pnr_expires_at')) {
                $table->timestamp('pnr_expires_at')->nullable()->after('fare_change_accepted_at');
            }
            if (! Schema::hasColumn('bookings', 'confirmation_method')) {
                $table->string('confirmation_method', 64)->nullable()->after('pnr_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            foreach ([
                'fare_revalidated_at',
                'selected_fare_total',
                'revalidated_fare_total',
                'fare_change_accepted_at',
                'pnr_expires_at',
                'confirmation_method',
            ] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
