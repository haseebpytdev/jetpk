<?php

namespace Tests\Unit;

use App\Data\FlightSegmentData;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\Suppliers\SabreSegmentChronologyRepair;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FlightOfferDisplayPresenterTest extends TestCase
{
    public function test_non_stop_itinerary_has_no_layover_between_segments(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T08:00:00',
            'arrival_at' => '2026-06-10T11:00:00',
            'duration_minutes' => 180,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-10T08:00:00',
                    'arrival_at' => '2026-06-10T11:00:00',
                    'duration_minutes' => 180,
                ],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertNull($p['layover_summary']);
        $this->assertFalse($p['connection_details_unavailable']);
        $this->assertSame('Total duration: 3h 00m', $p['total_journey_duration_display']);
        $this->assertNull($p['segments_display'][0]['layover_after_display'] ?? null);
        $this->assertStringStartsWith('Flight time:', (string) ($p['segments_display'][0]['flight_time_display'] ?? ''));
    }

    public function test_one_stop_shows_single_layover_at_connection_not_final_destination(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '2026-06-10T02:00:00',
            'arrival_at' => '2026-06-10T14:00:00',
            'duration_minutes' => 720,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'IST',
                    'departure_at' => '2026-06-10T02:00:00',
                    'arrival_at' => '2026-06-10T06:00:00',
                    'duration_minutes' => 240,
                ],
                [
                    'origin' => 'IST',
                    'destination' => 'DOH',
                    'departure_at' => '2026-06-10T10:00:00',
                    'arrival_at' => '2026-06-10T14:00:00',
                    'duration_minutes' => 240,
                ],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DOH'], []);

        $this->assertSame(['4h layover · IST'], $p['layover_summary']);
        $this->assertFalse($p['connection_details_unavailable']);

        $lay0 = (string) ($p['segments_display'][0]['layover_after_display'] ?? '');
        $this->assertStringStartsWith('Layover:', $lay0);
        $this->assertStringContainsString(' in IST', $lay0);
        $this->assertStringContainsString('4h 00m', $lay0);
        $this->assertStringNotContainsString('DOH', $lay0);

        $this->assertNull($p['segments_display'][1]['layover_after_display'] ?? null);

        $blob = json_encode($p, JSON_THROW_ON_ERROR);
        $this->assertDoesNotMatchRegularExpression('/\d+\.\d+/', $blob);
    }

    public function test_broken_segment_chain_hides_layovers_and_flags_connection_unavailable(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '2026-06-10T02:00:00',
            'arrival_at' => '2026-06-10T18:00:00',
            'duration_minutes' => 600,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'IST',
                    'departure_at' => '2026-06-10T02:00:00',
                    'arrival_at' => '2026-06-10T06:00:00',
                    'duration_minutes' => 240,
                ],
                [
                    'origin' => 'JED',
                    'destination' => 'DOH',
                    'departure_at' => '2026-06-10T14:00:00',
                    'arrival_at' => '2026-06-10T18:00:00',
                    'duration_minutes' => 240,
                ],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DOH'], []);

        $this->assertTrue($p['connection_details_unavailable']);
        $this->assertNull($p['layover_summary']);
        $this->assertNull($p['segments_display'][0]['layover_after_display'] ?? null);
    }

    public function test_two_stop_layover_summary_lists_each_connection(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T02:00:00',
            'arrival_at' => '2026-06-11T08:00:00',
            'duration_minutes' => 1800,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'departure_at' => '2026-06-10T02:00:00',
                    'arrival_at' => '2026-06-10T06:00:00',
                    'duration_minutes' => 240,
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'IST',
                    'departure_at' => '2026-06-10T08:10:00',
                    'arrival_at' => '2026-06-10T12:00:00',
                    'duration_minutes' => 230,
                ],
                [
                    'origin' => 'IST',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-10T17:25:00',
                    'arrival_at' => '2026-06-11T08:00:00',
                    'duration_minutes' => 300,
                ],
            ],
        ];

        $cityMap = ['DOH' => 'Doha', 'IST' => 'Istanbul'];
        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], $cityMap);

        $this->assertIsArray($p['layover_summary']);
        $this->assertCount(2, $p['layover_summary']);
        $this->assertSame('2h 10m layover · Doha (DOH)', $p['layover_summary'][0]);
        $this->assertSame('5h 25m layover · Istanbul (IST)', $p['layover_summary'][1]);
    }

    public function test_layover_tooltip_duration_formats_compactly(): void
    {
        $this->assertSame('45m', FlightOfferDisplayPresenter::formatLayoverTooltipDuration(45));
        $this->assertSame('3h', FlightOfferDisplayPresenter::formatLayoverTooltipDuration(180));
        $this->assertSame('3h 55m', FlightOfferDisplayPresenter::formatLayoverTooltipDuration(235));
        $this->assertSame('', FlightOfferDisplayPresenter::formatLayoverTooltipDuration(0));
    }

    public function test_layover_tooltip_airport_label_priority(): void
    {
        $this->assertSame('Jeddah (JED)', FlightOfferDisplayPresenter::formatLayoverTooltipAirport('Jeddah', 'jed'));
        $this->assertSame('DOH', FlightOfferDisplayPresenter::formatLayoverTooltipAirport('', 'DOH'));
        $this->assertSame('Layover airport unavailable', FlightOfferDisplayPresenter::formatLayoverTooltipAirport('', ''));
    }

    public function test_segment_flight_time_is_separate_from_layover_label(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '2026-06-10T02:00:00',
            'arrival_at' => '2026-06-10T14:00:00',
            'duration_minutes' => 720,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'IST',
                    'departure_at' => '2026-06-10T02:00:00',
                    'arrival_at' => '2026-06-10T06:00:00',
                    'duration_minutes' => 240,
                ],
                [
                    'origin' => 'IST',
                    'destination' => 'DOH',
                    'departure_at' => '2026-06-10T10:00:00',
                    'arrival_at' => '2026-06-10T14:00:00',
                    'duration_minutes' => 240,
                ],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DOH'], []);

        $ft = (string) ($p['segments_display'][0]['flight_time_display'] ?? '');
        $lay = (string) ($p['segments_display'][0]['layover_after_display'] ?? '');
        $this->assertStringContainsString('Flight time:', $ft);
        $this->assertStringContainsString('4h 00m', $ft);
        $this->assertStringStartsWith('Layover:', $lay);
        $this->assertStringNotContainsString('Flight time:', $lay);
    }

    /**
     * Phase S28 case A: PK 303 / EK 603 — spurious +1 day on first-leg arrival must move to KHI layover.
     */
    public function test_pk_303_ek_603_long_layover_chronology_repair_breakdown_matches_timeline(): void
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
        $repaired = SabreSegmentChronologyRepair::repair($segments, '2026-05-30', false)['segments'];
        $offer = [
            'supplier_provider' => 'sabre',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-05-30T05:00:00',
            'arrival_at' => '2026-06-01T07:10:00',
            'duration_minutes' => 0,
            'stops' => 1,
            'segments' => array_map(fn (FlightSegmentData $s) => $s->toArray(), $repaired),
        ];
        $offer['duration_minutes'] = FlightOfferDisplayPresenter::journeyTimelineMinutesFromOffer($offer);

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertSame(3010, $p['total_duration_minutes']);
        $this->assertSame(105, $p['segments_display'][0]['segment_duration_minutes']);
        $this->assertSame(2775, $p['segments_display'][0]['layover_duration_minutes_after']);
        $this->assertSame(130, $p['segments_display'][1]['segment_duration_minutes']);
        $this->assertNull($p['segments_display'][1]['layover_duration_minutes_after']);

        $this->assertSame('2d 2h 10m', $p['itinerary_duration_display']);
        $this->assertSame('Total duration: 2d 2h 10m', $p['total_journey_duration_display']);
        $this->assertSame('1 stop', $p['stops_display']);
        $this->assertSame(1, $p['stops_count']);
        $this->assertStringContainsString('1h 45m', (string) ($p['segments_display'][0]['flight_time_display'] ?? ''));
        $lay = (string) ($p['segments_display'][0]['layover_after_display'] ?? '');
        $this->assertStringContainsString('1d 22h 15m', $lay);
        $this->assertStringContainsString(' in KHI', $lay);
        $this->assertStringContainsString('2h 10m', (string) ($p['segments_display'][1]['flight_time_display'] ?? ''));
    }

    public function test_selected_itinerary_timeline_invalid_when_duration_mismatch_for_sabre(): void
    {
        $offer = [
            'supplier_provider' => 'sabre',
            'duration_minutes' => 845,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => '2026-05-30T05:00:00',
                    'arrival_at' => '2026-05-30T06:45:00',
                    'duration_minutes' => 105,
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-01T05:00:00',
                    'arrival_at' => '2026-06-01T07:10:00',
                    'duration_minutes' => 130,
                ],
            ],
        ];
        $this->assertTrue(FlightOfferDisplayPresenter::selectedItineraryTimelineInvalid($offer));
        $fixed = array_merge($offer, ['duration_minutes' => FlightOfferDisplayPresenter::journeyTimelineMinutesFromOffer($offer)]);
        $this->assertFalse(FlightOfferDisplayPresenter::selectedItineraryTimelineInvalid($fixed));
    }

    /**
     * Phase S28 case B: PK 305 / EK 2109 — next-day second leg; layover stays within one calendar day span.
     */
    public function test_pk_305_ek_2109_next_day_connection_breakdown(): void
    {
        $offer = [
            'supplier_provider' => 'sabre',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-05-30T05:00:00',
            'arrival_at' => '2026-05-31T07:25:00',
            'duration_minutes' => 1585,
            'stops' => 1,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => '2026-05-30T05:00:00',
                    'arrival_at' => '2026-05-30T06:45:00',
                    'duration_minutes' => 105,
                    'flight_number' => '305',
                    'airline_code' => 'PK',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => '2026-05-31T05:00:00',
                    'arrival_at' => '2026-05-31T07:25:00',
                    'duration_minutes' => 145,
                    'flight_number' => '2109',
                    'airline_code' => 'EK',
                ],
            ],
        ];
        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);
        $this->assertSame(1585, $p['total_duration_minutes']);
        $this->assertSame(105, $p['segments_display'][0]['segment_duration_minutes']);
        $this->assertSame(1335, $p['segments_display'][0]['layover_duration_minutes_after']);
        $this->assertSame(145, $p['segments_display'][1]['segment_duration_minutes']);
        $this->assertSame('1d 2h 25m', $p['itinerary_duration_display']);
        $this->assertSame('1 stop', $p['stops_display']);
        $this->assertStringContainsString('1h 45m', (string) ($p['segments_display'][0]['flight_time_display'] ?? ''));
        $lay = (string) ($p['segments_display'][0]['layover_after_display'] ?? '');
        $this->assertStringContainsString('22h 15m', $lay);
        $this->assertStringNotContainsString('1d 22h', $lay);
        $this->assertStringContainsString('2h 25m', (string) ($p['segments_display'][1]['flight_time_display'] ?? ''));
    }

    public function test_j2_style_layover_crosses_midnight_total_one_day(): void
    {
        $offer = [
            'supplier_provider' => 'sabre',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-05-30T05:00:00',
            'arrival_at' => '2026-05-31T05:50:00',
            'duration_minutes' => 1490,
            'stops' => 1,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'GYD',
                    'departure_at' => '2026-05-30T05:00:00',
                    'arrival_at' => '2026-05-30T10:15:00',
                    'duration_minutes' => 315,
                ],
                [
                    'origin' => 'GYD',
                    'destination' => 'DXB',
                    'departure_at' => '2026-05-31T03:00:00',
                    'arrival_at' => '2026-05-31T05:50:00',
                    'duration_minutes' => 170,
                ],
            ],
        ];
        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);
        $this->assertSame('1d 0h 50m', $p['itinerary_duration_display']);
        $this->assertStringContainsString('5h 15m', (string) ($p['segments_display'][0]['flight_time_display'] ?? ''));
        $lay = (string) ($p['segments_display'][0]['layover_after_display'] ?? '');
        $this->assertStringContainsString('16h 45m', $lay);
        $this->assertStringContainsString(' in GYD', $lay);
        $this->assertStringContainsString('2h 50m', (string) ($p['segments_display'][1]['flight_time_display'] ?? ''));
    }

    public function test_sv_layover_crosses_midnight_total_one_day(): void
    {
        $offer = [
            'supplier_provider' => 'sabre',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-05-30T05:00:00',
            'arrival_at' => '2026-05-31T05:50:00',
            'duration_minutes' => 1490,
            'stops' => 1,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'departure_at' => '2026-05-30T05:00:00',
                    'arrival_at' => '2026-05-30T10:15:00',
                    'duration_minutes' => 315,
                ],
                [
                    'origin' => 'JED',
                    'destination' => 'DXB',
                    'departure_at' => '2026-05-31T03:00:00',
                    'arrival_at' => '2026-05-31T05:50:00',
                    'duration_minutes' => 170,
                ],
            ],
        ];
        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);
        $this->assertSame('1d 0h 50m', $p['itinerary_duration_display']);
        $this->assertStringContainsString('5h 15m', (string) ($p['segments_display'][0]['flight_time_display'] ?? ''));
        $lay = (string) ($p['segments_display'][0]['layover_after_display'] ?? '');
        $this->assertStringContainsString('16h 45m', $lay);
        $this->assertStringContainsString('2h 50m', (string) ($p['segments_display'][1]['flight_time_display'] ?? ''));
    }

    public function test_presenter_mixed_carrier_metadata_surfaces_chain_and_validating(): void
    {
        $offer = [
            'supplier_provider' => 'sabre',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T05:00:00',
            'arrival_at' => '2026-06-10T12:00:00',
            'duration_minutes' => 420,
            'stops' => 1,
            'mixed_carrier' => true,
            'marketing_carrier_chain' => ['PK', 'EK'],
            'validating_carrier' => 'EK',
            'all_airline_codes' => ['PK', 'EK'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => '2026-06-10T05:00:00',
                    'arrival_at' => '2026-06-10T06:45:00',
                    'duration_minutes' => 105,
                    'airline_code' => 'PK',
                    'flight_number' => '303',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-10T08:00:00',
                    'arrival_at' => '2026-06-10T12:00:00',
                    'duration_minutes' => 240,
                    'airline_code' => 'EK',
                    'flight_number' => '603',
                ],
            ],
        ];
        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);
        $this->assertTrue($p['mixed_carrier']);
        $this->assertSame('PK + EK', $p['marketing_carrier_chain_display']);
        $this->assertSame('EK', $p['validating_carrier']);
        $this->assertSame(['PK', 'EK'], $p['all_airline_codes']);
        $this->assertSame('PK', $p['primary_display_carrier']);
    }

    public function test_presenter_single_marketing_route_omits_mixed_labels(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'airline_code' => 'SV',
            'departure_at' => '2026-06-10T08:00:00',
            'arrival_at' => '2026-06-10T11:00:00',
            'duration_minutes' => 180,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-10T08:00:00',
                    'arrival_at' => '2026-06-10T11:00:00',
                    'duration_minutes' => 180,
                    'airline_code' => 'SV',
                ],
            ],
        ];
        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);
        $this->assertFalse($p['mixed_carrier']);
        $this->assertNull($p['marketing_carrier_chain_display']);
        $this->assertSame([], $p['all_airline_codes']);
        $this->assertSame('SV', $p['primary_display_carrier']);
    }

    public function test_build_presentation_does_not_embed_price_fields(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T08:00:00',
            'arrival_at' => '2026-06-10T11:00:00',
            'duration_minutes' => 180,
            'final_customer_price' => 281661,
            'segments' => [],
        ];
        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);
        $this->assertArrayNotHasKey('final_customer_price', $p);
        $this->assertArrayNotHasKey('displayed_price', $p);
    }

    public function test_non_stop_layovers_display_is_empty(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T08:00:00',
            'arrival_at' => '2026-06-10T11:00:00',
            'duration_minutes' => 180,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-10T08:00:00',
                    'arrival_at' => '2026-06-10T11:00:00',
                    'duration_minutes' => 180,
                    'airline_code' => 'PK',
                    'airline_name' => 'Pakistan International Airlines',
                    'flight_number' => '303',
                ],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertSame([], $p['layovers_display']);
        $this->assertSame(1, $p['segments_display'][0]['segment_number']);
        $this->assertSame('Pakistan International Airlines', $p['segments_display'][0]['airline_name']);
    }

    public function test_one_stop_layovers_display_waiting_duration_label(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '2026-06-10T02:00:00',
            'arrival_at' => '2026-06-10T14:00:00',
            'duration_minutes' => 720,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'IST',
                    'departure_at' => '2026-06-10T02:00:00',
                    'arrival_at' => '2026-06-10T06:00:00',
                    'duration_minutes' => 240,
                    'airline_code' => 'TK',
                    'airline_name' => 'Turkish Airlines',
                    'flight_number' => '715',
                ],
                [
                    'origin' => 'IST',
                    'destination' => 'DOH',
                    'departure_at' => '2026-06-10T10:00:00',
                    'arrival_at' => '2026-06-10T14:00:00',
                    'duration_minutes' => 240,
                    'airline_code' => 'TK',
                    'flight_number' => '820',
                ],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DOH'], []);

        $this->assertCount(1, $p['layovers_display']);
        $this->assertStringStartsWith('Waiting duration ·', $p['layovers_display'][0]['label']);
        $this->assertStringContainsString('4h 00m', $p['layovers_display'][0]['label']);
        $this->assertStringContainsString(' in IST', $p['layovers_display'][0]['label']);
        $this->assertCount(2, $p['segments_display']);
    }

    public function test_two_stop_layovers_display_lists_both_connections(): void
    {
        $cityMap = ['DOH' => 'Doha', 'IST' => 'Istanbul'];
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T02:00:00',
            'arrival_at' => '2026-06-11T08:00:00',
            'duration_minutes' => 1800,
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '2026-06-10T02:00:00', 'arrival_at' => '2026-06-10T06:00:00', 'duration_minutes' => 240],
                ['origin' => 'DOH', 'destination' => 'IST', 'departure_at' => '2026-06-10T08:10:00', 'arrival_at' => '2026-06-10T12:00:00', 'duration_minutes' => 230],
                ['origin' => 'IST', 'destination' => 'DXB', 'departure_at' => '2026-06-10T17:25:00', 'arrival_at' => '2026-06-11T08:00:00', 'duration_minutes' => 300],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], $cityMap);

        $this->assertCount(2, $p['layovers_display']);
        $this->assertStringContainsString('Doha (DOH)', $p['layovers_display'][0]['label']);
        $this->assertStringContainsString('Istanbul (IST)', $p['layovers_display'][1]['label']);
    }

    public function test_fare_summary_display_groups_customer_safe_fields(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'flight_number' => '603',
            'cabin' => 'economy',
            'fare_family' => 'Economy Flex',
            'refundable' => true,
            'baggage' => ['summary' => '30kg checked'],
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180, 'airline_code' => 'EK', 'airline_name' => 'Emirates'],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], ['EK' => 'Emirates']);

        $summary = $p['fare_summary_display'];
        $this->assertStringContainsString('Emirates', (string) $summary['airline']);
        $this->assertSame('603', $summary['flight_numbers']);
        $this->assertSame('economy', $summary['cabin']);
        $this->assertSame('Refundable', $summary['refund_status']);
        $this->assertSame('Economy Flex', $summary['fare_family']);
        $this->assertSame(['30 kg checked'], $summary['baggage_lines']);
        $this->assertSame('Sabre', $summary['provider_label']);
    }

    public function test_fare_family_options_display_single_synthetic_default_when_universal_choice_enabled(): void
    {
        Config::set('ota.universal_fare_choice_enabled', true);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'fare_family' => 'Economy',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertTrue($p['single_direct_fare_on_card'] ?? false);
        $this->assertCount(1, $p['fare_family_options_display']);
        $this->assertTrue($p['fare_family_options_display'][0]['is_synthetic_default'] ?? false);
        $this->assertTrue($p['fare_family_options_display'][0]['selectable'] ?? false);
    }

    public function test_fare_family_options_display_empty_when_universal_choice_disabled(): void
    {
        Config::set('ota.universal_fare_choice_enabled', false);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'fare_family' => 'Economy',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertSame([], $p['fare_family_options_display']);
    }

    public function test_fare_family_options_display_maps_structured_branded_fares(): void
    {
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'branded_fares' => [
                [
                    'name' => 'Value',
                    'price_total' => 45000,
                    'currency' => 'PKR',
                    'baggage_summary' => '20kg',
                    'selectable' => false,
                ],
                [
                    'name' => 'Flexi',
                    'price_total' => 52000,
                    'currency' => 'PKR',
                    'refund_rule' => 'Refundable with fee',
                    'selectable' => true,
                    'is_cheapest' => false,
                ],
            ],
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertCount(2, $p['fare_family_options_display']);
        $this->assertSame('Value', $p['fare_family_options_display'][0]['name']);
        $this->assertFalse($p['fare_family_options_display'][0]['selectable']);
        $this->assertFalse($p['fare_family_options_display'][1]['selectable']);
        $this->assertTrue($p['has_branded_fares']);
        $this->assertCount(2, $p['branded_fares_display_options']);
        $this->assertSame('Fare family preview', $p['branded_fares_display_label']);
        $this->assertArrayNotHasKey('raw_payload', $p);
    }

    public function test_branded_fares_display_options_when_display_gate_enabled(): void
    {
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'branded_fares' => [
                ['name' => 'Value', 'brand_code' => 'VAL', 'price_total' => 45000, 'currency' => 'PKR', 'cabin' => 'Y', 'booking_classes_by_segment' => ['V'], 'fare_basis_codes' => ['VLWOPPK1'], 'baggage_summary' => '20kg', 'selectable' => true],
                ['name' => 'Flexi', 'brand_code' => 'FLX', 'price_total' => 52000, 'currency' => 'PKR', 'selectable' => true],
                ['name' => 'Premium', 'brand_code' => 'PRM', 'price_total' => 61000, 'currency' => 'PKR', 'selectable' => true],
                ['name' => 'Business', 'brand_code' => 'BUS', 'price_total' => 98000, 'currency' => 'PKR', 'selectable' => true],
            ],
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertTrue($p['branded_fares_display_enabled']);
        $this->assertTrue($p['has_branded_fares']);
        $this->assertCount(3, $p['branded_fares_display_options']);
        $this->assertSame(1, $p['branded_fares_more_count']);
        $this->assertSame('Fare family preview', $p['branded_fares_display_label']);
        $this->assertSame('Value', $p['branded_fares_display_options'][0]['name']);
        $this->assertSame('VAL', $p['branded_fares_display_options'][0]['brand_code']);
        $this->assertSame('PKR 45,000', $p['branded_fares_display_options'][0]['price_display']);
        $this->assertSame('V', $p['branded_fares_display_options'][0]['booking_class']);
        $this->assertSame('VLWOPPK1', $p['branded_fares_display_options'][0]['fare_basis']);
        $this->assertFalse($p['branded_fares_display_options'][0]['selectable']);
        $this->assertTrue($p['branded_fares_display_options'][0]['display_only']);
        $this->assertFalse($p['branded_fares_selection_active']);
        foreach ($p['fare_family_options_display'] as $option) {
            $this->assertFalse($option['selectable']);
        }
    }

    public function test_branded_fares_selection_active_when_both_gates_enabled(): void
    {
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'branded_fares' => [
                ['name' => 'Value', 'brand_code' => 'VAL', 'price_total' => 45000, 'currency' => 'PKR', 'baggage_summary' => '20kg'],
                ['name' => 'Flexi', 'brand_code' => 'FLX', 'price_total' => 52000, 'currency' => 'PKR'],
            ],
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertTrue($p['branded_fares_selection_active']);
        $this->assertSame('Choose fare family', $p['branded_fares_display_label']);
        $this->assertTrue($p['fare_family_options_display'][0]['selectable']);
        $this->assertFalse($p['fare_family_options_display'][0]['display_only']);
    }

    public function test_branded_fares_usd_options_show_approx_pkr_from_offer_ratio(): void
    {
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'final_customer_price' => 150000,
            'supplier_total_source' => 500,
            'pricing_currency' => 'PKR',
            'branded_fares' => [
                ['name' => 'Value', 'brand_code' => 'VAL', 'price_total' => 400, 'currency' => 'USD', 'pricing_information_index' => 0],
                ['name' => 'Flexi', 'brand_code' => 'FLX', 'price_total' => 500, 'currency' => 'USD', 'pricing_information_index' => 1],
            ],
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertTrue($p['branded_fares_display_options'][0]['price_is_approximate']);
        $this->assertStringStartsWith('Approx. PKR', (string) $p['branded_fares_display_options'][0]['price_display']);
        $this->assertSame(120000, $p['branded_fares_display_options'][0]['displayed_price']);
    }

    public function test_sanitize_selected_fare_family_intent_strips_supplier_refs(): void
    {
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $offer = [
            'final_customer_price' => 100000,
            'supplier_total_source' => 400,
            'pricing_currency' => 'PKR',
        ];
        $resolved = [
            'option_key' => 'val-pi0',
            'name' => 'Value',
            'brand_code' => 'VAL',
            'price_total' => 400,
            'currency' => 'USD',
            'booking_class' => 'V',
            'fare_basis' => 'VLWOPPK1',
            'baggage_summary' => '20kg',
            'pricing_information_index' => 0,
            'pricing_information_ref' => 'secret-ref',
            'offer_ref' => 'secret-offer',
        ];

        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($resolved, $offer);

        $this->assertSame('val-pi0', $intent['option_key']);
        $this->assertSame('Value', $intent['name']);
        $this->assertSame('Value', $intent['brand_name']);
        $this->assertSame('20kg', $intent['baggage']);
        $this->assertArrayNotHasKey('pricing_information_ref', $intent);
        $this->assertArrayNotHasKey('offer_ref', $intent);
        $this->assertArrayNotHasKey('pricing_information_index', $intent);
        $this->assertTrue($intent['price_is_approximate']);
        $this->assertTrue($intent['is_price_approximate']);
        $this->assertSame(FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE, $intent['validation_note']);
    }

    public function test_find_fare_family_option_by_key_resolves_brand_pi_keys(): void
    {
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'final_customer_price' => 150000,
            'supplier_total_source' => 500,
            'branded_fares' => [
                [
                    'name' => 'Smart',
                    'brand_code' => 'SMART',
                    'price_total' => 420,
                    'currency' => 'USD',
                    'pricing_information_index' => 0,
                    'baggage_summary' => '23kg',
                    'booking_classes_by_segment' => ['S'],
                    'fare_basis_codes' => ['SLWOPPK1'],
                ],
                [
                    'name' => 'Freedom',
                    'brand_code' => 'FRD',
                    'price_total' => 550,
                    'currency' => 'USD',
                    'pricing_information_index' => 1,
                ],
            ],
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $smart = FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, 'smart-pi0');
        $this->assertNotNull($smart);
        $this->assertSame('Smart', $smart['name']);
        $this->assertSame('SMART', $smart['brand_code']);

        $freedom = FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, 'frd-pi1');
        $this->assertNotNull($freedom);
        $this->assertSame('Freedom', $freedom['name']);

        $this->assertNull(FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, 'unknown-key'));
    }

    public function test_build_selected_fare_family_checkout_view_normalizes_intent_fields(): void
    {
        $view = FlightOfferDisplayPresenter::buildSelectedFareFamilyCheckoutView([
            'name' => 'Freedom',
            'brand_code' => 'FRD',
            'price_display' => 'Approx. PKR 165,000',
            'price_is_approximate' => true,
            'displayed_price' => 165000,
            'displayed_currency' => 'PKR',
            'baggage_summary' => '30kg',
            'cabin' => 'Economy',
            'booking_class' => 'F',
            'fare_basis' => 'FLWOPPK1',
            'pricing_information_ref' => 'secret',
        ]);

        $this->assertIsArray($view);
        $this->assertSame('Freedom', $view['name']);
        $this->assertSame('FRD', $view['brand_code']);
        $this->assertTrue($view['has_checkout_estimate']);
        $this->assertArrayNotHasKey('pricing_information_ref', $view);
    }

    public function test_build_checkout_selected_fare_estimate_presentation_uses_server_intent_only(): void
    {
        $estimate = FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation([
            'name' => 'Smart',
            'brand_code' => 'SMART',
            'displayed_price' => 83865,
            'displayed_currency' => 'PKR',
            'price_display' => 'Approx. PKR 83,865',
            'price_is_approximate' => true,
            'validation_note' => FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE,
        ]);

        $this->assertIsArray($estimate);
        $this->assertTrue($estimate['has_checkout_estimate']);
        $this->assertSame('Estimated selected fare', $estimate['label']);
        $this->assertSame(83865, $estimate['displayed_price']);
        $this->assertTrue($estimate['price_is_approximate']);
        $this->assertStringContainsString('83,865', $estimate['price_display']);

        $this->assertNull(FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation(null));
        $this->assertNull(FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation([
            'name' => 'Smart',
            'displayed_price' => 0,
        ]));
    }

    public function test_build_selected_fare_family_email_section_normalizes_branded_intent(): void
    {
        $section = FlightOfferDisplayPresenter::buildSelectedFareFamilyEmailSection([
            'name' => 'FREEDOM',
            'brand_code' => 'FL',
            'displayed_price' => 90062,
            'displayed_currency' => 'PKR',
            'price_display' => 'Approx. PKR 90,062',
            'price_is_approximate' => true,
            'baggage_summary' => '30 KG',
            'cabin' => 'economy',
            'booking_class' => 'V',
            'fare_basis' => 'VOWFL/V',
        ]);

        $this->assertIsArray($section);
        $this->assertSame('FREEDOM (FL)', $section['fare_family_label']);
        $this->assertSame('Estimated selected fare', $section['estimated_fare_label']);
        $this->assertSame('Approx. PKR 90,062', $section['estimated_fare_display']);
        $this->assertSame('30 KG', $section['baggage']);
        $this->assertSame('economy', $section['cabin']);
        $this->assertSame('V', $section['booking_class']);
        $this->assertSame('VOWFL/V', $section['fare_basis']);
        $this->assertSame(FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE, $section['validation_note']);
        $this->assertSame(FlightOfferDisplayPresenter::SELECTED_FARE_PAYABLE_DISCLAIMER, $section['payable_disclaimer']);

        $this->assertNull(FlightOfferDisplayPresenter::buildSelectedFareFamilyEmailSection(null));
        $this->assertNull(FlightOfferDisplayPresenter::buildSelectedFareFamilyEmailSection(['brand_code' => 'FL']));
    }

    public function test_preserve_sticky_selected_fare_family_display_keeps_first_price(): void
    {
        $stored = [
            'option_key' => 'frd-pi1',
            'name' => 'Freedom',
            'price_display' => 'Approx. PKR 165,000',
            'displayed_price' => 165000,
            'displayed_currency' => 'PKR',
            'price_is_approximate' => true,
            'is_price_approximate' => true,
        ];
        $fresh = [
            'option_key' => 'frd-pi1',
            'name' => 'Freedom',
            'price_display' => 'Approx. PKR 128,333',
            'displayed_price' => 128333,
            'displayed_currency' => 'PKR',
            'price_is_approximate' => true,
            'is_price_approximate' => true,
            'baggage_summary' => '30kg',
        ];

        $result = FlightOfferDisplayPresenter::preserveStickySelectedFareFamilyDisplay($stored, $fresh);

        $this->assertTrue($result['estimate_drift_detected']);
        $this->assertSame('Approx. PKR 165,000', $result['intent']['price_display']);
        $this->assertSame(165000, $result['intent']['displayed_price']);
        $this->assertSame('30kg', $result['intent']['baggage_summary']);
    }

    public function test_build_checkout_fare_rules_sidebar_uses_selected_branded_baggage(): void
    {
        $offer = ['baggage' => '0 KG', 'cabin' => 'economy'];
        $intent = [
            'name' => 'Freedom',
            'baggage_summary' => '30kg',
            'cabin' => 'Economy',
        ];

        $rules = FlightOfferDisplayPresenter::buildCheckoutFareRulesSidebar($offer, $intent);

        $this->assertTrue($rules['uses_selected_fare_family']);
        $this->assertSame('30kg', $rules['baggage_display']);
        $this->assertSame('Economy', $rules['cabin_display']);
    }

    public function test_build_checkout_fare_rules_sidebar_falls_back_to_base_offer(): void
    {
        $offer = ['baggage' => '20kg', 'cabin' => 'economy'];

        $rules = FlightOfferDisplayPresenter::buildCheckoutFareRulesSidebar($offer, null);

        $this->assertFalse($rules['uses_selected_fare_family']);
        $this->assertSame('20kg', $rules['baggage_display']);
        $this->assertSame('Economy', $rules['cabin_display']);
    }

    public function test_branded_fares_hidden_from_api_when_display_gate_disabled(): void
    {
        Config::set('suppliers.sabre.branded_fares_display_enabled', false);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'branded_fares' => [
                ['name' => 'Value', 'price_total' => 45000, 'currency' => 'PKR', 'selectable' => false],
                ['name' => 'Flexi', 'price_total' => 52000, 'currency' => 'PKR', 'selectable' => false],
            ],
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $this->assertTrue($p['has_branded_fares']);
        $this->assertSame([], $p['branded_fares_display_options']);
        $this->assertSame([], $p['fare_family_options_display']);
    }

    public function test_format_criteria_route_label_round_trip_and_multi_city(): void
    {
        $this->assertSame('LHE → DXB', FlightOfferDisplayPresenter::formatCriteriaRouteLabel([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'trip_type' => 'one_way',
        ]));
        $this->assertSame('LHE ⇄ DXB', FlightOfferDisplayPresenter::formatCriteriaRouteLabel([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'trip_type' => 'round_trip',
        ]));
        $this->assertSame('LHE → DXB · DXB → JED', FlightOfferDisplayPresenter::formatCriteriaRouteLabel([
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB'],
                ['origin' => 'DXB', 'destination' => 'JED'],
            ],
        ]));
    }

    public function test_merge_stored_search_criteria_preserves_multi_city_segments(): void
    {
        $stored = [
            'trip_type' => 'multi_city',
            'origin' => 'LHE',
            'destination' => 'JED',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-07-01'],
                ['origin' => 'DXB', 'destination' => 'JED', 'departure_date' => '2026-07-05'],
            ],
        ];
        $fromForm = [
            'origin' => 'LHE',
            'destination' => 'JED',
            'trip_type' => 'multi_city',
            'depart_date' => '2026-07-01',
        ];

        $merged = FlightOfferDisplayPresenter::mergeStoredSearchCriteria($fromForm, $stored);
        $this->assertSame('multi_city', $merged['trip_type']);
        $this->assertCount(2, $merged['segments']);
    }

    public function test_round_trip_splits_outbound_and_return_journeys(): void
    {
        $segments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-06-10T02:00:00', 'arrival_at' => '2026-06-10T03:30:00', 'duration_minutes' => 90],
            ['origin' => 'KHI', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T10:15:00', 'duration_minutes' => 135],
            ['origin' => 'DXB', 'destination' => 'DOH', 'departure_at' => '2026-06-17T14:00:00', 'arrival_at' => '2026-06-17T14:45:00', 'duration_minutes' => 45],
            ['origin' => 'DOH', 'destination' => 'LHE', 'departure_at' => '2026-06-17T18:00:00', 'arrival_at' => '2026-06-18T02:00:00', 'duration_minutes' => 480],
        ];

        $split = FlightOfferDisplayPresenter::splitRoundTripSegments($segments, 'LHE', 'DXB');
        $this->assertNotNull($split);
        $this->assertCount(2, $split['outbound']);
        $this->assertCount(2, $split['return']);

        $offer = [
            'origin' => 'LHE',
            'destination' => 'LHE',
            'departure_at' => '2026-06-10T02:00:00',
            'arrival_at' => '2026-06-18T02:00:00',
            'duration_minutes' => 5000,
            'segments' => $segments,
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation(
            $offer,
            ['origin' => 'LHE', 'destination' => 'DXB', 'trip_type' => 'round_trip'],
            []
        );

        $this->assertFalse($p['journey_grouping_unavailable']);
        $this->assertCount(2, $p['journeys_display']);
        $this->assertSame('outbound', $p['journeys_display'][0]['type']);
        $this->assertSame('Outbound', $p['journeys_display'][0]['label']);
        $this->assertSame('return', $p['journeys_display'][1]['type']);
        $this->assertSame('Return', $p['journeys_display'][1]['label']);
        $this->assertSame('LHE', $p['journeys_display'][0]['origin']);
        $this->assertSame('DXB', $p['journeys_display'][0]['destination']);
        $this->assertSame('DXB', $p['journeys_display'][1]['origin']);
        $this->assertSame('LHE', $p['journeys_display'][1]['destination']);
        $this->assertCount(2, $p['journeys_display'][0]['segments_display']);
        $this->assertCount(2, $p['journeys_display'][1]['segments_display']);
    }

    public function test_one_way_unchanged_without_journeys_display(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T08:00:00',
            'arrival_at' => '2026-06-10T11:00:00',
            'duration_minutes' => 180,
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB', 'trip_type' => 'one_way'], []);

        $this->assertSame([], $p['journeys_display']);
        $this->assertFalse($p['journey_grouping_unavailable']);
        $this->assertSame('LHE', $p['departure_airport_code']);
        $this->assertSame('DXB', $p['arrival_airport_code']);
    }

    public function test_round_trip_ambiguous_split_sets_grouping_unavailable(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'LHE',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation(
            $offer,
            ['origin' => 'LHE', 'destination' => 'DXB', 'trip_type' => 'round_trip'],
            []
        );

        $this->assertTrue($p['journey_grouping_unavailable']);
        $this->assertSame([], $p['journeys_display']);
    }

    public function test_format_multi_city_leg_label_includes_route(): void
    {
        $this->assertSame(
            'Leg 1: LHE → DXB',
            FlightOfferDisplayPresenter::formatMultiCityLegLabel(1, 'lhe', 'dxb')
        );
        $this->assertSame(
            'Leg 3: DXB → JED',
            FlightOfferDisplayPresenter::formatMultiCityLegLabel(3, 'DXB', 'JED')
        );
    }

    public function test_multi_city_splits_into_leg_one_and_leg_two(): void
    {
        $segments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-06-10T02:00:00', 'arrival_at' => '2026-06-10T03:30:00', 'duration_minutes' => 90],
            ['origin' => 'KHI', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T10:15:00', 'duration_minutes' => 135],
            ['origin' => 'DXB', 'destination' => 'JED', 'departure_at' => '2026-06-12T09:00:00', 'arrival_at' => '2026-06-12T11:00:00', 'duration_minutes' => 120],
        ];

        $split = FlightOfferDisplayPresenter::splitMultiCitySegments($segments, [
            ['origin' => 'LHE', 'destination' => 'DXB'],
            ['origin' => 'DXB', 'destination' => 'JED'],
        ]);
        $this->assertNotNull($split);
        $this->assertCount(2, $split);
        $this->assertCount(2, $split[0]);
        $this->assertCount(1, $split[1]);

        $criteria = [
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-06-10'],
                ['origin' => 'DXB', 'destination' => 'JED', 'departure_date' => '2026-06-12'],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation(
            ['origin' => 'LHE', 'destination' => 'JED', 'segments' => $segments],
            $criteria,
            [],
        );

        $this->assertFalse($p['journey_grouping_unavailable']);
        $this->assertCount(2, $p['journeys_display']);
        $this->assertSame('multi_city', $p['journeys_display'][0]['type']);
        $this->assertSame('Leg 1: LHE → DXB', $p['journeys_display'][0]['label']);
        $this->assertSame('LHE', $p['journeys_display'][0]['origin']);
        $this->assertSame('DXB', $p['journeys_display'][0]['destination']);
        $this->assertSame('Leg 2: DXB → JED', $p['journeys_display'][1]['label']);
        $this->assertCount(2, $p['journeys_display'][0]['segments_display']);
        $this->assertCount(1, $p['journeys_display'][1]['segments_display']);
    }

    public function test_multi_city_layovers_stay_within_leg(): void
    {
        $segments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-06-10T02:00:00', 'arrival_at' => '2026-06-10T03:30:00', 'duration_minutes' => 90],
            ['origin' => 'KHI', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T10:15:00', 'duration_minutes' => 135],
            ['origin' => 'DXB', 'destination' => 'JED', 'departure_at' => '2026-06-12T09:00:00', 'arrival_at' => '2026-06-12T11:00:00', 'duration_minutes' => 120],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation(
            ['segments' => $segments],
            [
                'trip_type' => 'multi_city',
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'DXB'],
                    ['origin' => 'DXB', 'destination' => 'JED'],
                ],
            ],
            [],
        );

        $leg1Layovers = $p['journeys_display'][0]['layovers_display'] ?? [];
        $this->assertNotEmpty($leg1Layovers);
        $leg2Layovers = $p['journeys_display'][1]['layovers_display'] ?? [];
        $this->assertSame([], $leg2Layovers);
    }

    public function test_multi_city_ambiguous_split_sets_grouping_unavailable(): void
    {
        $p = FlightOfferDisplayPresenter::buildPresentation(
            [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T11:00:00', 'duration_minutes' => 180],
                ],
            ],
            [
                'trip_type' => 'multi_city',
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'DXB'],
                    ['origin' => 'DXB', 'destination' => 'JED'],
                ],
            ],
            [],
        );

        $this->assertTrue($p['journey_grouping_unavailable']);
        $this->assertSame([], $p['journeys_display']);
    }

    public function test_journey_overview_display_includes_stops_and_duration(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T08:00:00',
            'arrival_at' => '2026-06-11T06:00:00',
            'duration_minutes' => 1320,
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '2026-06-10T08:00:00', 'arrival_at' => '2026-06-10T10:00:00', 'duration_minutes' => 120],
                ['origin' => 'DOH', 'destination' => 'DXB', 'departure_at' => '2026-06-10T14:00:00', 'arrival_at' => '2026-06-11T06:00:00', 'duration_minutes' => 480],
            ],
        ];

        $p = FlightOfferDisplayPresenter::buildPresentation($offer, ['origin' => 'LHE', 'destination' => 'DXB'], []);

        $j = $p['journey_overview_display'];
        $this->assertSame('LHE', $j['origin_code']);
        $this->assertSame('DXB', $j['destination_code']);
        $this->assertSame(1, $j['stops_count']);
        $this->assertSame('1 stop', $j['stops_display']);
        $this->assertSame('22h 00m', $j['total_duration_display']);
    }

    public function test_safe_fare_option_key_for_log_accepts_short_alphanumeric_keys(): void
    {
        $this->assertSame('val-pi0', FlightOfferDisplayPresenter::safeFareOptionKeyForLog('val-pi0'));
        $this->assertNull(FlightOfferDisplayPresenter::safeFareOptionKeyForLog(''));
        $this->assertNull(FlightOfferDisplayPresenter::safeFareOptionKeyForLog(str_repeat('a', 121)));
        $this->assertNull(FlightOfferDisplayPresenter::safeFareOptionKeyForLog('bad key!'));
    }

    public function test_fare_family_option_keys_sample_returns_keys_from_offer_only(): void
    {
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $offer = [
            'branded_fares' => [
                ['name' => 'Value', 'brand_code' => 'VAL', 'price_total' => 400, 'currency' => 'USD', 'pricing_information_index' => 0],
                ['name' => 'Freedom', 'brand_code' => 'FRD', 'price_total' => 550, 'currency' => 'USD', 'pricing_information_index' => 1],
            ],
            'supplier_total_source' => 500,
            'final_customer_price' => 150000,
            'pricing_currency' => 'PKR',
        ];

        $sample = FlightOfferDisplayPresenter::fareFamilyOptionKeysSample($offer);

        $this->assertSame(['val-pi0', 'frd-pi1'], $sample);
    }
}
