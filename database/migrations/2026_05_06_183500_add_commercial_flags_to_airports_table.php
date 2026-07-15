<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airports', function (Blueprint $table): void {
            $table->boolean('has_routes')->default(false)->after('priority_score');
            $table->integer('route_count')->default(0)->after('has_routes');
            $table->boolean('is_commercial')->default(false)->after('route_count');
            $table->string('airport_type')->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table): void {
            $table->dropColumn(['has_routes', 'route_count', 'is_commercial', 'airport_type']);
        });
    }
};
