<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\SabreTripOrdersGetBookingInspectSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabreTripOrdersGetBookingInspectSummaryTest extends TestCase
{
    private SabreTripOrdersGetBookingInspectSummary $summary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->summary = new SabreTripOrdersGetBookingInspectSummary;
    }

    public function test_summary_extracts_cancelable_and_ticketed_booleans(): void
    {
        $out = $this->summary->buildForProbeRow([
            'bookingId' => 'abc-123',
            'bookingSignature' => 'sig',
            'isCancelable' => false,
            'isTicketed' => true,
            'travelers' => [['givenName' => 'SECRET', 'surname' => 'NAME']],
            'fares' => [['total' => 100]],
            'remarks' => [],
            'request' => ['confirmationId' => 'PNR123'],
        ], ['http_status' => 200, 'map_preview' => ['candidate_segment_count' => 0]]);

        $s = $out['get_booking_status_summary'];
        $this->assertTrue($s['booking_id_present']);
        $this->assertTrue($s['booking_signature_present']);
        $this->assertFalse($s['is_cancelable_value']);
        $this->assertTrue($s['is_ticketed_value']);
        $this->assertSame(1, $s['traveler_count']);
        $this->assertSame(1, $s['fare_count']);
        $this->assertContains('confirmationId', $s['request_keys_sanitized']);
        $this->assertStringNotContainsString('SECRET', json_encode($out));
    }

    public function test_no_segments_and_not_cancelable_infers_likely_cancelled(): void
    {
        $out = $this->summary->buildForProbeRow([
            'bookingId' => 'id',
            'isCancelable' => false,
            'isTicketed' => false,
            'travelers' => [],
            'fares' => [],
        ], ['http_status' => 200, 'map_preview' => ['candidate_segment_count' => 0, 'mappable_segment_count' => 0]]);

        $this->assertTrue($out['cancel_verification_possible']);
        $this->assertSame('likely_cancelled', $out['cancel_verification_status']);
    }

    public function test_missing_status_booleans_unknown_no_status_fields(): void
    {
        $out = $this->summary->buildForProbeRow(['bookingId' => 'x'], ['http_status' => 200]);

        $this->assertNull($out['get_booking_status_summary']['is_cancelable_value']);
        $this->assertFalse($out['cancel_verification_possible']);
        $this->assertSame('unknown_no_status_fields', $out['cancel_verification_status']);
    }

    public function test_http_403_retrieve_forbidden(): void
    {
        $out = $this->summary->buildForProbeRow([], ['http_status' => 403]);

        $this->assertFalse($out['cancel_verification_possible']);
        $this->assertSame('retrieve_forbidden', $out['cancel_verification_status']);
    }

    #[DataProvider('cancelableActiveProvider')]
    public function test_is_cancelable_true_is_likely_active(bool $withSegments): void
    {
        $json = [
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        if ($withSegments) {
            $json['flights'] = [['fromAirportCode' => 'LHE', 'toAirportCode' => 'DXB']];
        }

        $out = $this->summary->buildForProbeRow($json, [
            'http_status' => 200,
            'map_preview' => ['candidate_segment_count' => $withSegments ? 0 : 0],
        ]);

        $this->assertSame('likely_active', $out['cancel_verification_status']);
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function cancelableActiveProvider(): array
    {
        return [
            'no_segments' => [false],
            'with_flights_path' => [true],
        ];
    }

    public function test_extract_direct_cancel_safety_flags_detects_ticket_numbers_key(): void
    {
        $flags = $this->summary->extractDirectCancelSafetyFlags([
            'bookingId' => 'bk-safe-id',
            'isCancelable' => true,
            'isTicketed' => false,
            'ticketNumbers' => ['0011111111111'],
        ]);

        $this->assertTrue($flags['is_cancelable']);
        $this->assertFalse($flags['is_ticketed']);
        $this->assertTrue($flags['ticket_numbers_present']);
        $this->assertTrue($flags['booking_id_present']);

        $encoded = json_encode($flags);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('bk-safe-id', $encoded);
        $this->assertStringNotContainsString('0011111111111', $encoded);
    }

    public function test_build_cancel_schema_inventory_lists_safe_paths_without_values(): void
    {
        $out = $this->summary->buildCancelSchemaInventory([
            'bookingId' => 'secret-booking-id',
            'bookingSignature' => 'secret-signature',
            'isCancelable' => true,
            'isTicketed' => false,
            'orderId' => 'secret-order-id',
            'flights' => [
                ['segmentId' => 'seg-1', 'fromAirportCode' => 'LHE'],
            ],
            'travelers' => [['givenName' => 'Jane', 'email' => 'secret@example.com']],
        ]);

        $presence = is_array($out['cancel_related_presence'] ?? null) ? $out['cancel_related_presence'] : [];
        $this->assertTrue($presence['order_id_present'] ?? false);
        $this->assertGreaterThan(0, (int) ($presence['segment_ids_path_count'] ?? 0));
        $this->assertContains('bookingId', $out['top_level_keys_sanitized']);
        $encoded = json_encode($out);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('secret-booking-id', $encoded);
        $this->assertStringNotContainsString('secret-signature', $encoded);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
    }

    public function test_build_airline_locator_observability_detects_segment_airline_pnr_without_pii(): void
    {
        $out = $this->summary->buildAirlineLocatorObservability([
            'confirmationId' => 'QPXBOE',
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'DXB',
                    'airlinePnr' => 'ABC12X',
                ],
            ],
            'travelers' => [['givenName' => 'Jane', 'email' => 'secret@example.com']],
        ]);

        $this->assertTrue($out['airline_locator_present']);
        $this->assertSame('flights.0.airlinePnr', $out['airline_locator_path']);
        $this->assertContains('flights.0.airlinePnr', $out['airline_locator_paths']);
        $this->assertSame('ABC12X', $out['airline_locator_value']);
        $this->assertTrue($out['trip_orders_confirmation_id_present']);
        $encoded = json_encode($out);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
        $this->assertStringNotContainsString('Jane', $encoded);
    }

    public function test_build_airline_locator_observability_tracks_sabre_record_locator_separately(): void
    {
        $out = $this->summary->buildAirlineLocatorObservability([
            'recordLocator' => 'QPXBOE',
            'flights' => [
                ['airlineLocator' => 'XYZ78A'],
            ],
        ]);

        $this->assertTrue($out['sabre_record_locator_present']);
        $this->assertSame('recordLocator', $out['sabre_record_locator_path']);
        $this->assertSame('QPXBOE', $out['sabre_record_locator_value']);
        $this->assertTrue($out['airline_locator_present']);
        $this->assertSame('flights.0.airlineLocator', $out['airline_locator_path']);
        $this->assertSame('XYZ78A', $out['airline_locator_value']);
    }

    public function test_build_airline_locator_observability_ignores_uuid_booking_ids(): void
    {
        $out = $this->summary->buildAirlineLocatorObservability([
            'bookingId' => '8f3d2c1a-9b4e-4a1f-9c2d-abcdef123456',
            'supplierReference' => 'NOTALOC',
            'flights' => [],
        ]);

        $this->assertFalse($out['airline_locator_present']);
        $this->assertNull($out['airline_locator_value']);
        $this->assertFalse($out['sabre_record_locator_present']);
    }

    public function test_build_for_probe_row_includes_airline_locator_observability(): void
    {
        $out = $this->summary->buildForProbeRow([
            'isCancelable' => true,
            'flights' => [['airlinePnr' => 'AIR45A']],
        ], ['http_status' => 200, 'map_preview' => ['candidate_segment_count' => 1]]);

        $this->assertIsArray($out['airline_locator_observability'] ?? null);
        $this->assertTrue($out['airline_locator_observability']['airline_locator_present']);
    }

    public function test_discovers_segment_paths_without_pii(): void
    {
        $out = $this->summary->buildForProbeRow([
            'isCancelable' => false,
            'isTicketed' => true,
            'flights' => [
                ['fromAirportCode' => 'LHE', 'toAirportCode' => 'DXB', 'flightNumber' => '501'],
            ],
            'travelers' => [['email' => 'secret@example.com', 'givenName' => 'Jane']],
        ], ['http_status' => 200, 'map_preview' => ['candidate_segment_count' => 0]]);

        $paths = array_column($out['possible_air_item_paths'], 'path');
        $this->assertContains('flights', $paths);
        $this->assertSame('unknown_no_segments', $out['cancel_verification_status']);
        $encoded = json_encode($out);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
        $this->assertStringNotContainsString('Jane', $encoded);
    }
}
