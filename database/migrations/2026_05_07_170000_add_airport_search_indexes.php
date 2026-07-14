<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airports', function (Blueprint $table): void {
            $table->index('city');
            $table->index('country');
            $table->index(['is_active', 'is_commercial', 'priority_score'], 'airports_active_commercial_priority_idx');
            $table->index(['has_routes', 'route_count'], 'airports_routes_idx');
        });
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table): void {
            $table->dropIndex(['city']);
            $table->dropIndex(['country']);
            $table->dropIndex('airports_active_commercial_priority_idx');
            $table->dropIndex('airports_routes_idx');
        });
    }
};
