<?php

namespace Tests\Feature;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabrePublicCreateStructuralPayloadMatrixPhase17DTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $segments
     */
    #[DataProvider('itineraryShapeProvider')]
    public function test_iati_payload_segment_order_and_count_match_authoritative_snapshot(
        string $label,
        array $segments,
        int $expectedSegmentCount,
        string $firstOrigin,
        string $lastDestination,
    ): void {
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
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

        $wire = app(SabreBookingPayloadBuilder::class)->buildIatiLikeCpnrV24GdsWire($draft, []);
        $airBook = $wire['CreatePassengerNameRecordRQ']['AirBook']['OriginDestinationInformation'] ?? [];
        $flightSegments = $airBook['FlightSegment'] ?? [];
        if (isset($flightSegments['DepartureDateTime'])) {
            $flightSegments = [$flightSegments];
        }

        $this->assertCount($expectedSegmentCount, $flightSegments, $label);
        $this->assertSame($firstOrigin, $flightSegments[0]['OriginLocation']['LocationCode'] ?? null, $label);
        $last = $flightSegments[array_key_last($flightSegments)];
        $this->assertSame($lastDestination, $last['DestinationLocation']['LocationCode'] ?? null, $label);
    }

    /**
     * @return array<string, array{0: string, 1: list<array<string, mixed>>, 2: int, 3: string, 4: string}>
     */
    public static function itineraryShapeProvider(): array
    {
        $direct = [[
            'origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'PK', 'marketing_carrier' => 'PK',
            'operating_carrier' => 'PK', 'flight_number' => '233',
            'departure_at' => '2026-08-15T08:00:00', 'arrival_at' => '2026-08-15T11:00:00', 'booking_class' => 'V',
        ]];
        $connecting = [
            [
                'origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'marketing_carrier' => 'PK',
                'operating_carrier' => 'PK', 'flight_number' => '301',
                'departure_at' => '2026-08-15T06:00:00', 'arrival_at' => '2026-08-15T07:30:00', 'booking_class' => 'Y',
            ],
            [
                'origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'marketing_carrier' => 'EK',
                'operating_carrier' => 'EK', 'flight_number' => '601',
                'departure_at' => '2026-08-15T10:00:00', 'arrival_at' => '2026-08-15T12:00:00', 'booking_class' => 'K',
            ],
        ];
        $twoStop = array_merge($connecting, [[
            'origin' => 'DXB', 'destination' => 'LHR', 'carrier' => 'BA', 'marketing_carrier' => 'BA',
            'operating_carrier' => 'BA', 'flight_number' => '105',
            'departure_at' => '2026-08-15T14:00:00', 'arrival_at' => '2026-08-15T18:30:00', 'booking_class' => 'N',
        ]]);
        $returnDirect = array_merge($direct, [[
            'origin' => 'DXB', 'destination' => 'LHE', 'carrier' => 'PK', 'marketing_carrier' => 'PK',
            'operating_carrier' => 'PK', 'flight_number' => '234',
            'departure_at' => '2026-08-22T13:00:00', 'arrival_at' => '2026-08-22T17:00:00', 'booking_class' => 'V',
        ]]);

        return [
            'one_way_direct' => ['one_way_direct', $direct, 1, 'LHE', 'DXB'],
            'one_way_connecting_mixed_carrier' => ['one_way_connecting_mixed_carrier', $connecting, 2, 'LHE', 'DXB'],
            'one_way_two_stops' => ['one_way_two_stops', $twoStop, 3, 'LHE', 'LHR'],
            'return_direct_direct' => ['return_direct_direct', $returnDirect, 2, 'LHE', 'LHE'],
        ];
    }
}
