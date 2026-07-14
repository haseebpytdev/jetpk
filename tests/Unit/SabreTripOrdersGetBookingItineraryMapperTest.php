<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\SabreTripOrdersGetBookingItineraryMapper;
use Tests\TestCase;

class SabreTripOrdersGetBookingItineraryMapperTest extends TestCase
{
    protected SabreTripOrdersGetBookingItineraryMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new SabreTripOrdersGetBookingItineraryMapper;
    }

    public function test_root_flights_maps_complete_candidate_row(): void
    {
        $preview = $this->mapper->mapPreview($this->tripOrdersFlightsJson(), [
            'http_status' => 200,
        ]);

        $this->assertSame(1, $preview['candidate_segment_count']);
        $this->assertSame(1, $preview['mappable_segment_count']);
        $this->assertTrue($preview['safe_to_map_preview']);
        $row = $preview['candidate_rows'][0];
        $this->assertSame('flights', $row['candidate_source']);
        $this->assertSame('LHE', $row['origin']);
        $this->assertSame('DXB', $row['destination']);
        $this->assertSame('2026-06-01T08:00:00', $row['departure_at']);
        $this->assertSame('2026-06-01T14:00:00', $row['arrival_at']);
        $this->assertSame('EK', $row['marketing_airline']);
        $this->assertSame('EK', $row['operating_airline']);
        $this->assertSame('501', $row['flight_number']);
        $this->assertSame('Y', $row['booking_class']);
        $this->assertSame('HK', $row['segment_status']);
        $this->assertSame([], $row['missing_required_fields']);
    }

    public function test_root_all_segments_maps_as_fallback(): void
    {
        $preview = $this->mapper->mapPreview([
            'allSegments' => [
                [
                    'startLocationCode' => 'LHE',
                    'endLocationCode' => 'DXB',
                    'startDate' => '2026-06-01',
                    'startTime' => '08:00',
                    'endDate' => '2026-06-01',
                    'endTime' => '14:00',
                    'vendorCode' => 'EK',
                    'text' => 'EK 501',
                    'type' => 'AIR',
                ],
            ],
        ], ['http_status' => 200]);

        $this->assertSame(1, $preview['mappable_segment_count']);
        $this->assertTrue($preview['safe_to_map_preview']);
        $row = $preview['candidate_rows'][0];
        $this->assertSame('allSegments', $row['candidate_source']);
        $this->assertSame('501', $row['flight_number']);
    }

    public function test_flights_preferred_over_all_segments_when_both_exist(): void
    {
        $preview = $this->mapper->mapPreview(array_merge($this->tripOrdersFlightsJson(), [
            'allSegments' => [
                [
                    'startLocationCode' => 'LHE',
                    'endLocationCode' => 'DXB',
                    'startDate' => '2026-06-01',
                    'startTime' => '08:00',
                    'endDate' => '2026-06-01',
                    'endTime' => '14:00',
                    'vendorCode' => 'EK',
                    'text' => 'EK 501',
                ],
            ],
        ]), ['http_status' => 200]);

        $this->assertSame(1, $preview['candidate_segment_count']);
        $this->assertSame('flights', $preview['candidate_rows'][0]['candidate_source']);
    }

    public function test_resource_unavailable_forces_safe_to_map_false(): void
    {
        $preview = $this->mapper->mapPreview($this->tripOrdersFlightsJson(), [
            'http_status' => 200,
            'response_error_codes' => ['RESOURCE_UNAVAILABLE'],
        ]);

        $this->assertTrue($preview['resource_unavailable_present']);
        $this->assertFalse($preview['safe_to_map_preview']);
    }

    public function test_missing_flight_number_or_datetime_makes_row_unmappable(): void
    {
        $preview = $this->mapper->mapPreview([
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'DXB',
                    'airlineCode' => 'EK',
                ],
            ],
        ], ['http_status' => 200]);

        $this->assertSame(0, $preview['mappable_segment_count']);
        $this->assertFalse($preview['safe_to_map_preview']);
        $missing = $preview['candidate_rows'][0]['missing_required_fields'];
        $this->assertContains('departure_at', $missing);
        $this->assertContains('arrival_at', $missing);
        $this->assertContains('flight_number', $missing);
    }

    public function test_output_does_not_contain_pii_from_json(): void
    {
        $json = array_merge($this->tripOrdersFlightsJson(), [
            'travelers' => [['givenName' => 'JANESECRET', 'surname' => 'DOESECRET']],
            'contactInfo' => ['email' => 'secret@example.com'],
        ]);
        $encoded = json_encode($this->mapper->mapPreview($json, ['http_status' => 200]));

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('JANESECRET', $encoded);
        $this->assertStringNotContainsString('DOESECRET', $encoded);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
    }

    public function test_hx_segment_status_blocks_sync_eligibility(): void
    {
        $preview = $this->mapper->mapPreview([
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'KHI',
                    'departureDate' => '2026-06-06',
                    'departureTime' => '11:00',
                    'arrivalDate' => '2026-06-06',
                    'arrivalTime' => '12:45',
                    'airlineCode' => 'PK',
                    'flightNumber' => '303',
                    'flightStatusCode' => 'HX',
                ],
            ],
        ], ['http_status' => 200]);

        $eligibility = $this->mapper->evaluateSyncEligibility($preview);
        $this->assertFalse($eligibility['can_sync']);
        $this->assertSame('blocked_segment_status', $eligibility['reason_code']);
        $this->assertNull($this->mapper->buildSnapshot($preview, 'IJYJMV'));
    }

    public function test_build_snapshot_allowlists_keys_only(): void
    {
        $preview = $this->mapper->mapPreview($this->tripOrdersFlightsJson(), ['http_status' => 200]);
        $snap = $this->mapper->buildSnapshot($preview, 'UNGKWK', '2026-06-06T12:00:00Z');
        $this->assertIsArray($snap);
        $this->assertEqualsCanonicalizing([
            'source', 'endpoint_path', 'synced_at', 'pnr', 'origin', 'destination',
            'departure_at', 'arrival_at', 'stops', 'segments',
        ], array_keys($snap));
        $this->assertEqualsCanonicalizing([
            'origin', 'destination', 'departure_at', 'arrival_at',
            'airline_code', 'operating_airline_code', 'flight_number', 'booking_class', 'segment_status',
        ], array_keys($snap['segments'][0]));
    }

    public function test_combine_time_formats(): void
    {
        $preview = $this->mapper->mapPreview([
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'DXB',
                    'departureDate' => '2026-06-01',
                    'departureTime' => '0500',
                    'arrivalDate' => '2026-06-01',
                    'arrivalTime' => '5:00 PM',
                    'airlineCode' => 'EK',
                    'flightNumber' => '501',
                ],
            ],
        ], ['http_status' => 200]);

        $row = $preview['candidate_rows'][0];
        $this->assertSame('2026-06-01T05:00:00', $row['departure_at']);
        $this->assertSame('2026-06-01T17:00:00', $row['arrival_at']);
    }

    public function test_refine_resource_unavailable_reason_partial_when_locator_or_segment_present(): void
    {
        $preview = $this->mapper->mapPreview(array_merge($this->tripOrdersFlightsJson(), [
            'errors' => [['code' => 'RESOURCE_UNAVAILABLE']],
        ]), ['http_status' => 200]);

        $this->assertSame(
            'partial_resource_unavailable',
            $this->mapper->refineResourceUnavailableReason($preview, ['airline_locator_present' => false]),
        );
        $this->assertSame(
            'partial_resource_unavailable',
            $this->mapper->refineResourceUnavailableReason($preview, [
                'airline_locator_present' => true,
                'airline_locator_value' => 'RQATZN',
            ]),
        );
    }

    public function test_refine_resource_unavailable_reason_blocked_when_no_partial_signals(): void
    {
        $preview = $this->mapper->mapPreview([
            'errors' => [['code' => 'RESOURCE_UNAVAILABLE']],
        ], ['http_status' => 200]);

        $this->assertSame(
            'blocked_resource_unavailable',
            $this->mapper->refineResourceUnavailableReason($preview, ['airline_locator_present' => false]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function tripOrdersFlightsJson(): array
    {
        return [
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'DXB',
                    'departureDate' => '2026-06-01',
                    'departureTime' => '08:00',
                    'arrivalDate' => '2026-06-01',
                    'arrivalTime' => '14:00',
                    'airlineCode' => 'EK',
                    'operatingAirlineCode' => 'EK',
                    'flightNumber' => '501',
                    'bookingClass' => 'Y',
                    'flightStatusCode' => 'HK',
                ],
            ],
        ];
    }
}
