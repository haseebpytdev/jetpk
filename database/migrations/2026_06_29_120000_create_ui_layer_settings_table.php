<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ui_layer_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('layer_key', 96)->unique();
            $table->boolean('enabled')->default(false);
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
        Schema::dropIfExists('ui_layer_settings');
    }
};
