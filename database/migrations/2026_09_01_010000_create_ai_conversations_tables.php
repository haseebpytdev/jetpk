<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('channel', 32)->default('web');
            $table->string('visitor_token_hash', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state', 32)->default('AI_ACTIVE')->index();
            $table->foreignId('taken_over_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_over_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->json('shopping_state')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['ai_conversation_id', 'id']);
        });

        Schema::create('ai_handoff_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_state', 32);
            $table->string('to_state', 32);
            $table->string('reason', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_handoff_audits');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
