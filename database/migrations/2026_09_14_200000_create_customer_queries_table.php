<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_reference', 32)->unique();
            $table->string('visitor_token_hash', 64)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 191);
            $table->boolean('email_verified')->default(false);
            $table->string('phone_raw', 40)->nullable();
            $table->string('phone_e164', 24)->nullable();
            $table->string('phone_country', 8)->nullable();
            $table->boolean('phone_verified')->default(false);
            $table->boolean('contact_consent')->default(false);
            $table->timestamp('consent_timestamp')->nullable();
            $table->string('consent_source', 40)->default('ask_jetpakistan');
            $table->string('source', 40)->default('ask_jetpakistan');
            $table->string('intent', 80)->nullable();
            $table->string('origin', 8)->nullable();
            $table->string('destination', 8)->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->string('trip_type', 20)->nullable();
            $table->unsignedTinyInteger('adult_count')->nullable();
            $table->unsignedTinyInteger('child_count')->nullable();
            $table->unsignedTinyInteger('infant_count')->nullable();
            $table->string('status', 32)->default('new')->index();
            $table->string('priority', 20)->nullable();
            $table->boolean('callback_required')->default(false);
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('ai_summary')->nullable();
            $table->json('travel_state')->nullable();
            $table->string('ip_country_hint', 8)->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_queries');
    }
};
