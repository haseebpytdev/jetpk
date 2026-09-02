<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Models\Airport;
use App\Support\FlightSearch\AirportReferenceLookup;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AirportReferenceLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AirportReferenceLookup::flushRequestMemo();
    }

    public function test_airport_lookup_is_memoized_within_request(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Airport::query()->updateOrCreate(
            ['iata_code' => 'LHE'],
            ['name' => 'Allama Iqbal', 'city' => 'Lahore', 'country' => 'Pakistan']
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = AirportReferenceLookup::cityCountryLine('LHE');
        $afterFirst = count(DB::getQueryLog());
        $second = AirportReferenceLookup::cityCountryLine('LHE');
        $afterSecond = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertStringContainsString('Lahore', $first);
        $this->assertSame($afterFirst, $afterSecond);
    }
}
