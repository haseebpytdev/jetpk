<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_transactions', 'purpose')) {
                $table->string('purpose', 40)->default('booking')->after('gateway');
                $table->index('purpose');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_transactions', 'purpose')) {
                $table->dropIndex(['purpose']);
                $table->dropColumn('purpose');
            }
        });
    }
};
