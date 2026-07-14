<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'supplier_validation_strategy_evidence';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_connection_id')->nullable();
            $table->string('provider', 32)->default('sabre');
            $table->string('distribution_channel', 16)->default('gds');
            $table->string('action_code', 64);
            $table->string('strategy_code', 128);
            $table->string('endpoint_path', 255)->nullable();
            $table->string('payload_schema', 128)->nullable();
            $table->string('carrier_chain', 64)->nullable();
            $table->string('validating_carrier', 8)->nullable();
            $table->string('route_pattern', 64)->nullable();
            $table->string('trip_type', 32)->nullable();
            $table->unsignedTinyInteger('segment_count')->nullable();
            $table->string('outcome', 16);
            $table->unsignedInteger('success_count')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedBigInteger('last_success_booking_id')->nullable();
            $table->unsignedBigInteger('failed_booking_id')->nullable();
            $table->string('safe_failure_family', 64)->nullable();
            $table->string('safe_reason_code', 128)->nullable();
            $table->timestamps();

            $table->index(['supplier_connection_id', 'action_code', 'strategy_code', 'outcome'], 'svse_lookup_idx');
            $table->index(['action_code', 'strategy_code'], 'svse_action_strategy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
