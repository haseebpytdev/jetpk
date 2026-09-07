<?php

use App\Services\Notifications\NotificationRouteSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 120);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedBigInteger('agency_id')->nullable()->index();
            $table->string('aggregate_type', 80)->nullable();
            $table->string('aggregate_id', 64)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload');
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['status', 'available_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->index();
            $table->string('event_type', 120);
            $table->unsignedBigInteger('agency_id')->nullable()->index();
            $table->string('channel', 16)->default('email');
            $table->string('audience', 40);
            $table->string('recipient', 190);
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('template_key', 120)->nullable();
            $table->string('template_version', 40)->nullable();
            $table->string('queue_name', 80)->nullable();
            $table->string('priority', 24)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('provider_message_id', 120)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_key', 190)->unique();
            $table->unsignedBigInteger('agency_id')->nullable()->index();
            $table->string('event_type', 120);
            $table->string('channel', 16)->default('email');
            $table->string('audience', 40);
            $table->string('recipient_strategy', 60);
            $table->string('provider', 40)->default('laravel_mail');
            $table->string('template_key', 120)->nullable();
            $table->string('priority', 24)->default('transactional');
            $table->string('queue_name', 80);
            $table->string('locale', 12)->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['event_type', 'channel', 'enabled']);
        });

        app(NotificationRouteSeeder::class)->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_routes');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_outbox');
    }
};
