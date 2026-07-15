<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_bookings', function (Blueprint $table): void {
            $table->timestamp('reservation_created_at')->nullable()->after('expires_at');
            $table->timestamp('released_at')->nullable()->after('reservation_created_at');
            $table->string('release_reason', 120)->nullable()->after('released_at');
            $table->timestamp('supplier_release_attempted_at')->nullable()->after('supplier_reservation_id');
            $table->timestamp('supplier_released_at')->nullable()->after('supplier_release_attempted_at');
            $table->timestamp('supplier_release_failed_at')->nullable()->after('supplier_released_at');
            $table->text('supplier_release_response')->nullable()->after('supplier_release_failed_at');
            $table->timestamp('payment_submitted_at')->nullable()->after('supplier_release_response');
            $table->string('payment_method', 40)->nullable()->after('payment_submitted_at');
            $table->string('payment_reference', 120)->nullable()->after('payment_method');
            $table->string('payment_proof_path', 255)->nullable()->after('payment_reference');
            $table->string('manual_payment_status', 40)->nullable()->after('payment_proof_path');
            $table->timestamp('admin_payment_verified_at')->nullable()->after('manual_payment_status');
            $table->foreignId('admin_payment_verified_by')->nullable()->after('admin_payment_verified_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('group_bookings', function (Blueprint $table): void {
            $table->dropForeign(['admin_payment_verified_by']);
            $table->dropColumn([
                'reservation_created_at',
                'released_at',
                'release_reason',
                'supplier_release_attempted_at',
                'supplier_released_at',
                'supplier_release_failed_at',
                'supplier_release_response',
                'payment_submitted_at',
                'payment_method',
                'payment_reference',
                'payment_proof_path',
                'manual_payment_status',
                'admin_payment_verified_at',
                'admin_payment_verified_by',
            ]);
        });
    }
};
