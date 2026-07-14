<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 64);
            $table->string('outcome', 32);
            $table->nullableMorphs('actor');
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at'], 'security_events_type_created_idx');
            $table->index(['outcome', 'created_at'], 'security_events_outcome_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
