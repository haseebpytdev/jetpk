<?php

namespace Tests\Unit\Services\TravelData;

use App\Models\Airline;
use App\Services\TravelData\AirlineBrandingService;
use App\Services\TravelData\AirlineCanonicalResolver;
use App\Services\TravelData\AirlineCsvImportService;
use App\Services\TravelData\AirlineLogoCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AirlineCanonicalResolutionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('requiredJetpkMappingsProvider')]
    public function canonical_resolver_maps_required_codes(string $code, string $expectedName): void
    {
        $resolver = app(AirlineCanonicalResolver::class);
        $this->assertSame($expectedName, $resolver->canonicalDisplayName($code));
        $this->assertSame($code, $resolver->resolveToCanonicalIata($code));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function requiredJetpkMappingsProvider(): array
    {
        return [
            'PK' => ['PK', 'Pakistan International Airlines'],
            'PA' => ['PA', 'Airblue'],
            'PF' => ['PF', 'AirSial'],
            '9P' => ['9P', 'Fly Jinnah'],
            'SV' => ['SV', 'Saudia'],
            'G9' => ['G9', 'Air Arabia'],
            'WY' => ['WY', 'Oman Air'],
        ];
    }

    #[Test]
    public function supplier_aliases_resolve_to_canonical_iata(): void
    {
        $resolver = app(AirlineCanonicalResolver::class);
        $this->assertSame('PK', $resolver->resolveToCanonicalIata('PIA'));
        $this->assertSame('PA', $resolver->resolveToCanonicalIata('ABQ'));
        $this->assertSame('SV', $resolver->resolveToCanonicalIata('SVA'));
    }

    #[Test]
    public function obsolete_duplicate_row_cannot_overwrite_canonical_airline(): void
    {
        Airline::query()->create([
            'iata_code' => 'PA',
            'icao_code' => 'ABQ',
            'name' => 'Airblue',
            'country' => 'Pakistan',
            'is_active' => true,
            'meta' => ['canonical_override' => true, 'source' => 'jetpk-canonical-override'],
        ]);

        $csv = storage_path('app/audits/jetpk-airport-parity/test-pa-duplicate.csv');
        file_put_contents($csv, implode("\n", [
            'Name,IATA,ICAO,Country,Active',
            'Parmiss Airlines (IPV),PA,IPV,Iran,Y',
            'Airblue,PA,ABQ,Pakistan,Y',
        ]));

        app(AirlineCsvImportService::class)->import($csv, false);

        $this->assertDatabaseHas('airlines', [
            'iata_code' => 'PA',
            'name' => 'Airblue',
        ]);
        $this->assertDatabaseMissing('airlines', ['name' => 'Parmiss Airlines (IPV)']);
        unlink($csv);
    }

    #[Test]
    public function canonical_import_is_idempotent(): void
    {
        $csv = storage_path('app/audits/jetpk-airport-parity/test-canonical-pk.csv');
        file_put_contents($csv, implode("\n", [
            'Name,IATA,ICAO,Country,Active',
            'Pakistan International Airlines,PK,PIA,Pakistan,Y',
        ]));

        $service = app(AirlineCsvImportService::class);
        $first = $service->import($csv, false);
        $second = $service->import($csv, false);

        $this->assertSame(1, Airline::query()->where('iata_code', 'PK')->count());
        $this->assertGreaterThan(0, $first['imported'] + $first['updated']);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(0, $second['updated']);
        unlink($csv);
    }

    #[Test]
    public function dry_run_performs_no_writes(): void
    {
        $before = Airline::query()->count();
        $csv = base_path('tests/Fixtures/travel-data/airlines-canonical-sample.csv');
        if (! is_file($csv)) {
            file_put_contents($csv, implode("\n", [
                'Name,IATA,ICAO,Country,Active',
                'Airblue,PA,ABQ,Pakistan,Y',
            ]));
        }
        $stats = app(AirlineCsvImportService::class)->import($csv, true);
        $this->assertFalse($stats['db_write_attempted']);
        $this->assertSame($before, Airline::query()->count());
    }

    #[Test]
    public function dry_run_command_reports_db_write_attempted_false(): void
    {
        $path = storage_path('app/imports/kaggle/airports-global/airlines.csv');
        if (! is_file($path)) {
            $this->markTestSkipped('airlines.csv fixture not present');
        }
        $before = Airline::query()->count();
        Artisan::call('ota:import-airports-airlines', ['--airlines-only' => true, '--dry-run' => true]);
        $this->assertStringContainsString('db_write_attempted=false', Artisan::output());
        $this->assertSame($before, Airline::query()->count());
    }

    #[Test]
    public function analyze_database_accounting_reconciles_unique_db_ids(): void
    {
        Airline::query()->create([
            'iata_code' => 'PA',
            'icao_code' => 'ABQ',
            'name' => 'Airblue',
            'is_active' => true,
        ]);
        Airline::query()->create([
            'iata_code' => 'XX',
            'icao_code' => 'XXX',
            'name' => 'Collision Target Airline',
            'is_active' => true,
        ]);

        $csv = storage_path('app/audits/jetpk-airport-parity/test-accounting.csv');
        file_put_contents($csv, implode("\n", [
            'Name,IATA,ICAO,Country,Active',
            'Airblue,PA,ABQ,Pakistan,Y',
            'Alias hits same row,PA,ABQ,Pakistan,Y',
            'New Airline,ZZ,ZZZ,Pakistan,Y',
        ]));

        $analysis = app(AirlineCsvImportService::class)->analyze($csv);
        $db = $analysis['database_accounting'];
        $this->assertSame(2, $db['current_database_rows']);
        $this->assertSame(2, $db['unique_normalized_source_targets']);
        $this->assertSame(1, $db['inserts']);
        $this->assertSame(1, $db['unique_matched_db_row_ids']);
        $this->assertSame(1, $db['updates'] + $db['unchanged']);
        $this->assertTrue($db['reconciliation']['unique_matched_db_ids_equals_updates_plus_unchanged']);
        $this->assertSame(3, $db['expected_post_import_database_rows']);
        unlink($csv);
    }

    #[Test]
    public function analyze_target_metrics_reconcile(): void
    {
        $path = storage_path('app/imports/kaggle/airports-global/airlines.csv');
        if (! is_file($path)) {
            $this->markTestSkipped('airlines.csv fixture not present');
        }
        $analysis = app(AirlineCsvImportService::class)->analyze($path);
        $targets = (int) ($analysis['target_metrics']['unique_canonical_targets'] ?? 0);
        $sum = (int) ($analysis['target_metrics']['inserts'] ?? 0)
            + (int) ($analysis['target_metrics']['updates'] ?? 0)
            + (int) ($analysis['target_metrics']['unchanged'] ?? 0);
        $this->assertSame($targets, $sum);

        $db = $analysis['database_accounting'];
        $this->assertSame(
            (int) $db['updates'] + (int) $db['unchanged'],
            (int) $db['unique_matched_db_row_ids'],
        );
        $this->assertSame(
            (int) $db['current_database_rows'] + (int) $db['inserts'],
            (int) $db['expected_post_import_database_rows'],
        );
        $this->assertLessThanOrEqual(
            (int) $db['current_database_rows'],
            (int) $db['unique_matched_db_row_ids'],
        );
    }

    #[Test]
    public function branding_uses_canonical_logo_code_without_master_url(): void
    {
        Config::set('ota.airline_logo_cache.download_on_miss', false);
        $cache = \Mockery::mock(AirlineLogoCacheService::class);
        $cache->shouldReceive('genericFallbackPublicUrl')->andReturn('/images/airline-generic.svg');
        $cache->shouldReceive('resolvePublicUrl')->with('PA', false)->andReturn('/storage/airline-logos/PA.png');

        $branding = new AirlineBrandingService($cache, app(AirlineCanonicalResolver::class));
        $url = $branding->getLogoForCode('PA');
        $this->assertSame('/storage/airline-logos/PA.png', $url);
        $this->assertStringNotContainsString('haseebasif.com', (string) $url);
    }

    #[Test]
    public function unknown_code_falls_back_to_generic_logo(): void
    {
        Config::set('ota.airline_logo_cache.download_on_miss', false);
        $branding = app(AirlineBrandingService::class);
        $this->assertSame('/images/airline-generic.svg', $branding->getLogoForCode('ZZZ'));
    }

    #[Test]
    public function branding_display_name_uses_canonical_not_obsolete_db_row(): void
    {
        Airline::query()->create([
            'iata_code' => 'PA',
            'name' => 'Parmiss Airlines (IPV)',
            'is_active' => true,
        ]);
        $this->assertSame('Airblue', app(AirlineBrandingService::class)->getDisplayNameForCode('PA'));
    }
}
