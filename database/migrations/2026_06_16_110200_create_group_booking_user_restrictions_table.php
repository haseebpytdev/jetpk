<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_booking_user_restrictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('unpaid_release_count')->default(0);
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('last_release_at')->nullable();
            $table->timestamp('reset_at')->nullable();
            $table->foreignId('reset_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reset_note')->nullable();
            $table->timestamps();

            $table->index('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_booking_user_restrictions');
    }
};
