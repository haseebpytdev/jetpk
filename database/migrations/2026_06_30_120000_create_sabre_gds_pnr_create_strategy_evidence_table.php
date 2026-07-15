<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'sabre_gds_pnr_create_strategy_evidence';

    /** @var list<array{name: string, columns: list<string>}> */
    private const INDEXES = [
        ['name' => 'sgpce_conn_idx', 'columns' => ['supplier_connection_id']],
        ['name' => 'sgpce_provider_idx', 'columns' => ['provider']],
        ['name' => 'sgpce_strategy_idx', 'columns' => ['strategy_code']],
        ['name' => 'sgpce_carrier_idx', 'columns' => ['validating_carrier']],
        ['name' => 'sgpce_route_idx', 'columns' => ['route_pattern']],
        ['name' => 'sgpce_trip_idx', 'columns' => ['trip_type']],
        ['name' => 'sgpce_status_idx', 'columns' => ['outcome']],
        ['name' => 'sgpce_last_success_idx', 'columns' => ['last_success_booking_id']],
        ['name' => 'sgpce_last_failure_idx', 'columns' => ['failed_booking_id']],
        [
            'name' => 'sgpce_lookup_idx',
            'columns' => [
                'supplier_connection_id',
                'validating_carrier',
                'route_pattern',
                'trip_type',
                'segment_count',
                'outcome',
            ],
        ],
        ['name' => 'sgpce_strategy_outcome_idx', 'columns' => ['strategy_code', 'outcome']],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $this->defineColumns($table);
                $this->defineIndexes($table);
            });

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $this->ensureColumns($table);
        });

        $this->ensureIndexes();
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function defineColumns(Blueprint $table): void
    {
        $table->id();
        $table->unsignedBigInteger('supplier_connection_id')->nullable();
        $table->string('provider', 32)->default('sabre');
        $table->string('distribution_channel', 16)->default('gds');
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
        $table->string('host_error_family', 64)->nullable();
        $table->string('safe_reason_code', 128)->nullable();
        $table->timestamps();
    }

    private function defineIndexes(Blueprint $table): void
    {
        foreach (self::INDEXES as $index) {
            $table->index($index['columns'], $index['name']);
        }
    }

    private function ensureColumns(Blueprint $table): void
    {
        if (! Schema::hasColumn(self::TABLE, 'supplier_connection_id')) {
            $table->unsignedBigInteger('supplier_connection_id')->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'provider')) {
            $table->string('provider', 32)->default('sabre');
        }
        if (! Schema::hasColumn(self::TABLE, 'distribution_channel')) {
            $table->string('distribution_channel', 16)->default('gds');
        }
        if (! Schema::hasColumn(self::TABLE, 'strategy_code')) {
            $table->string('strategy_code', 128);
        }
        if (! Schema::hasColumn(self::TABLE, 'endpoint_path')) {
            $table->string('endpoint_path', 255)->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'payload_schema')) {
            $table->string('payload_schema', 128)->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'carrier_chain')) {
            $table->string('carrier_chain', 64)->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'validating_carrier')) {
            $table->string('validating_carrier', 8)->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'route_pattern')) {
            $table->string('route_pattern', 64)->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'trip_type')) {
            $table->string('trip_type', 32)->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'segment_count')) {
            $table->unsignedTinyInteger('segment_count')->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'outcome')) {
            $table->string('outcome', 16);
        }
        if (! Schema::hasColumn(self::TABLE, 'success_count')) {
            $table->unsignedInteger('success_count')->default(0);
        }
        if (! Schema::hasColumn(self::TABLE, 'last_success_at')) {
            $table->timestamp('last_success_at')->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'last_success_booking_id')) {
            $table->unsignedBigInteger('last_success_booking_id')->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'failed_booking_id')) {
            $table->unsignedBigInteger('failed_booking_id')->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'host_error_family')) {
            $table->string('host_error_family', 64)->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'safe_reason_code')) {
            $table->string('safe_reason_code', 128)->nullable();
        }
        if (! Schema::hasColumn(self::TABLE, 'created_at')) {
            $table->timestamps();
        } elseif (! Schema::hasColumn(self::TABLE, 'updated_at')) {
            $table->timestamp('updated_at')->nullable();
        }
    }

    private function ensureIndexes(): void
    {
        foreach (self::INDEXES as $index) {
            if ($this->indexExists($index['name'])) {
                continue;
            }

            Schema::table(self::TABLE, function (Blueprint $table) use ($index): void {
                $table->index($index['columns'], $index['name']);
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        $rows = DB::select(
            'SHOW INDEX FROM `'.self::TABLE.'` WHERE Key_name = ?',
            [$indexName],
        );

        return $rows !== [];
    }
};
