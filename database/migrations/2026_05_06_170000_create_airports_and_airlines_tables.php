<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airports', function (Blueprint $table): void {
            $table->id();
            $table->string('iata_code', 8)->nullable()->index();
            $table->string('icao_code', 8)->nullable()->index();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 4)->nullable();
            $table->string('timezone')->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('priority_score')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('search_keywords')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('airlines', function (Blueprint $table): void {
            $table->id();
            $table->string('iata_code', 8)->nullable()->index();
            $table->string('icao_code', 8)->nullable()->index();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('search_keywords')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airlines');
        Schema::dropIfExists('airports');
    }
};
