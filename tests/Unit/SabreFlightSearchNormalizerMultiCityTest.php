<?php

namespace Tests\Unit;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSegmentData;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class SabreFlightSearchNormalizerMultiCityTest extends TestCase
{
    #[Test]
    public function test_accepts_valid_multi_city_itinerary_matching_requested_legs(): void
    {
        $segments = [
            new FlightSegmentData('LHE', 'DOH', '2026-06-10T08:00:00', '2026-06-10T10:00:00'),
            new FlightSegmentData('DOH', 'DXB', '2026-06-10T14:00:00', '2026-06-10T16:00:00'),
            new FlightSegmentData('DXB', 'JED', '2026-06-12T09:00:00', '2026-06-12T11:00:00'),
            new FlightSegmentData('JED', 'LHE', '2026-06-14T18:00:00', '2026-06-15T02:00:00'),
        ];

        $searchRequest = FlightSearchRequestData::fromArray([
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-06-10'],
                ['origin' => 'DXB', 'destination' => 'JED', 'departure_date' => '2026-06-12'],
                ['origin' => 'JED', 'destination' => 'LHE', 'departure_date' => '2026-06-14'],
            ],
        ]);

        $this->assertTrue($this->invokeMatchesSearchEndpoints(
            $searchRequest,
            'LHE',
            'LHE',
            $segments,
        ));
    }

    #[Test]
    public function test_rejects_unrelated_multi_city_itinerary(): void
    {
        $segments = [
            new FlightSegmentData('LHE', 'DXB', '2026-06-10T08:00:00', '2026-06-10T11:00:00'),
        ];

        $searchRequest = FlightSearchRequestData::fromArray([
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-06-10'],
                ['origin' => 'DXB', 'destination' => 'JED', 'departure_date' => '2026-06-12'],
            ],
        ]);

        $this->assertFalse($this->invokeMatchesSearchEndpoints(
            $searchRequest,
            'LHE',
            'DXB',
            $segments,
        ));
    }

    #[Test]
    public function test_discontinuous_multicity_itinerary_normalizes_when_slice_endpoints_match(): void
    {
        $json = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multicity_three_slice_response.json')),
            true,
        );
        $this->assertIsArray($json);

        $searchRequest = FlightSearchRequestData::fromArray([
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'departure_date' => '2026-08-20'],
                ['origin' => 'ISB', 'destination' => 'DXB', 'departure_date' => '2026-08-25'],
                ['origin' => 'DXB', 'destination' => 'LHE', 'departure_date' => '2026-09-02'],
            ],
        ]);

        $conn = \App\Models\SupplierConnection::make(['provider' => 'sabre']);
        $conn->id = 1;
        $offers = app(SabreFlightSearchNormalizer::class)->normalize($json, $conn, $searchRequest);

        $this->assertCount(1, $offers);
        $this->assertCount(3, $offers[0]->segments);
    }

    /**
     * @param  list<FlightSegmentData>  $workingSegments
     */
    protected function invokeMatchesSearchEndpoints(
        FlightSearchRequestData $searchRequest,
        string $offerOrigin,
        string $offerDestination,
        array $workingSegments,
    ): bool {
        $normalizer = app(SabreFlightSearchNormalizer::class);
        $method = new ReflectionMethod(SabreFlightSearchNormalizer::class, 'matchesSearchEndpoints');
        $method->setAccessible(true);

        return (bool) $method->invoke($normalizer, $searchRequest, $offerOrigin, $offerDestination, $workingSegments);
    }
}
