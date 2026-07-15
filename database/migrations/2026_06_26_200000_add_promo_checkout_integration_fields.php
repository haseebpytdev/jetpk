<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->unsignedInteger('per_user_limit')->nullable()->after('used_count');
            $table->boolean('internal_testing_only')->default(false)->after('status');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('promo_code_id')->nullable()->after('balance_due')->constrained('promo_codes')->nullOnDelete();
            $table->string('promo_code', 64)->nullable()->after('promo_code_id');
            $table->decimal('promo_discount_amount', 12, 2)->default(0)->after('promo_code');
            $table->decimal('payable_before_promo', 12, 2)->nullable()->after('promo_discount_amount');
            $table->decimal('payable_after_promo', 12, 2)->nullable()->after('payable_before_promo');
            $table->timestamp('promo_applied_at')->nullable()->after('payable_after_promo');
        });

        Schema::create('promo_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 128)->nullable();
            $table->string('code', 64);
            $table->decimal('original_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('final_amount', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('status', 32)->default('applied');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['promo_code_id', 'status']);
            $table->index(['booking_id', 'status']);
            $table->unique(['booking_id', 'promo_code_id'], 'promo_redemptions_booking_promo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_redemptions');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn([
                'promo_code',
                'promo_discount_amount',
                'payable_before_promo',
                'payable_after_promo',
                'promo_applied_at',
            ]);
        });

        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['per_user_limit', 'internal_testing_only']);
        });
    }
};
