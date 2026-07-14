<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_enabled')->default(true);
            $table->json('variables')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'event', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_message_templates');
    }
};
