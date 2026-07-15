<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_communication_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('smtp_enabled')->default(false);
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_email')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_provider')->nullable();
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->text('whatsapp_webhook_verify_token')->nullable();
            $table->string('whatsapp_default_country_code')->nullable()->default('+92');
            $table->json('whatsapp_settings')->nullable();
            $table->json('notification_rules')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_communication_settings');
    }
};
