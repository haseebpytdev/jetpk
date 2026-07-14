<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_users', function (Blueprint $table) {
            $table->string('agency_role', 64)->nullable()->after('role');
            $table->index('agency_role');
        });
    }

    public function down(): void
    {
        Schema::table('agency_users', function (Blueprint $table) {
            $table->dropIndex(['agency_role']);
            $table->dropColumn('agency_role');
        });
    }
};
