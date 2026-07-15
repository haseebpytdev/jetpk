<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('forwarded_to_agent_id')->nullable()->after('assigned_to_user_id')->constrained('agents')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable()->after('forwarded_to_agent_id');
            $table->foreignId('forwarded_by_user_id')->nullable()->after('forwarded_at')->constrained('users')->nullOnDelete();

            $table->index('forwarded_to_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['forwarded_to_agent_id']);
            $table->dropForeign(['forwarded_by_user_id']);
            $table->dropIndex(['forwarded_to_agent_id']);
            $table->dropColumn(['forwarded_to_agent_id', 'forwarded_at', 'forwarded_by_user_id']);
        });
    }
};
