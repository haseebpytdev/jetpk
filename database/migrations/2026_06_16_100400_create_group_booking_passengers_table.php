<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_booking_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_booking_id')->constrained('group_bookings')->cascadeOnDelete();
            $table->string('title', 8)->nullable();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->date('date_of_birth')->nullable();
            $table->string('passport_number', 40)->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('passenger_type', 16)->default('adult');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_booking_passengers');
    }
};
