<?php

namespace Tests\Unit;

use App\Data\FlightSegmentData;
use App\Support\Suppliers\SabreItineraryTimingValidator;
use App\Support\Suppliers\SabreSegmentChronologyRepair;
use Tests\TestCase;

class SabreSegmentChronologyRepairTest extends TestCase
{
    public function test_repairs_reversed_lhe_khi_dxb_when_second_leg_calendar_wrong(): void
    {
        $segments = [
            new FlightSegmentData(
                origin: 'LHE',
                destination: 'KHI',
                departure_at: '2026-05-30T05:00:00',
                arrival_at: '2026-05-30T06:45:00',
                flight_number: '301',
                airline_code: 'PK',
                airline_name: null,
                duration_minutes: 105,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
            new FlightSegmentData(
                origin: 'KHI',
                destination: 'DXB',
                departure_at: '2026-05-29T05:00:00',
                arrival_at: '2026-05-30T04:00:00',
                flight_number: '601',
                airline_code: 'EK',
                airline_name: null,
                duration_minutes: 135,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
        ];

        $this->assertFalse(SabreItineraryTimingValidator::analyzeFlightSegmentModels($segments)['ok']);

        $out = SabreSegmentChronologyRepair::repair($segments, '2026-05-30', true);
        $fixed = $out['segments'];
        $this->assertTrue(SabreItineraryTimingValidator::analyzeFlightSegmentModels($fixed)['ok']);
        $this->assertTrue($out['diagnostics']['date_repair_attempted']);
        $this->assertTrue($out['diagnostics']['date_repair_applied']);
        $this->assertGreaterThan(0, $out['diagnostics']['repaired_segment_count']);
        $this->assertTrue($out['diagnostics']['segment_order_corrected']);
        $this->assertTrue($out['diagnostics']['requested_departure_date_present']);

        $this->assertChronological($fixed);
    }

    public function test_repairs_direct_leg_when_arrival_before_departure_with_elapsed(): void
    {
        $segments = [
            new FlightSegmentData(
                origin: 'LHE',
                destination: 'DXB',
                departure_at: '2026-06-01T08:00:00',
                arrival_at: '2026-06-01T06:00:00',
                flight_number: '615',
                airline_code: 'EK',
                airline_name: null,
                duration_minutes: 120,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
        ];

        $out = SabreSegmentChronologyRepair::repair($segments, '2026-06-01', false);
        $fixed = $out['segments'][0];
        $this->assertSame('2026-06-01T08:00:00', $fixed->departure_at);
        $this->assertSame('2026-06-01T10:00:00', $fixed->arrival_at);
        $this->assertTrue(SabreItineraryTimingValidator::analyzeFlightSegmentModels($out['segments'])['ok']);
    }

    public function test_snaps_spurious_plus_one_day_on_short_pk_leg_so_layover_carries_multi_day_wait(): void
    {
        $segments = [
            new FlightSegmentData(
                origin: 'LHE',
                destination: 'KHI',
                departure_at: '2026-05-30T05:00:00',
                arrival_at: '2026-05-31T06:45:00',
                flight_number: '303',
                airline_code: 'PK',
                airline_name: null,
                duration_minutes: 105,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
            new FlightSegmentData(
                origin: 'KHI',
                destination: 'DXB',
                departure_at: '2026-06-01T05:00:00',
                arrival_at: '2026-06-01T07:10:00',
                flight_number: '603',
                airline_code: 'EK',
                airline_name: null,
                duration_minutes: 130,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
        ];

        $out = SabreSegmentChronologyRepair::repair($segments, '2026-05-30', false);
        $fixed = $out['segments'];
        $this->assertTrue(SabreItineraryTimingValidator::analyzeFlightSegmentModels($fixed)['ok']);
        $this->assertSame('2026-05-30T06:45:00', $fixed[0]->arrival_at);
        $this->assertSame(105, $fixed[0]->duration_minutes);
        $this->assertGreaterThanOrEqual(
            strtotime($fixed[0]->arrival_at),
            strtotime($fixed[1]->departure_at),
        );
    }

    public function test_impossible_connection_still_fails_after_max_day_slide(): void
    {
        $segments = [
            new FlightSegmentData(
                origin: 'LHE',
                destination: 'KHI',
                departure_at: '2026-09-01T03:00:00',
                arrival_at: '2026-09-01T04:30:00',
                flight_number: '301',
                airline_code: 'PK',
                airline_name: null,
                duration_minutes: 90,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
            new FlightSegmentData(
                origin: 'KHI',
                destination: 'DXB',
                departure_at: '2026-08-20T08:00:00',
                arrival_at: '2026-08-20T10:15:00',
                flight_number: '601',
                airline_code: 'EK',
                airline_name: null,
                duration_minutes: 135,
                operating_airline_code: null,
                operating_airline_name: null,
            ),
        ];

        $out = SabreSegmentChronologyRepair::repair($segments, '2026-09-01', false);
        $this->assertFalse(SabreItineraryTimingValidator::analyzeFlightSegmentModels($out['segments'])['ok']);
        $this->assertTrue($out['diagnostics']['date_repair_attempted']);
    }

    /**
     * @param  list<FlightSegmentData>  $segments
     */
    protected function assertChronological(array $segments): void
    {
        $n = count($segments);
        for ($i = 0; $i < $n; $i++) {
            $s = $segments[$i];
            $this->assertNotSame('', trim($s->departure_at));
            $this->assertNotSame('', trim($s->arrival_at));
            $this->assertGreaterThan(
                strtotime($s->departure_at),
                strtotime($s->arrival_at),
            );
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                strtotime($segments[$i]->arrival_at),
                strtotime($segments[$i + 1]->departure_at),
            );
        }
    }
}
