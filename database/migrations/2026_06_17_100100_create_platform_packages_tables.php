<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_package_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_package_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['platform_package_id', 'module_key'], 'pkg_module_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_package_modules');
        Schema::dropIfExists('platform_packages');
    }
};
