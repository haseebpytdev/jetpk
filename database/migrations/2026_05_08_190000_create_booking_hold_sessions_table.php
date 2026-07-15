<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_hold_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('search_id')->nullable();
            $table->string('offer_id');
            $table->string('supplier_provider', 32)->nullable();
            $table->foreignId('supplier_connection_id')->nullable()->constrained('supplier_connections')->nullOnDelete();
            $table->string('supplier_offer_id')->nullable();
            $table->string('supplier_order_id')->nullable();
            $table->string('supplier_order_reference')->nullable();
            $table->string('hold_status', 32)->default('not_started');
            $table->timestamp('price_guarantee_expires_at')->nullable();
            $table->timestamp('payment_required_by')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->decimal('validated_total_amount', 14, 2)->default(0);
            $table->char('validated_total_currency', 3)->default('PKR');
            $table->decimal('converted_total_pkr', 14, 2)->nullable();
            $table->json('markup_snapshot')->nullable();
            $table->json('passenger_counts')->nullable();
            $table->json('validated_offer_snapshot')->nullable();
            $table->json('hold_order_snapshot')->nullable();
            $table->text('last_error_safe')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'hold_status']);
            $table->index(['search_id', 'offer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_hold_sessions');
    }
};
