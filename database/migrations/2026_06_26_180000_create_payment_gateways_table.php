<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('environment', 16)->default('test');
            $table->boolean('is_active')->default(false);
            $table->text('merchant_id')->nullable();
            $table->text('merchant_secret_key')->nullable();
            $table->string('base_url')->default('https://api.abhipay.com.pk/api/v3');
            $table->string('callback_url')->nullable();
            $table->string('success_url')->nullable();
            $table->string('cancel_url')->nullable();
            $table->string('decline_url')->nullable();
            $table->text('config_json')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'code']);
            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
