<?php

namespace Tests\Unit;

use App\Support\Bookings\BookingItineraryOverviewPresenter;
use Tests\TestCase;

class BookingItineraryOverviewPresenterTest extends TestCase
{
    public function test_returns_null_when_meta_empty(): void
    {
        $this->assertNull(BookingItineraryOverviewPresenter::fromBookingMeta(null));
        $this->assertNull(BookingItineraryOverviewPresenter::fromBookingMeta([]));
    }

    public function test_non_stop_single_segment_labels_and_lines(): void
    {
        $depart = '2026-06-01';
        $meta = [
            'search_criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => $depart,
            ],
            'flight_offer_snapshot' => [
                'supplier_provider' => 'sabre',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_at' => $depart.'T08:00:00Z',
                'arrive_at' => $depart.'T14:00:00Z',
                'stops' => 0,
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => $depart.'T08:00:00Z',
                        'arrival_at' => $depart.'T14:00:00Z',
                        'duration_minutes' => 360,
                        'airline_code' => 'EK',
                        'flight_number' => '501',
                    ],
                ],
            ],
        ];

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($meta);
        $this->assertNotNull($out);
        $this->assertSame(BookingItineraryOverviewPresenter::ITINERARY_SOURCE_SEARCH_SNAPSHOT, $out['itinerary_source']);
        $this->assertSame('Direct', $out['stops_label']);
        $this->assertSame('LHE → DXB', $out['journey_od']);
        $this->assertCount(1, $out['segment_lines']);
        $this->assertStringContainsString('LHE → DXB', $out['segment_lines'][0]);
        $this->assertStringContainsString('EK501', $out['segment_lines'][0]);
        $this->assertMatchesRegularExpression('/\b(AM|PM)\b/', $out['segment_lines'][0]);
    }

    public function test_multi_segment_shows_one_stop_transfer_line_and_ampm(): void
    {
        $meta = [
            'search_criteria' => ['origin' => 'LHE', 'destination' => 'DXB'],
            'flight_offer_snapshot' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-05-30T05:00:00',
                'arrival_at' => '2026-05-30T22:10:00',
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'KHI',
                        'departure_at' => '2026-05-30T05:00:00',
                        'arrival_at' => '2026-05-30T06:45:00',
                        'duration_minutes' => 105,
                        'airline_code' => 'EK',
                        'flight_number' => '600',
                    ],
                    [
                        'origin' => 'KHI',
                        'destination' => 'DXB',
                        'departure_at' => '2026-05-30T15:00:00',
                        'arrival_at' => '2026-05-30T18:10:00',
                        'duration_minutes' => 190,
                        'airline_code' => 'EK',
                        'flight_number' => '601',
                    ],
                ],
            ],
        ];

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($meta);
        $this->assertNotNull($out);
        $this->assertSame('1 stop', $out['stops_label']);
        $this->assertGreaterThanOrEqual(3, count($out['segment_lines']));
        $transferLines = array_values(array_filter(
            $out['segment_lines'],
            static fn (string $line): bool => str_contains($line, 'Transfer:')
        ));
        $this->assertNotEmpty($transferLines);
        foreach ($out['segment_lines'] as $line) {
            if (preg_match('/^\d+\./', $line)) {
                $this->assertMatchesRegularExpression('/\b(AM|PM)\b/', $line);
            }
        }
    }

    public function test_pnr_itinerary_snapshot_uses_pnr_synced_source(): void
    {
        $snap = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-01T08:00:00',
            'arrival_at' => '2026-06-01T14:00:00',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-01T08:00:00',
                    'arrival_at' => '2026-06-01T14:00:00',
                    'duration_minutes' => 360,
                    'airline_code' => 'EK',
                    'flight_number' => '501',
                ],
            ],
        ];
        $meta = [
            'search_criteria' => ['origin' => 'LHE', 'destination' => 'DXB'],
            'flight_offer_snapshot' => [
                'origin' => 'LHE',
                'destination' => 'XXX',
                'segments' => [],
            ],
            'pnr_itinerary_snapshot' => $snap,
        ];

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($meta);
        $this->assertNotNull($out);
        $this->assertSame(BookingItineraryOverviewPresenter::ITINERARY_SOURCE_PNR_SYNCED, $out['itinerary_source']);
        $this->assertSame('PNR/airline itinerary', $out['itinerary_source_label']);
        $this->assertFalse($out['show_snapshot_itinerary_warning']);
        $this->assertStringContainsString('LHE → DXB', $out['segment_lines'][0]);
    }

    public function test_pnr_without_synced_itinerary_uses_snapshot_label_with_final_sync_notice(): void
    {
        $meta = [
            'search_criteria' => ['origin' => 'LHE', 'destination' => 'DXB'],
            'flight_offer_snapshot' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-05-30T17:00:00',
                        'arrival_at' => '2026-05-30T19:45:00',
                        'duration_minutes' => 165,
                        'airline_code' => 'PK',
                        'flight_number' => '301',
                    ],
                ],
            ],
        ];

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($meta, true);
        $this->assertNotNull($out);
        $this->assertSame(BookingItineraryOverviewPresenter::ITINERARY_SOURCE_SEARCH_SNAPSHOT, $out['itinerary_source']);
        $this->assertSame(
            'Search/checkout snapshot — final airline itinerary not yet synced',
            $out['itinerary_source_label']
        );
        $this->assertTrue($out['show_snapshot_itinerary_warning']);
        $this->assertTrue($out['show_fare_snapshot_note']);
    }

    public function test_no_pnr_uses_search_snapshot_label_without_final_sync_suffix(): void
    {
        $meta = [
            'flight_offer_snapshot' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-05-30T17:00:00',
                        'arrival_at' => '2026-05-30T19:45:00',
                        'airline_code' => 'PK',
                        'flight_number' => '301',
                    ],
                ],
            ],
        ];

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($meta, false);
        $this->assertNotNull($out);
        $this->assertSame('Search/checkout snapshot', $out['itinerary_source_label']);
        $this->assertFalse($out['show_snapshot_itinerary_warning']);
        $this->assertFalse($out['show_fare_snapshot_note']);
    }

    public function test_round_trip_journey_od_uses_bidirectional_label(): void
    {
        $meta = [
            'search_criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'trip_type' => 'round_trip',
                'return_date' => '2026-07-10',
            ],
            'flight_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T02:00:00', 'arrival_at' => '2026-06-10T10:15:00', 'airline_code' => 'EK', 'flight_number' => '601'],
                    ['origin' => 'DXB', 'destination' => 'LHE', 'departure_at' => '2026-06-17T14:00:00', 'arrival_at' => '2026-06-18T02:00:00', 'airline_code' => 'EK', 'flight_number' => '602'],
                ],
            ],
        ];

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($meta);
        $this->assertNotNull($out);
        $this->assertSame('LHE ⇄ DXB', $out['journey_od']);
        $this->assertSame('Return', $out['trip_type_label']);
        $this->assertNotEmpty($out['journey_group_lines']);
    }

    public function test_multi_city_journey_od_and_group_labels(): void
    {
        $meta = [
            'search_criteria' => [
                'trip_type' => 'multi_city',
                'origin' => 'LHE',
                'destination' => 'JED',
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-07-01'],
                    ['origin' => 'DXB', 'destination' => 'JED', 'departure_date' => '2026-07-05'],
                ],
            ],
            'flight_offer_snapshot' => [
                'segments' => [
                    ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-07-01T02:00:00', 'arrival_at' => '2026-07-01T10:15:00', 'airline_code' => 'EK', 'flight_number' => '601'],
                    ['origin' => 'DXB', 'destination' => 'JED', 'departure_at' => '2026-07-05T14:00:00', 'arrival_at' => '2026-07-05T18:00:00', 'airline_code' => 'EK', 'flight_number' => '602'],
                ],
            ],
        ];

        $out = BookingItineraryOverviewPresenter::fromBookingMeta($meta);
        $this->assertNotNull($out);
        $this->assertSame('LHE → DXB · DXB → JED', $out['journey_od']);
        $this->assertSame('Multi-city', $out['trip_type_label']);
        $this->assertContains('Leg 1: LHE → DXB', $out['journey_group_lines']);
    }

    public function test_admin_fare_heuristic_flags_micro_base_against_large_supplier_total(): void
    {
        $this->assertTrue(BookingItineraryOverviewPresenter::adminStoredFareLineItemsLookUnreliable(148.0, 0.0, 82115.0, 90000.0));
        $this->assertFalse(BookingItineraryOverviewPresenter::adminStoredFareLineItemsLookUnreliable(45000.0, 5000.0, 82115.0, 90000.0));
    }
}
