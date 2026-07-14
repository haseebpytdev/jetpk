<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_inventory_id')->constrained('group_inventories')->restrictOnDelete();
            $table->string('status', 40)->default('pending_passenger_details');
            $table->unsignedInteger('seat_count')->default(1);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('PKR');
            $table->timestamp('expires_at')->nullable();
            $table->string('supplier_reservation_id', 120)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_bookings');
    }
};
