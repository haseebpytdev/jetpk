<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('supplier', 32)->default('alhaider');
            $table->string('supplier_package_id', 64);
            $table->string('public_id', 80)->nullable();
            $table->foreignId('group_category_id')->nullable()->constrained('group_categories')->nullOnDelete();
            $table->string('title', 200);
            $table->string('sector', 100)->nullable();
            $table->unsignedInteger('airline_id')->nullable();
            $table->string('airline_name', 120)->nullable();
            $table->string('package_type', 80)->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedInteger('total_seats')->default(0);
            $table->unsignedInteger('held_seats')->default(0);
            $table->unsignedInteger('sold_seats')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('price_child', 12, 2)->nullable();
            $table->decimal('price_infant', 12, 2)->nullable();
            $table->string('currency', 8)->default('PKR');
            $table->string('baggage', 120)->nullable();
            $table->text('refund_change_notes')->nullable();
            $table->json('snapshot')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier', 'supplier_package_id']);
            $table->index(['is_active', 'departure_date']);
            $table->index(['is_active', 'sector']);
            $table->index(['is_active', 'airline_id']);
            $table->index(['is_active', 'group_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_inventories');
    }
};
