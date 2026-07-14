<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow multiple supplier_connections per agency+provider; enforce uniqueness
     * on (agency_id, provider, display name) instead.
     *
     * Down() restores the old unique(agency_id, provider) only when safe — it will
     * fail if duplicate provider rows exist for an agency.
     */
    public function up(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table): void {
            $table->dropUnique(['agency_id', 'provider']);
        });

        $this->backfillBlankNames();
        $this->dedupeNamesPerAgencyAndProvider();

        Schema::table('supplier_connections', function (Blueprint $table): void {
            $table->unique(
                ['agency_id', 'provider', 'name'],
                'supplier_connections_agency_provider_name_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table): void {
            $table->dropUnique('supplier_connections_agency_provider_name_unique');
        });

        Schema::table('supplier_connections', function (Blueprint $table): void {
            $table->unique(['agency_id', 'provider']);
        });
    }

    protected function backfillBlankNames(): void
    {
        $rows = DB::table('supplier_connections')
            ->where(function ($q): void {
                $q->whereNull('name')->orWhere('name', '');
            })
            ->orderBy('id')
            ->get(['id', 'provider', 'display_name']);

        foreach ($rows as $row) {
            $label = trim((string) ($row->display_name ?? ''));
            if ($label === '') {
                $label = ucfirst((string) $row->provider).' connection '.$row->id;
            }
            DB::table('supplier_connections')->where('id', $row->id)->update(['name' => $label]);
        }
    }

    /**
     * Ensure (agency_id, provider, name) is unique before adding the DB constraint.
     */
    protected function dedupeNamesPerAgencyAndProvider(): void
    {
        $seen = [];
        $rows = DB::table('supplier_connections')->orderBy('id')->get(['id', 'agency_id', 'provider', 'name']);

        foreach ($rows as $row) {
            $name = (string) $row->name;
            $key = $row->agency_id.'|'.$row->provider.'|'.$name;
            if (isset($seen[$key])) {
                $newName = $name.' #'.$row->id;
                DB::table('supplier_connections')->where('id', $row->id)->update(['name' => $newName]);
                $key = $row->agency_id.'|'.$row->provider.'|'.$newName;
            }
            $seen[$key] = true;
        }
    }
};
