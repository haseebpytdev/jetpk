<?php

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('provider');
            $table->string('environment', 32)->default(SupplierEnvironment::Demo->value)->after('name');
            $table->string('status', 32)->default(SupplierConnectionStatus::Inactive->value)->after('environment');
            $table->string('base_url')->nullable()->after('status');
            $table->string('last_test_status', 64)->nullable()->after('last_tested_at');
            $table->text('last_error')->nullable()->after('last_test_status');
            $table->json('meta')->nullable()->after('settings');
        });

        DB::table('supplier_connections')
            ->orderBy('id')
            ->get()
            ->each(function (object $row): void {
                DB::table('supplier_connections')
                    ->where('id', $row->id)
                    ->update([
                        'name' => $row->display_name ?: ucfirst((string) $row->provider).' Supplier',
                        'environment' => (string) $row->provider === 'mock' ? SupplierEnvironment::Demo->value : SupplierEnvironment::Sandbox->value,
                        'status' => (bool) $row->is_active ? SupplierConnectionStatus::Active->value : SupplierConnectionStatus::Inactive->value,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'name',
                'environment',
                'status',
                'base_url',
                'last_test_status',
                'last_error',
                'meta',
            ]);
        });
    }
};
