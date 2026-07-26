<?php

namespace Tests\Feature;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Sabre\SabrePublicCreateStructuralScenarioCatalog;
use Tests\TestCase;

/**
 * Phase 17E: one-way structural Create PNR payload matrix (builder-level, sanitized fixtures).
 */
class SabrePublicOneWayStructuralMatrixPhase17ETest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $segments
     */
    #[DataProvider('oneWayProvider')]
    public function test_one_way_payload_segment_structure(
        string $label,
        array $segments,
        int $expectedSegmentCount,
        string $firstOrigin,
        string $lastDestination,
    ): void {
        $wire = $this->buildWireFromSegments($segments);
        $flightSegments = $this->extractFlightSegmentsFromWire($wire);

        $this->assertCount($expectedSegmentCount, $flightSegments, $label);
        $this->assertSame($firstOrigin, $flightSegments[0]['OriginLocation']['LocationCode'] ?? null, $label);
        $last = $flightSegments[array_key_last($flightSegments)];
        $this->assertSame($lastDestination, $last['DestinationLocation']['LocationCode'] ?? null, $label);
        $this->assertSame($firstOrigin, $flightSegments[0]['OriginLocation']['LocationCode'] ?? null, $label.' trip origin');
    }

    public static function oneWayProvider(): array
    {
        return SabrePublicCreateStructuralScenarioCatalog::oneWayScenarios();
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    protected function buildWireFromSegments(array $segments): array
    {
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => (string) ($segments[0]['carrier'] ?? 'PK'),
            'segments' => $segments,
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => 'Test',
                'last_name' => 'Traveler',
                'gender' => 'MALE',
                'date_of_birth' => '1990-01-15',
            ]],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
            '_requires_passport_doc' => false,
            '_sabre_booking_context' => [],
        ];

        return app(SabreBookingPayloadBuilder::class)->buildIatiLikeCpnrV24GdsWire($draft, []);
    }

    /**
     * @param  array<string, mixed>  $wire
     * @return list<array<string, mixed>>
     */
    protected function extractFlightSegmentsFromWire(array $wire): array
    {
        $airBook = $wire['CreatePassengerNameRecordRQ']['AirBook']['OriginDestinationInformation'] ?? [];
        $flightSegments = $airBook['FlightSegment'] ?? [];
        if (isset($flightSegments['DepartureDateTime'])) {
            return [$flightSegments];
        }

        return array_values($flightSegments);
    }
}
