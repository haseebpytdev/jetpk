<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_featured_fares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->char('origin_code', 3);
            $table->char('destination_code', 3);
            $table->unsignedTinyInteger('date_offset_days');
            $table->string('cabin', 32)->default('economy');
            $table->unsignedTinyInteger('adults')->default(1);
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(100);
            $table->timestamp('last_refreshed_at')->nullable();
            $table->string('last_status', 32)->default('pending');
            $table->string('last_error_code')->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_featured_fares');
    }
};
