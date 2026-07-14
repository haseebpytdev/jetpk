<?php

namespace Tests\Unit;

use App\Data\FlightSegmentData;
use App\Support\Suppliers\SabreItineraryTimingValidator;
use Tests\TestCase;

class SabreItineraryTimingValidatorTest extends TestCase
{
    public function test_rejects_when_second_segment_departs_before_first_arrival_but_airports_connect(): void
    {
        $segments = [
            [
                'origin' => 'LHE',
                'destination' => 'KHI',
                'departure_at' => '2026-05-30T05:00:00',
                'arrival_at' => '2026-05-30T06:45:00',
            ],
            [
                'origin' => 'KHI',
                'destination' => 'DXB',
                'departure_at' => '2026-05-29T05:00:00',
                'arrival_at' => '2026-05-30T04:00:00',
            ],
        ];
        $a = SabreItineraryTimingValidator::analyzeSegmentArrays($segments);
        $this->assertFalse($a['ok']);
        $this->assertTrue($a['airport_continuity_ok']);
        $this->assertFalse($a['chronology_ok']);
        $this->assertGreaterThan(0, $a['failed_time_link_count']);
    }

    public function test_accepts_chronological_lhe_khi_dxb(): void
    {
        $segments = [
            [
                'origin' => 'LHE',
                'destination' => 'KHI',
                'departure_at' => '2026-05-30T05:00:00',
                'arrival_at' => '2026-05-30T06:45:00',
            ],
            [
                'origin' => 'KHI',
                'destination' => 'DXB',
                'departure_at' => '2026-05-30T08:00:00',
                'arrival_at' => '2026-05-30T11:00:00',
            ],
        ];
        $a = SabreItineraryTimingValidator::analyzeSegmentArrays($segments);
        $this->assertTrue($a['ok']);
        $this->assertSame(0, $a['failed_time_link_count']);
        $this->assertSame(0, $a['invalid_segment_duration_count']);
    }

    public function test_flight_segment_models_delegates_consistently(): void
    {
        $models = [
            new FlightSegmentData(
                origin: 'AAA',
                destination: 'BBB',
                departure_at: '2026-01-01T10:00:00',
                arrival_at: '2026-01-01T11:00:00',
                flight_number: '1',
                airline_code: 'XX',
                airline_name: null,
                duration_minutes: 60,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
            new FlightSegmentData(
                origin: 'BBB',
                destination: 'CCC',
                departure_at: '2026-01-01T11:30:00',
                arrival_at: '2026-01-01T13:00:00',
                flight_number: '2',
                airline_code: 'XX',
                airline_name: null,
                duration_minutes: 90,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
        ];
        $this->assertTrue(SabreItineraryTimingValidator::analyzeFlightSegmentModels($models)['ok']);
    }
}
