<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive RBAC persistence.
 *
 * Uniqueness strategy (MariaDB 10.11 / SQLite tests):
 * UNIQUE(agency_id, slug) is unsafe because NULL agency_id (platform roles)
 * does not collide in MariaDB. Instead persist scope_key:
 *   platform  → "platform"
 *   agency    → (string) agency_id
 * UNIQUE(scope_key, slug) is database-enforced, no fake agency ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->string('scope_key', 32);
            $table->string('name', 64);
            $table->string('slug', 64);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'slug'], 'roles_scope_key_slug_unique');
            $table->index(['agency_id', 'is_system']);
            $table->foreign('agency_id')->references('id')->on('agencies')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->string('permission_key', 128);
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'permission_key'], 'role_permissions_role_key_unique');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'user_id'], 'role_user_role_user_unique');
            $table->index('user_id');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
