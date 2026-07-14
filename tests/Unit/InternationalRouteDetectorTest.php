<?php

namespace Tests\Unit;

use App\Services\Booking\InternationalRouteDetector;
use Database\Seeders\AirportAirlineReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternationalRouteDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_pakistan_domestic_pair(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $detector = new InternationalRouteDetector;

        $this->assertTrue($detector->isPakistanDomesticForTravelDocuments('LHE', 'KHI'));
        $this->assertTrue($detector->isPakistanDomesticForTravelDocuments('khi', 'ISB'));
    }

    public function test_non_pk_domestic_or_international_is_not_pk_domestic_documents(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $detector = new InternationalRouteDetector;

        $this->assertFalse($detector->isPakistanDomesticForTravelDocuments('LHE', 'DXB'));
        $this->assertFalse($detector->isPakistanDomesticForTravelDocuments('ORD', 'JFK'));
    }

    public function test_unknown_airport_is_not_pk_domestic_documents(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $detector = new InternationalRouteDetector;

        $this->assertFalse($detector->isPakistanDomesticForTravelDocuments('LHE', 'ZZZ'));
    }

    public function test_multi_country_segments_require_passport_even_if_search_pair_is_pk(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $detector = new InternationalRouteDetector;
        $offer = [
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'KHI'],
                ['origin' => 'KHI', 'destination' => 'DXB'],
            ],
        ];

        $this->assertFalse($detector->nationalIdTravelDocumentsAllowedForOffer($offer, 'LHE', 'KHI'));
    }

    public function test_single_country_non_pakistan_offer_disallows_national_id(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $detector = new InternationalRouteDetector;
        $offer = [
            'segments' => [
                ['origin' => 'ORD', 'destination' => 'JFK'],
            ],
        ];

        $this->assertFalse($detector->nationalIdTravelDocumentsAllowedForOffer($offer, 'ORD', 'JFK'));
    }
}
