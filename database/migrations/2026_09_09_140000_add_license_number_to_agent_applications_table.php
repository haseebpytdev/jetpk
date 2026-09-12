<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('agent_applications', 'license_number')) {
                $table->string('license_number', 80)->nullable()->after('iata_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('agent_applications', 'license_number')) {
                $table->dropColumn('license_number');
            }
        });
    }
};
