<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive indexes for JetPK admin customer index and social-account lookups.
 * Reversible; safe on live with existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['account_type', 'current_agency_id', 'id'], 'users_account_agency_id_idx');
        });

        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->index(['user_id', 'provider'], 'social_accounts_user_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropIndex('social_accounts_user_provider_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_account_agency_id_idx');
        });
    }
};
