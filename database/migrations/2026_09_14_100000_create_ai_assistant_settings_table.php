<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('master_enabled')->default(false);
            $table->boolean('lab_adapter_enabled')->default(false);
            $table->boolean('rag_enabled')->default(false);
            $table->boolean('human_handoff_enabled')->default(false);
            $table->boolean('learning_queue_enabled')->default(false);
            $table->boolean('internal_canary_enabled')->default(false);
            $table->boolean('flight_search_read_only_enabled')->default(false);
            $table->string('audience_mode', 32)->default('off');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_settings');
    }
};
