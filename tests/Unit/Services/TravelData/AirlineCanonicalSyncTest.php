<?php

namespace Tests\Unit\Services\TravelData;

use App\Models\Airline;
use App\Models\Airport;
use App\Services\TravelData\AirlineCanonicalResolver;
use App\Services\TravelData\AirlineCanonicalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AirlineCanonicalSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dry_run_performs_zero_writes(): void
    {
        Airline::query()->create([
            'iata_code' => 'PA',
            'icao_code' => 'IPV',
            'name' => 'Parmiss Airlines (IPV)',
            'country' => 'Iran',
            'is_active' => true,
        ]);

        $before = Airline::query()->count();
        $plan = app(AirlineCanonicalSyncService::class)->plan();
        $this->assertFalse($plan['db_write_attempted']);
        $this->assertSame($before, Airline::query()->count());
        $this->assertSame(1, $plan['update_count']);
    }

    #[Test]
    public function dry_run_command_reports_db_write_attempted_false(): void
    {
        Airline::query()->create([
            'iata_code' => 'PA',
            'name' => 'Parmiss Airlines (IPV)',
            'is_active' => true,
        ]);

        $before = Airline::query()->count();
        Artisan::call('jetpk:airlines:canonical-sync', ['--dry-run' => true]);
        $this->assertStringContainsString('db_write_attempted=false', Artisan::output());
        $this->assertSame($before, Airline::query()->count());
    }

    #[Test]
    public function apply_updates_only_configured_codes(): void
    {
        Airline::query()->create([
            'iata_code' => 'PA',
            'icao_code' => 'IPV',
            'name' => 'Parmiss Airlines (IPV)',
            'country' => 'Iran',
            'is_active' => true,
        ]);
        Airline::query()->create([
            'iata_code' => 'ZZ',
            'icao_code' => 'ZZZ',
            'name' => 'Unrelated Airline',
            'country' => 'Nowhere',
            'is_active' => true,
        ]);

        $result = app(AirlineCanonicalSyncService::class)->apply();
        $this->assertTrue($result['applied']);
        $this->assertSame(1, $result['update_count']);

        $this->assertDatabaseHas('airlines', [
            'iata_code' => 'PA',
            'name' => 'Airblue',
            'icao_code' => 'ABQ',
            'country' => 'Pakistan',
        ]);
        $this->assertDatabaseHas('airlines', [
            'iata_code' => 'ZZ',
            'name' => 'Unrelated Airline',
        ]);
    }

    #[Test]
    public function duplicate_iata_conflict_blocks_all_writes(): void
    {
        Airline::query()->create([
            'iata_code' => 'PA',
            'name' => 'Parmiss Airlines (IPV)',
            'is_active' => true,
        ]);
        Airline::query()->create([
            'iata_code' => 'PA',
            'name' => 'Airblue Duplicate',
            'is_active' => true,
        ]);

        $beforeNames = Airline::query()->orderBy('id')->pluck('name')->all();
        $result = app(AirlineCanonicalSyncService::class)->apply();

        $this->assertFalse($result['applied']);
        $this->assertSame(1, $result['conflict_count']);
        $this->assertFalse($result['db_write_attempted']);
        $this->assertSame($beforeNames, Airline::query()->orderBy('id')->pluck('name')->all());
    }

    #[Test]
    public function conflict_on_one_code_leaves_all_other_rows_unchanged(): void
    {
        Airline::query()->create([
            'iata_code' => 'PF',
            'name' => 'Primera Air',
            'is_active' => true,
        ]);
        Airline::query()->create([
            'iata_code' => 'PA',
            'name' => 'Parmiss Airlines (IPV)',
            'is_active' => true,
        ]);
        Airline::query()->create([
            'iata_code' => 'PA',
            'name' => 'Airblue Duplicate',
            'is_active' => true,
        ]);

        $result = app(AirlineCanonicalSyncService::class)->apply();

        $this->assertFalse($result['applied']);
        $this->assertDatabaseHas('airlines', ['iata_code' => 'PF', 'name' => 'Primera Air']);
        $this->assertDatabaseHas('airlines', ['iata_code' => 'PA', 'name' => 'Parmiss Airlines (IPV)']);
    }

    #[Test]
    public function second_apply_run_is_idempotent(): void
    {
        Airline::query()->create([
            'iata_code' => '9P',
            'name' => 'Pelangi',
            'icao_code' => 'XXX',
            'is_active' => true,
        ]);

        $first = app(AirlineCanonicalSyncService::class)->apply();
        $second = app(AirlineCanonicalSyncService::class)->apply();

        $this->assertSame(1, $first['update_count']);
        $this->assertSame(0, $second['update_count']);
        $this->assertSame(7, $second['unchanged_count']);
        $this->assertFalse($second['db_write_attempted']);
    }

    #[Test]
    public function sync_does_not_modify_airports(): void
    {
        Airport::query()->create([
            'iata_code' => 'LHE',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'is_active' => true,
        ]);

        Airline::query()->create([
            'iata_code' => 'SV',
            'name' => 'Saudi Arabian Airlines',
            'is_active' => true,
        ]);

        $airportBefore = Airport::query()->first()?->toArray();
        app(AirlineCanonicalSyncService::class)->apply();
        $this->assertSame($airportBefore, Airport::query()->first()?->toArray());
    }

    #[Test]
    public function production_mismatch_rows_are_corrected(): void
    {
        $rows = [
            ['PA', 'Parmiss Airlines (IPV)', 'IPV'],
            ['PF', 'Primera Air', 'PRI'],
            ['9P', 'Pelangi', 'XXX'],
            ['SV', 'Saudi Arabian Airlines', 'SVA'],
        ];
        foreach ($rows as [$iata, $name, $icao]) {
            Airline::query()->create([
                'iata_code' => $iata,
                'icao_code' => $icao,
                'name' => $name,
                'is_active' => true,
            ]);
        }
        foreach (['PK', 'G9', 'WY'] as $code) {
            $override = app(AirlineCanonicalResolver::class)->overrideForIata($code);
            Airline::query()->create(app(AirlineCanonicalResolver::class)->payloadFromOverride($override));
        }

        $result = app(AirlineCanonicalSyncService::class)->apply();
        $this->assertTrue($result['applied']);
        $this->assertSame(4, $result['update_count']);

        $this->assertDatabaseHas('airlines', ['iata_code' => 'PA', 'name' => 'Airblue']);
        $this->assertDatabaseHas('airlines', ['iata_code' => 'PF', 'name' => 'AirSial']);
        $this->assertDatabaseHas('airlines', ['iata_code' => '9P', 'name' => 'Fly Jinnah']);
        $this->assertDatabaseHas('airlines', ['iata_code' => 'SV', 'name' => 'Saudia']);

        Artisan::call('jetpk:airline-code-ambiguity-audit');
        $this->assertStringContainsString('fail_count=0', Artisan::output());
    }

    #[Test]
    public function apply_preserves_existing_row_id(): void
    {
        $row = Airline::query()->create([
            'iata_code' => 'PA',
            'name' => 'Parmiss Airlines (IPV)',
            'is_active' => true,
        ]);
        $id = $row->id;

        app(AirlineCanonicalSyncService::class)->apply();

        $this->assertSame($id, Airline::query()->where('iata_code', 'PA')->value('id'));
    }
}
