<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('tagline')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('support_whatsapp')->nullable();
            $table->string('support_email')->nullable();
            $table->text('office_address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable()->default('Pakistan');
            $table->string('website_url')->nullable();
            $table->string('timezone')->nullable()->default('Asia/Karachi');
            $table->string('currency')->nullable()->default('PKR');
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('footer_logo_path')->nullable();
            $table->string('header_cta_label')->nullable();
            $table->string('header_cta_url')->nullable();
            $table->text('footer_about')->nullable();
            $table->string('footer_copyright')->nullable();
            $table->json('social_links')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_settings');
    }
};
