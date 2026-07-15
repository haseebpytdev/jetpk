<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('passenger_index')->default(0);
            $table->string('title', 16)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('passport_number')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'passenger_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_passengers');
    }
};
