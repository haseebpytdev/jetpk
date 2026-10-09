<?php

namespace Tests\Feature\Console;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneJpqaWriteSupplierConnectionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_candidates_without_deleting(): void
    {
        $agency = Agency::factory()->create();
        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'JPQA-WRITE-1791479357017-api',
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
        ]);
        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'AirBlue Zapways TEST v2',
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $this->artisan('supplier:prune-jpqa-write-connections')
            ->expectsOutputToContain('QA_DELETE_CANDIDATES=1')
            ->assertSuccessful();

        $this->assertDatabaseHas('supplier_connections', ['name' => 'JPQA-WRITE-1791479357017-api']);
        $this->assertDatabaseHas('supplier_connections', ['name' => 'AirBlue Zapways TEST v2']);
    }

    public function test_execute_deletes_only_jpqa_write_rows(): void
    {
        $agency = Agency::factory()->create();
        $qa = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'JPQA-WRITE-1791480233772-api',
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
        ]);
        $real = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'PIA JetPK',
            'provider' => SupplierProvider::PiaNdc,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $this->artisan('supplier:prune-jpqa-write-connections', ['--execute' => true])
            ->expectsOutputToContain('QA_RECORDS_DELETED=1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('supplier_connections', ['id' => $qa->id]);
        $this->assertDatabaseHas('supplier_connections', ['id' => $real->id]);
    }
}
