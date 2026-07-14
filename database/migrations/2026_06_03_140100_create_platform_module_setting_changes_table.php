<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_module_setting_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('developer_user_id')
                ->nullable()
                ->constrained('developer_users')
                ->nullOnDelete();
            $table->string('module_key', 64);
            $table->boolean('old_enabled')->nullable();
            $table->boolean('new_enabled');
            $table->string('source', 32)->default('manual');
            $table->string('preset_key', 64)->nullable();
            $table->boolean('validation_passed')->default(true);
            $table->json('validation_violations')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['module_key', 'created_at'], 'pm_changes_module_created_idx');
            $table->index(['developer_user_id', 'created_at'], 'pm_changes_dev_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_module_setting_changes');
    }
};
