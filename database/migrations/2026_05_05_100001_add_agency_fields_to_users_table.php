<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_agency_id')
                ->nullable()
                ->after('remember_token')
                ->constrained('agencies')
                ->nullOnDelete();
            $table->string('account_type', 32)->nullable()->after('current_agency_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_agency_id');
            $table->dropColumn('account_type');
        });
    }
};
