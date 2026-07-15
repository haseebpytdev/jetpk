<?php

namespace Tests\Unit\Services\TravelData;

use App\Models\Airline;
use App\Models\Airport;
use App\Services\TravelData\TravelDataAirportAirlineBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TravelDataAirportAirlineBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authoritative_restore_restores_exact_row_set(): void
    {
        $airport = Airport::query()->create([
            'iata_code' => 'AAA',
            'name' => 'Alpha',
            'city' => 'City A',
            'country' => 'Test',
            'is_active' => true,
            'is_commercial' => true,
        ]);
        Airport::query()->create([
            'iata_code' => 'BBB',
            'name' => 'Beta',
            'city' => 'City B',
            'country' => 'Test',
            'is_active' => true,
            'is_commercial' => true,
        ]);

        $path = storage_path('app/audits/jetpk-airport-parity/test-authoritative-backup.json');
        File::ensureDirectoryExists(dirname($path));
        app(TravelDataAirportAirlineBackupService::class)->export($path);

        Airport::query()->create([
            'iata_code' => 'ZZZ',
            'name' => 'Post-backup row',
            'city' => 'Extra',
            'country' => 'Test',
            'is_active' => true,
            'is_commercial' => true,
        ]);
        $airport->update(['city' => 'Mutated']);

        $this->artisan('travel-data:restore-airport-airline-backup', [
            '--path' => $path,
            '--authoritative' => true,
        ])->expectsConfirmation('Authoritative restore will delete rows not in the backup. Continue?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseHas('airports', ['iata_code' => 'AAA', 'city' => 'City A']);
        $this->assertDatabaseHas('airports', ['iata_code' => 'BBB']);
        $this->assertDatabaseMissing('airports', ['iata_code' => 'ZZZ']);
        $this->assertSame(2, Airport::query()->count());
    }

    #[Test]
    public function restore_dry_run_reports_without_writing(): void
    {
        Airport::query()->create([
            'iata_code' => 'TST',
            'name' => 'Test',
            'city' => 'City',
            'country' => 'Test',
            'is_active' => true,
            'is_commercial' => true,
        ]);
        $path = storage_path('app/audits/jetpk-airport-parity/test-dry-run-backup.json');
        File::ensureDirectoryExists(dirname($path));
        app(TravelDataAirportAirlineBackupService::class)->export($path);

        Airport::query()->delete();
        $this->artisan('travel-data:restore-airport-airline-backup', [
            '--path' => $path,
            '--dry-run' => true,
            '--authoritative' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, Airport::query()->count());
    }

    #[Test]
    public function export_includes_schema_ids_and_checksum(): void
    {
        Airline::query()->create([
            'iata_code' => 'PK',
            'name' => 'Pakistan International Airlines',
            'is_active' => true,
        ]);
        $path = storage_path('app/audits/jetpk-airport-parity/test-export-meta.json');
        File::ensureDirectoryExists(dirname($path));
        $result = app(TravelDataAirportAirlineBackupService::class)->export($path);
        $payload = json_decode((string) file_get_contents($path), true);

        $this->assertNotEmpty($result['sha256']);
        $this->assertSame($result['sha256'], $payload['sha256'] ?? null);
        $this->assertNotEmpty($payload['schema']['airlines'] ?? []);
        $this->assertContains('PK', collect($payload['airlines'])->pluck('iata_code')->all());
    }
}
