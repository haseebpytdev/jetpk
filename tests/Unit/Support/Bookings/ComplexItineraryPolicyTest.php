<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Booking;
use App\Support\Bookings\ComplexItineraryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplexItineraryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_trip_search_criteria_is_complex(): void
    {
        $booking = Booking::factory()->create([
            'meta' => [
                'search_criteria' => ['trip_type' => 'round_trip'],
            ],
        ]);

        $this->assertTrue(ComplexItineraryPolicy::isComplex($booking));
    }

    public function test_multi_city_segments_criteria_is_complex(): void
    {
        $booking = Booking::factory()->create([
            'meta' => [
                'search_criteria' => [
                    'trip_type' => 'multi_city',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-06-01'],
                        ['origin' => 'DXB', 'destination' => 'IST', 'depart_date' => '2026-06-05'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue(ComplexItineraryPolicy::isComplex($booking));
    }

    public function test_one_way_without_extra_signals_is_not_complex(): void
    {
        $booking = Booking::factory()->create([
            'meta' => [
                'search_criteria' => ['trip_type' => 'one_way'],
                'flight_offer_snapshot' => ['segments' => [['origin' => 'LHE', 'destination' => 'DXB']]],
            ],
        ]);

        $this->assertFalse(ComplexItineraryPolicy::isComplex($booking));
    }

    public function test_multiple_journey_groups_is_complex(): void
    {
        $booking = Booking::factory()->create([
            'meta' => [
                'search_criteria' => ['trip_type' => 'one_way'],
                'validated_offer_snapshot' => [
                    'journeys_display' => [
                        ['label' => 'Outbound'],
                        ['label' => 'Return'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue(ComplexItineraryPolicy::isComplex($booking));
    }

    public function test_public_checkout_always_defers_when_complex_even_if_config_enabled(): void
    {
        config(['suppliers.sabre.complex_itinerary_pnr_enabled' => true]);
        $booking = Booking::factory()->create([
            'meta' => ['search_criteria' => ['trip_type' => 'round_trip']],
        ]);

        $this->assertTrue(ComplexItineraryPolicy::shouldDeferSabrePnr($booking, true));
    }
}
