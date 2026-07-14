<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('account_type');
            $table->timestamp('invited_at')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('invited_at');
            $table->json('meta')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['status', 'invited_at', 'last_login_at', 'meta']);
        });
    }
};
