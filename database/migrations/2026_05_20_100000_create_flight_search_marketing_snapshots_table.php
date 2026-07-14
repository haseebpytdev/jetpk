<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_search_marketing_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained(indexName: 'fsm_agency_fk')->nullOnDelete();
            $table->uuid('search_id');
            $table->foreignId('user_id')->nullable()->constrained(indexName: 'fsm_user_fk')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('session_id')->nullable();
            $table->string('source_channel')->nullable()->default('public_search');
            $table->json('criteria');
            $table->string('criteria_fingerprint', 64);
            $table->json('top_offers');
            $table->unsignedInteger('offer_count')->default(0);
            $table->timestamp('searched_at');
            $table->timestamp('send_after_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('skip_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('communication_log_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('search_id', 'fsm_search_uid');
            $table->index('criteria_fingerprint', 'fsm_criteria_fp_idx');
            $table->index(['status', 'send_after_at'], 'fsm_status_send_idx');
            $table->index(['recipient_email', 'searched_at'], 'fsm_email_search_idx');
            $table->index(['recipient_email', 'criteria_fingerprint'], 'fsm_email_fp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_search_marketing_snapshots');
    }
};
