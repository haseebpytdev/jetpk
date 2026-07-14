<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('supplier_connections')->where('provider', 'mock')->delete();
    }

    public function down(): void
    {
        // Intentionally empty: mock supplier is no longer supported.
    }
};
