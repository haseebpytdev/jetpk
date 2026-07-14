<?php

namespace Tests\Feature;

use App\Models\Airport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AirportImportTest extends TestCase
{
    use RefreshDatabase;

    protected function fixturePath(): string
    {
        return base_path('tests/Fixtures/travel-data/ourairports-sample.csv');
    }

    public function test_import_dry_run_does_not_write(): void
    {
        $this->artisan('airports:import', [
            '--source' => $this->fixturePath(),
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('airports', 0);
    }

    public function test_import_upserts_valid_iata_airports(): void
    {
        $this->artisan('airports:import', [
            '--source' => $this->fixturePath(),
        ])->assertExitCode(0);

        $this->assertDatabaseHas('airports', [
            'iata_code' => 'LHE',
            'city' => 'Lahore',
            'is_active' => true,
            'is_commercial' => true,
        ]);
        $this->assertDatabaseHas('airports', [
            'iata_code' => 'DXB',
            'city' => 'Dubai',
        ]);
        $this->assertDatabaseHas('airports', [
            'iata_code' => 'LYP',
            'city' => 'Faisalabad',
        ]);
    }

    public function test_import_skips_closed_and_no_iata_rows(): void
    {
        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);

        $this->assertDatabaseMissing('airports', ['iata_code' => 'STN']);
        $this->assertDatabaseMissing('airports', ['name' => 'No IATA Heliport']);
        $this->assertDatabaseMissing('airports', ['iata_code' => 'XXX']);
    }

    public function test_import_applies_config_overrides(): void
    {
        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);

        $lhe = Airport::query()->where('iata_code', 'LHE')->first();
        $this->assertNotNull($lhe);
        $this->assertSame('Allama Iqbal International Airport', $lhe->name);
        $this->assertGreaterThanOrEqual(280, (int) $lhe->priority_score);
    }

    public function test_exact_iata_search_ranks_first(): void
    {
        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);

        $json = $this->getJson('/airports/search?q=LHR')->assertOk()->json();
        $this->assertNotEmpty($json);
        $this->assertSame('LHR', $json[0]['iata']);
    }

    public function test_lahore_search_returns_lhe(): void
    {
        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);

        $json = $this->getJson('/airports/search?q=Lahore')->assertOk()->json();
        $iatas = array_column($json, 'iata');
        $this->assertContains('LHE', $iatas);
        $this->assertStringContainsString('LHE', $json[0]['label'] ?? '');
        $this->assertStringContainsString('Lahore', $json[0]['label'] ?? '');
    }

    public function test_dubai_search_returns_dxb(): void
    {
        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);

        $json = $this->getJson('/airports/search?q=Dubai')->assertOk()->json();
        $this->assertNotEmpty($json);
        $this->assertSame('DXB', $json[0]['iata']);
        $this->assertStringContainsString('DXB —', $json[0]['label'] ?? '');
        $this->assertStringContainsString('Dubai', $json[0]['label'] ?? '');
    }

    public function test_london_search_returns_lhr_before_lgw(): void
    {
        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);

        $json = $this->getJson('/airports/search?q=London')->assertOk()->json();
        $this->assertGreaterThanOrEqual(2, count($json));
        $this->assertSame('LHR', $json[0]['iata']);
        $iatas = array_column($json, 'iata');
        $this->assertContains('LGW', $iatas);
        $this->assertStringContainsString('LHR', $json[0]['label'] ?? '');
        $this->assertStringContainsString('Heathrow', $json[0]['name'] ?? '');
    }

    public function test_autocomplete_response_shape_unchanged_for_mobile_and_desktop(): void
    {
        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);

        $json = $this->getJson('/airports/search?q=LHE')->assertOk()->json();
        $this->assertNotEmpty($json);

        $row = $json[0];
        $this->assertArrayHasKey('iata', $row);
        $this->assertArrayHasKey('iata_code', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('city', $row);
        $this->assertArrayHasKey('country', $row);
        $this->assertArrayHasKey('label', $row);
        $this->assertArrayHasKey('description', $row);
        $this->assertSame($row['iata'], $row['iata_code']);
        $this->assertArrayNotHasKey('meta', $row);
    }

    public function test_reimport_updates_existing_iata_row(): void
    {
        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);
        $first = Airport::query()->where('iata_code', 'LHE')->value('updated_at');

        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);
        $second = Airport::query()->where('iata_code', 'LHE')->value('updated_at');

        $this->assertSame(1, Airport::query()->where('iata_code', 'LHE')->count());
        $this->assertNotNull($first);
        $this->assertNotNull($second);
    }

    public function test_import_does_not_prune_extra_rows_by_default(): void
    {
        Airport::query()->create([
            'iata_code' => 'ZZZ',
            'name' => 'Manual Airport',
            'city' => 'Nowhere',
            'country' => 'Test',
            'is_active' => true,
            'is_commercial' => true,
        ]);

        Artisan::call('airports:import', ['--source' => $this->fixturePath()]);

        $this->assertDatabaseHas('airports', ['iata_code' => 'ZZZ']);
    }

    public function test_explicit_prune_removes_rows_not_in_source(): void
    {
        Airport::query()->create([
            'iata_code' => 'ZZZ',
            'name' => 'Stale Airport',
            'city' => 'Nowhere',
            'country' => 'Test',
            'is_active' => true,
            'is_commercial' => true,
        ]);

        $this->artisan('airports:import', [
            '--source' => $this->fixturePath(),
            '--prune-not-in-source' => true,
        ])->expectsConfirmation('Prune airports not present in the source CSV?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('airports', ['iata_code' => 'ZZZ']);
    }
}
