<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->string('channel')->default('email');
            $table->boolean('enabled')->default(true);
            $table->string('recipient_scope')->default('admin');
            $table->json('recipient_emails')->nullable();
            $table->json('cc_emails')->nullable();
            $table->json('bcc_emails')->nullable();
            $table->string('digest_mode')->default('immediate');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'event_key', 'channel'], 'agency_notification_event_channel_unique');
            $table->index(['agency_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_notification_settings');
    }
};
