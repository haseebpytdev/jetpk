<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Agent staff uses existing users.account_type (string) with value agent_staff.
 * Permissions and owner agent link are stored in users.meta JSON — no schema change required.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty: account_type column already supports agent_staff.
    }

    public function down(): void
    {
        // No rollback needed.
    }
};
