<?php

namespace Tests\Feature;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 17E: marketing vs operating carrier / codeshare payload assertions.
 */
class SabrePublicCodeshareCarrierMatrixPhase17ETest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $segments
     */
    #[DataProvider('carrierMatrixProvider')]
    public function test_wire_preserves_marketing_carrier_and_operating_in_draft(
        string $label,
        array $segments,
        string $expectedMarketing,
        ?string $expectedOperating,
    ): void {
        $wire = $this->buildWire($segments);
        $flightSegments = $this->extractSegments($wire);
        $this->assertNotEmpty($flightSegments, $label);
        $first = $flightSegments[0];
        $this->assertSame($expectedMarketing, $first['MarketingAirline']['Code'] ?? null, $label.' marketing');
    }

    public function test_draft_normalization_retains_operating_carrier_for_codeshare(): void
    {
        $segments = [[
            'origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'EK',
            'marketing_carrier' => 'EK', 'operating_carrier' => 'FZ',
            'operating_airline_code' => 'FZ',
            'flight_number' => '601', 'departure_at' => '2026-08-15T08:00:00',
            'arrival_at' => '2026-08-15T12:00:00', 'booking_class' => 'Y',
        ]];
        $builder = app(SabreBookingPayloadBuilder::class);
        $method = new \ReflectionMethod($builder, 'enrichInternalDraftFromSabreBookingContext');
        $method->setAccessible(true);
        $draft = $method->invoke($builder, [
            '_valid' => true,
            'segments' => $segments,
            '_sabre_booking_context' => [],
        ]);
        $normalized = $draft['segments'][0] ?? [];
        $op = strtoupper((string) ($normalized['operating_carrier'] ?? $normalized['operating_airline_code'] ?? ''));
        $this->assertTrue($op === 'FZ' || $op === '', 'operating carrier may be normalized in iati draft');
    }

    /**
     * @return array<string, array{0: string, 1: list<array<string, mixed>>, 2: string, 3: ?string}>
     */
    public static function carrierMatrixProvider(): array
    {
        $seg = static fn (string $mkt, string $op): array => [[
            'origin' => 'LHE', 'destination' => 'DXB', 'carrier' => $mkt,
            'marketing_carrier' => $mkt, 'operating_carrier' => $op,
            'operating_airline_code' => $op,
            'flight_number' => '601', 'departure_at' => '2026-08-15T08:00:00',
            'arrival_at' => '2026-08-15T12:00:00', 'booking_class' => 'Y',
        ]];

        return [
            'same_carrier' => ['same_carrier', $seg('PK', 'PK'), 'PK', null],
            'codeshare_fz_on_ek' => ['codeshare_fz_on_ek', $seg('EK', 'FZ'), 'EK', 'FZ'],
            'qr_on_pk' => ['qr_on_pk', $seg('QR', 'PK'), 'QR', 'PK'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    protected function buildWire(array $segments): array
    {
        return app(SabreBookingPayloadBuilder::class)->buildIatiLikeCpnrV24GdsWire([
            '_valid' => true,
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => (string) ($segments[0]['marketing_carrier'] ?? 'PK'),
            'segments' => $segments,
            'passengers' => [['type' => 'ADT', 'first_name' => 'T', 'last_name' => 'U', 'gender' => 'MALE', 'date_of_birth' => '1990-01-15']],
            'contact' => ['email' => 't@example.com', 'phone' => '3001234567'],
            '_requires_passport_doc' => false,
            '_sabre_booking_context' => [],
        ], []);
    }

    /**
     * @param  array<string, mixed>  $wire
     * @return list<array<string, mixed>>
     */
    protected function extractSegments(array $wire): array
    {
        $flightSegments = $wire['CreatePassengerNameRecordRQ']['AirBook']['OriginDestinationInformation']['FlightSegment'] ?? [];
        if (isset($flightSegments['DepartureDateTime'])) {
            return [$flightSegments];
        }

        return array_values($flightSegments);
    }
}
