<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_module_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('module_key', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->boolean('locked')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by_developer_user_id')
                ->nullable()
                ->constrained('developer_users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_module_settings');
    }
};
