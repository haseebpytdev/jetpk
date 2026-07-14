<?php

namespace Tests\Feature;

use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\Bookings\BookingItineraryOverviewPresenter;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCheckoutSnapshotSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_trip_snapshot_meta_preserves_criteria_segments_and_journey_groups(): void
    {
        $segments = [
            ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-10T02:00:00', 'arrival_at' => '2026-06-10T10:15:00', 'duration_minutes' => 135, 'airline_code' => 'EK', 'flight_number' => '601'],
            ['origin' => 'DXB', 'destination' => 'LHE', 'departure_at' => '2026-06-17T14:00:00', 'arrival_at' => '2026-06-18T02:00:00', 'duration_minutes' => 480, 'airline_code' => 'EK', 'flight_number' => '602'],
        ];
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
            'trip_type' => 'round_trip',
            'return_date' => '2026-06-17',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $offer = [
            'id' => 'offer-rt-1',
            'offer_id' => 'offer-rt-1',
            'origin' => 'LHE',
            'destination' => 'LHE',
            'departure_at' => '2026-06-10T02:00:00',
            'arrival_at' => '2026-06-18T02:00:00',
            'segments' => $segments,
        ];

        $store = app(FlightSearchResultStore::class);
        $searchId = $store->store($criteria, [$offer], []);

        $fromForm = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
            'trip_type' => 'round_trip',
            'return_date' => '2026-06-17',
        ];
        $mergedCriteria = FlightOfferDisplayPresenter::mergeStoredSearchCriteria(
            $fromForm,
            $store->get($searchId)['criteria'] ?? null,
        );
        $snapshot = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($offer, $mergedCriteria);

        $meta = [
            'search_criteria' => $mergedCriteria,
            'flight_offer_snapshot' => $snapshot,
            'complex_itinerary_requires_staff_confirmation' => true,
        ];

        $this->assertSame('round_trip', $meta['search_criteria']['trip_type']);
        $this->assertSame('2026-06-17', $meta['search_criteria']['return_date']);
        $this->assertCount(2, $meta['flight_offer_snapshot']['segments']);
        $this->assertCount(2, $meta['flight_offer_snapshot']['journeys_display']);

        $overview = BookingItineraryOverviewPresenter::fromBookingMeta($meta);
        $this->assertNotNull($overview);
        $this->assertSame('LHE ⇄ DXB', $overview['journey_od']);
    }

    public function test_multi_city_snapshot_preserves_segments_and_flat_offer_segments(): void
    {
        $criteria = [
            'trip_type' => 'multi_city',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-01',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-07-01'],
                ['origin' => 'DXB', 'destination' => 'JED', 'departure_date' => '2026-07-05'],
            ],
            'cabin' => 'economy',
            'adults' => 1,
        ];
        $segments = [
            ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-07-01T02:00:00', 'arrival_at' => '2026-07-01T10:15:00', 'airline_code' => 'EK', 'flight_number' => '601'],
            ['origin' => 'DXB', 'destination' => 'JED', 'departure_at' => '2026-07-05T14:00:00', 'arrival_at' => '2026-07-05T18:00:00', 'airline_code' => 'EK', 'flight_number' => '602'],
        ];
        $offer = [
            'id' => 'offer-mc-1',
            'offer_id' => 'offer-mc-1',
            'segments' => $segments,
        ];

        $store = app(FlightSearchResultStore::class);
        $searchId = $store->store($criteria, [$offer], []);

        $fromForm = [
            'origin' => 'LHE',
            'destination' => 'JED',
            'trip_type' => 'multi_city',
            'depart_date' => '2026-07-01',
        ];
        $mergedCriteria = FlightOfferDisplayPresenter::mergeStoredSearchCriteria(
            $fromForm,
            $store->get($searchId)['criteria'] ?? null,
        );
        $snapshot = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($offer, $mergedCriteria);

        $meta = [
            'search_criteria' => $mergedCriteria,
            'flight_offer_snapshot' => $snapshot,
        ];

        $this->assertSame('multi_city', $meta['search_criteria']['trip_type']);
        $this->assertCount(2, $meta['search_criteria']['segments']);
        $this->assertCount(2, $meta['flight_offer_snapshot']['segments']);

        $overview = BookingItineraryOverviewPresenter::fromBookingMeta($meta);
        $this->assertNotNull($overview);
        $this->assertSame('LHE → DXB · DXB → JED', $overview['journey_od']);
    }

    public function test_one_way_merge_and_snapshot_unchanged(): void
    {
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-01',
            'trip_type' => 'one_way',
        ];
        $offer = [
            'id' => 'offer-ow-1',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-01T08:00:00', 'arrival_at' => '2026-06-01T14:00:00'],
            ],
        ];

        $merged = FlightOfferDisplayPresenter::mergeStoredSearchCriteria($criteria, $criteria);
        $snapshot = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($offer, $merged);

        $this->assertSame('one_way', $merged['trip_type']);
        $this->assertArrayNotHasKey('journeys_display', $snapshot);
        $this->assertCount(1, $snapshot['segments']);

        $overview = BookingItineraryOverviewPresenter::fromBookingMeta([
            'search_criteria' => $merged,
            'flight_offer_snapshot' => $snapshot,
        ]);
        $this->assertSame('LHE → DXB', $overview['journey_od'] ?? '');
        $this->assertSame('One-way', $overview['trip_type_label'] ?? '');
    }
}
