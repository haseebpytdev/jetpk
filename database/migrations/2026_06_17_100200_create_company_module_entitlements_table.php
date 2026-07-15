<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_module_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->boolean('enabled')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->string('source', 32)->default('manual');
            $table->unsignedBigInteger('assigned_by_developer_user_id')->nullable();
            $table->foreign('assigned_by_developer_user_id', 'cme_assigned_dev_fk')
                ->references('id')
                ->on('developer_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency_id', 'module_key'], 'agency_module_entitlement_unique');
            $table->index(['agency_id', 'expires_at'], 'agency_entitlement_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_module_entitlements');
    }
};
