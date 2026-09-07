<?php

use App\Services\Notifications\NotificationRouteSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(NotificationRouteSeeder::class)->seedDefaults();
    }

    public function down(): void
    {
        // Seeded routes are additive; leave in place on rollback.
    }
};
