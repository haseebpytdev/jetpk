<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_pages')) {
            return;
        }

        Schema::create('client_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('internal_name');
            $table->string('public_title');
            $table->string('nav_label')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('show_header')->default(true);
            $table->boolean('show_footer')->default(true);
            $table->json('seo_json')->nullable();
            $table->timestamps();

            $table->unique(['client_profile_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_pages');
    }
};
