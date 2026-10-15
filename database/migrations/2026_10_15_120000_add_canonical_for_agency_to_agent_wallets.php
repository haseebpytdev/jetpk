<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-DEF-0007: mark at most one operational canonical wallet per agency.
 *
 * Semantics: canonical_for_agency = 1 (canonical) or NULL (historical).
 * UNIQUE(agency_id, canonical_for_agency) allows many NULL historical rows
 * while permitting only one canonical row per agency (MySQL/MariaDB/SQLite NULL unique semantics).
 *
 * Refuse automatic assignment when an agency already has multiple wallet rows.
 */
return new class extends Migration
{
    public const UNIQUE_INDEX = 'agent_wallets_agency_canonical_unique';

    public function up(): void
    {
        Schema::table('agent_wallets', function (Blueprint $table): void {
            $table->unsignedTinyInteger('canonical_for_agency')->nullable()->default(null)->after('status');
        });

        $ambiguousAgencyIds = DB::table('agent_wallets')
            ->select('agency_id')
            ->groupBy('agency_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('agency_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ambiguousAgencyIds !== []) {
            throw new \RuntimeException(
                'OPS-DEF-0007 migration refused: agencies with multiple agent_wallets rows require '.
                'manual reconciliation before canonical backfill. agency_ids='.
                implode(',', $ambiguousAgencyIds)
            );
        }

        // Clean one-wallet-per-agency state: mark each sole wallet canonical.
        DB::table('agent_wallets')->whereNotNull('agency_id')->update([
            'canonical_for_agency' => 1,
        ]);

        Schema::table('agent_wallets', function (Blueprint $table): void {
            $table->unique(['agency_id', 'canonical_for_agency'], self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('agent_wallets', function (Blueprint $table): void {
            $table->dropUnique(self::UNIQUE_INDEX);
        });

        Schema::table('agent_wallets', function (Blueprint $table): void {
            $table->dropColumn('canonical_for_agency');
        });
    }
};
