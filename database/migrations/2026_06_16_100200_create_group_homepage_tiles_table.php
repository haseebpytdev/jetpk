<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_homepage_tiles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('image_path')->nullable();
            $table->string('target_type', 32)->default('all');
            $table->string('target_value', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_homepage_tiles');
    }
};
