<?php

namespace Tests\Feature\Console;

use App\Console\Commands\NormalizeSupplierConnectionNamesCommand;
use App\Models\SupplierConnection;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizeSupplierConnectionNamesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_dry_run_lists_legacy_names_only(): void
    {
        $connection = SupplierConnection::factory()->create(['name' => 'JPak Group']);

        $this->artisan('supplier:normalize-connection-names')
            ->expectsOutputToContain('NORMALIZE_CANDIDATES=1')
            ->assertSuccessful();

        $this->assertSame('JPak Group', $connection->fresh()->name);
    }

    public function test_execute_renames_exact_legacy_label(): void
    {
        $connection = SupplierConnection::factory()->create(['name' => 'sabre-sandbox-qa']);

        $this->artisan('supplier:normalize-connection-names', ['--execute' => true])
            ->expectsOutputToContain('NORMALIZE_APPLIED=1')
            ->assertSuccessful();

        $this->assertSame('Sabre Sandbox QA (CERT)', $connection->fresh()->name);
    }
}
