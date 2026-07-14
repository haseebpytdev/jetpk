<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 40);
            $table->string('environment', 16)->default('test');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('client_transaction_id')->unique();
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_session_id')->nullable();
            $table->text('gateway_payment_url')->nullable();
            $table->string('status', 40)->default('initiated');
            $table->string('gateway_status')->nullable();
            $table->string('gateway_code')->nullable();
            $table->string('gateway_message')->nullable();
            $table->text('request_payload_json')->nullable();
            $table->text('response_payload_json')->nullable();
            $table->text('callback_payload_json')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index('gateway_order_id');
            $table->index('booking_id');
            $table->index('group_booking_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
