<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_checkout_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->boolean('guest_booking_enabled')->default(true);
            $table->boolean('card_payment_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_checkout_settings');
    }
};
