<?php

namespace Tests\Unit\Support\Audits;

use App\Services\TravelData\AirportImportService;
use App\Support\Audits\JetpkAirportParityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JetpkAirportParityAuditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function source_analyzer_counts_unique_eligible_iata_from_fixture(): void
    {
        $analysis = app(AirportImportService::class)->analyzeSource(
            base_path('tests/Fixtures/travel-data/ourairports-sample.csv'),
        );

        $this->assertGreaterThan(0, $analysis['unique_eligible_iata_count']);
        $this->assertSame($analysis['unique_eligible_iata_count'], count($analysis['eligible_iata_codes']));
        $this->assertGreaterThanOrEqual($analysis['unique_eligible_iata_count'], $analysis['eligible_rows_before_dedup']);
    }

    #[Test]
    public function airport_data_parity_audit_writes_json_and_markdown(): void
    {
        $result = app(JetpkAirportParityAuditService::class)->airportDataParityAudit(
            base_path('tests/Fixtures/travel-data/ourairports-sample.csv'),
        );

        $this->assertFileExists($result['path_json']);
        $this->assertFileExists($result['path_md']);
        $this->assertArrayHasKey('count_reconciliation', $result['report']);
    }

    #[Test]
    public function airport_data_parity_audit_command_is_read_only(): void
    {
        $before = \App\Models\Airport::query()->count();
        $exitCode = \Illuminate\Support\Facades\Artisan::call('jetpk:airport-data-parity-audit', [
            '--source' => base_path('tests/Fixtures/travel-data/ourairports-sample.csv'),
        ]);
        $after = \App\Models\Airport::query()->count();

        $this->assertSame($before, $after);
        $this->assertContains($exitCode, [0, 1]);
    }

    #[Test]
    public function export_and_restore_backup_round_trip(): void
    {
        \App\Models\Airport::query()->create([
            'iata_code' => 'TST',
            'name' => 'Test Airport',
            'city' => 'Test City',
            'country' => 'Pakistan',
            'is_active' => true,
            'is_commercial' => true,
        ]);

        $path = storage_path('app/audits/jetpk-airport-parity/test-backup.json');
        File::ensureDirectoryExists(dirname($path));

        $this->artisan('travel-data:export-airport-airline-backup', ['--path' => $path])->assertExitCode(0);

        \App\Models\Airport::query()->delete();
        $this->assertSame(0, \App\Models\Airport::query()->count());

        $this->artisan('travel-data:restore-airport-airline-backup', [
            '--path' => $path,
            '--authoritative' => true,
        ])->expectsConfirmation('Authoritative restore will delete rows not in the backup. Continue?', 'yes')
            ->assertExitCode(0);
        $this->assertDatabaseHas('airports', ['iata_code' => 'TST']);
    }

    #[Test]
    public function reserved_nul_iata_is_excluded_from_eligible_source_count(): void
    {
        $csv = storage_path('app/audits/jetpk-airport-parity/test-nul-row.csv');
        File::ensureDirectoryExists(dirname($csv));
        File::put($csv, implode("\n", [
            'id,ident,type,name,latitude_deg,longitude_deg,elevation_ft,continent,iso_country,iso_region,municipality,scheduled_service,gps_code,iata_code,local_code,home_link,wikipedia_link,keywords',
            '5417,PANU,small_airport,Nulato Airport,64.729301,-158.074005,399,NA,US,US-AK,Nulato,yes,PANU,NUL,PANU,NUL,,,',
        ]));

        $analysis = app(AirportImportService::class)->analyzeSource($csv);
        $this->assertSame(0, $analysis['unique_eligible_iata_count']);
        $this->assertContains('NUL', $analysis['reserved_iata_codes_rejected']);
        File::delete($csv);
    }

    #[Test]
    public function normalize_iata_rejects_reserved_nul_code(): void
    {
        $reflection = new \ReflectionClass(AirportImportService::class);
        $method = $reflection->getMethod('normalizeIata');
        $method->setAccessible(true);
        $service = app(AirportImportService::class);
        $this->assertNull($method->invoke($service, 'NUL'));
        $this->assertSame('LHE', $method->invoke($service, 'lhe'));
    }

    #[Test]
    public function airline_logo_resolver_returns_local_generic_without_master_url(): void
    {
        $url = app(\App\Services\TravelData\AirlineBrandingService::class)->getLogoForCode('ZZ');
        $this->assertIsString($url);
        $this->assertStringStartsWith('/', $url);
        $this->assertStringNotContainsString('haseebasif.com', $url);
        $this->assertStringNotContainsString('parwaaz', strtolower($url));
    }
}
