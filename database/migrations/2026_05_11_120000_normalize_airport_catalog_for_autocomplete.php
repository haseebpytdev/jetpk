<?php

use App\Models\Airport;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Airport::normalizeCatalogForSearch();
    }

    public function down(): void
    {
        // Data repair is not reversible.
    }
};
