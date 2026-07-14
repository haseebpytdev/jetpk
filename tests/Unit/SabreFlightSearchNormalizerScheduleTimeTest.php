<?php

namespace Tests\Unit;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class SabreFlightSearchNormalizerScheduleTimeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('offsetClockPortionProvider')]
    public function test_extract_clock_portion_preserves_wall_clock_over_timezone_suffix(
        string $raw,
        string $expected,
    ): void {
        $this->assertSame($expected, $this->invokeExtractClockPortion($raw));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function offsetClockPortionProvider(): array
    {
        return [
            'live offset hhmmss' => ['11:00:00+05:00', '11:00:00'],
            'live offset another zone' => ['16:10:00+04:00', '16:10:00'],
            'zulu suffix' => ['11:00:00Z', '11:00:00'],
            'plain hhmmss' => ['11:00:00', '11:00:00'],
            'short clock' => ['11:00', '11:00:00'],
            'iso datetime with offset' => ['2026-05-30T11:00:00+05:00', '11:00:00'],
            'iso datetime without offset' => ['2026-08-15T02:15:00', '02:15:00'],
            'iso datetime with offset different date' => ['2026-08-15T02:15:00+05:00', '02:15:00'],
            'half hour offset' => ['11:30:00+05:00', '11:30:00'],
        ];
    }

    #[Test]
    public function test_live_offset_schedule_time_anchors_to_search_date(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_lhe_ist_doh_time_only_20260530.json')), true);
        $fixture['groupedItineraryResponse']['scheduleDescs'][0]['departure']['time'] = '11:00:00+05:00';

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-05-30',
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $this->assertSame('2026-05-30T11:00:00', $offers[0]->segments[0]['departure_at']);
    }

    #[Test]
    public function test_iso_schedule_times_regression_still_normalize(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_lhe_ist_doh_time_only_20260530.json')), true);
        $fixture['groupedItineraryResponse']['scheduleDescs'][0]['departure']['time'] = '2026-05-30T02:30:00';
        $fixture['groupedItineraryResponse']['scheduleDescs'][0]['arrival']['time'] = '2026-05-30T06:30:00';

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-05-30',
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $this->assertSame('2026-05-30T02:30:00', $offers[0]->segments[0]['departure_at']);
        $this->assertSame('2026-05-30T06:30:00', $offers[0]->segments[0]['arrival_at']);
    }

    protected function invokeExtractClockPortion(string $raw): ?string
    {
        $normalizer = app(SabreFlightSearchNormalizer::class);
        $method = new ReflectionMethod(SabreFlightSearchNormalizer::class, 'extractClockPortion');
        $method->setAccessible(true);

        return $method->invoke($normalizer, $raw);
    }
}
