<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_embed_tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('slug', 64)->unique();
            $table->string('display_name', 120);
            $table->string('assistant_name', 120);
            $table->string('status', 32)->default('disabled');
            $table->boolean('embed_enabled')->default(false);
            $table->string('knowledge_namespace', 64)->default('default');
            $table->json('allowed_origins')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('theme')->nullable();
            $table->json('branding')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_embed_tenant_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_embed_tenant_id')->constrained('ai_embed_tenants')->cascadeOnDelete();
            $table->string('key_hash', 64);
            $table->string('key_prefix', 12);
            $table->string('status', 32)->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_embed_tenant_id', 'key_hash']);
            $table->index(['key_hash', 'status']);
        });

        Schema::create('ai_embed_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_public_id')->nullable();
            $table->string('event_type', 64);
            $table->boolean('success')->default(true);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_public_id', 'event_type']);
        });

        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->foreignId('ai_embed_tenant_id')
                ->nullable()
                ->after('channel')
                ->constrained('ai_embed_tenants')
                ->nullOnDelete();
            $table->index(['ai_embed_tenant_id', 'visitor_token_hash']);
        });

        Schema::table('customer_queries', function (Blueprint $table): void {
            $table->foreignId('ai_embed_tenant_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained('ai_embed_tenants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_queries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ai_embed_tenant_id');
        });

        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ai_embed_tenant_id');
        });

        Schema::dropIfExists('ai_embed_audit_events');
        Schema::dropIfExists('ai_embed_tenant_keys');
        Schema::dropIfExists('ai_embed_tenants');
    }
};
