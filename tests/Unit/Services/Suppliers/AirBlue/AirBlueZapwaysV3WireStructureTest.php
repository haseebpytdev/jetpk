<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConnectionSearchPolicy;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use DOMDocument;
use DOMXPath;
use ReflectionClass;
use Tests\TestCase;

class AirBlueZapwaysV3WireStructureTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function v3Config(): array
    {
        return [
            'protocol_version' => AirBlueZapwaysProtocolVersion::V3->value,
            'namespace' => AirBlueZapwaysProtocolVersion::V3->namespaceUri(),
            'client_id' => 'CLIENT',
            'client_key' => 'KEY',
            'agent_type' => '5',
            'agent_id' => 'AGENT',
            'agent_password' => 'secret',
            'service_target' => 'Test',
            'service_version' => '1.04',
            'currency' => 'PKR',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleSegment(): array
    {
        return [
            'departure_datetime' => '2026-10-15T08:00:00',
            'flight_number' => '401',
            'rbd' => 'Y',
            'fare_type' => 'PUBLISHED',
            'cabin_class' => 'ECONOMY',
            'departure_airport' => 'KHI',
            'arrival_airport' => 'ISB',
            'marketing_carrier' => 'PA',
        ];
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadXML($xml);

        return new DOMXPath($document);
    }

    public function test_invalid_protocol_versions_fail_closed(): void
    {
        foreach (['4.0', 'v4', 'banana', '3.1'] as $invalid) {
            try {
                AirBlueZapwaysProtocolVersion::fromCredentials(['protocol_version' => $invalid]);
                $this->fail('Expected invalid protocol to throw for: '.$invalid);
            } catch (AirBlueValidationException $exception) {
                $this->assertSame('invalid_protocol_version', $exception->normalizedCode);
            }
        }
    }

    public function test_blank_protocol_defaults_to_v2(): void
    {
        $this->assertSame(
            AirBlueZapwaysProtocolVersion::V2,
            AirBlueZapwaysProtocolVersion::fromCredentials([]),
        );
    }

    public function test_seat_map_exact_hierarchy(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirSeatMapRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'flight_segments' => [$this->sampleSegment()],
        ]);

        $xpath = $this->xpath($xml);
        $this->assertSame(1, $xpath->query('//*[local-name()="AirSeatMap"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="airSeatMapRQ"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="airSeatMapRQ"]/*[local-name()="SeatMapRequests"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="SeatMapRequests"]/*[local-name()="SeatMapRequest"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="SeatMapRequest"]/*[local-name()="FlightSegmentInfo"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="airSeatMapRQ"]/*[local-name()="BookingReferenceID"]')->length);
        $this->assertSame(0, $xpath->query('//*[local-name()="airSeatMapRQ"]/*[local-name()="FlightSegmentInfo"]')->length);
    }

    public function test_seat_map_multiple_segments_use_multiple_requests(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirSeatMapRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'flight_segments' => [
                $this->sampleSegment(),
                array_merge($this->sampleSegment(), ['flight_number' => '402']),
            ],
        ]);

        $xpath = $this->xpath($xml);
        $this->assertSame(2, $xpath->query('//*[local-name()="SeatMapRequest"]')->length);
    }

    public function test_ancillary_exact_hierarchy(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirAncillaryItemsRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'flight_segments' => [$this->sampleSegment()],
        ]);

        $xpath = $this->xpath($xml);
        $this->assertSame(1, $xpath->query('//*[local-name()="AirAncillaryItems"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="airAncillaryItemsRQ"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="airAncillaryItemsRQ"]/*[local-name()="AncillaryItemRequests"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="AncillaryItemRequests"]/*[local-name()="AncillaryItemRequest"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="AncillaryItemRequest"]/*[local-name()="FlightSegmentInfo"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="airAncillaryItemsRQ"]/*[local-name()="BookingReferenceID"]')->length);
    }

    public function test_air_book_modify_seat_exact_hierarchy(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookModifyRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'modification_type' => '5',
            'seat_changes' => [[
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ]],
        ]);

        $xpath = $this->xpath($xml);
        $this->assertSame(1, $xpath->query('//*[local-name()="airBookModifyRQ"]/*[local-name()="AirBookModifyRQ"][@ModificationType="5"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="AirBookModifyRQ"]/*[local-name()="TravelerInfo"]/*[local-name()="SpecialReqDetails"]/*[local-name()="SeatRequests"]/*[local-name()="SeatRequest"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="airBookModifyRQ"]/*[local-name()="AirReservation"]/*[local-name()="BookingReferenceID"]')->length);
        $this->assertSame(0, $xpath->query('//*[local-name()="airBookModifyRQ"]/*[local-name()="UniqueID"]')->length);
        $this->assertSame(0, $xpath->query('//*[local-name()="airBookModifyRQ"][@ModificationType]')->length);
    }

    public function test_air_book_modify_item_exact_hierarchy(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookModifyRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'modification_type' => '5',
            'item_changes' => [[
                'item_code' => 'XBAG20',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
                'item_count' => 0,
            ]],
        ]);

        $xpath = $this->xpath($xml);
        $this->assertSame(1, $xpath->query('//*[local-name()="SpecialReqDetails"]/*[local-name()="SpecialServiceRequests"]/*[local-name()="SpecialServiceRequest"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="SpecialServiceRequest"][@ItemCount="0"]')->length);
    }

    public function test_air_book_modify_combined_seat_and_item_hierarchy(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookModifyRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'modification_type' => '5',
            'seat_changes' => [[
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ]],
            'item_changes' => [[
                'item_code' => 'XBAG20',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ]],
        ]);

        $xpath = $this->xpath($xml);
        $this->assertSame(1, $xpath->query('//*[local-name()="SpecialReqDetails"]/*[local-name()="SeatRequests"]')->length);
        $this->assertSame(1, $xpath->query('//*[local-name()="SpecialReqDetails"]/*[local-name()="SpecialServiceRequests"]')->length);
    }

    public function test_read_without_instance_omits_attribute(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildReadRequest($this->v3Config(), 'ABC123', '');
        $xpath = $this->xpath($xml);
        $node = $xpath->query('//*[local-name()="UniqueID"]')->item(0);
        $this->assertNotNull($node);
        $this->assertFalse($node->hasAttribute('Instance'));
    }

    public function test_read_with_instance_includes_attribute(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildReadRequest($this->v3Config(), 'ABC123', 'INST1');
        $xpath = $this->xpath($xml);
        $node = $xpath->query('//*[local-name()="UniqueID"]')->item(0);
        $this->assertNotNull($node);
        $this->assertSame('INST1', $node->getAttribute('Instance'));
    }

    public function test_cancel_without_instance_omits_attribute(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildCancelRequest($this->v3Config(), 'ABC123', '');
        $xpath = $this->xpath($xml);
        $node = $xpath->query('//*[local-name()="UniqueID"]')->item(0);
        $this->assertNotNull($node);
        $this->assertFalse($node->hasAttribute('Instance'));
    }

    public function test_exchange_cancel_with_instance_includes_attribute(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildCancelRequest($this->v3Config(), 'ABC123', 'EXCH1');
        $xpath = $this->xpath($xml);
        $node = $xpath->query('//*[local-name()="UniqueID"]')->item(0);
        $this->assertNotNull($node);
        $this->assertSame('EXCH1', $node->getAttribute('Instance'));
    }

    public function test_unresolved_v3_soap_action_fails_closed(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        try {
            $method->invoke($client, 'air_seat_map', ['is_test' => true, 'protocol_version' => '3.0']);
            $this->fail('Expected missing_soap_action exception.');
        } catch (AirBlueValidationException $exception) {
            $this->assertSame('missing_soap_action', $exception->normalizedCode);
        }
    }

    public function test_configured_v3_soap_action_resolves(): void
    {
        $versions = (array) config('suppliers.airblue.protocol_versions');
        $v3 = is_array($versions['3.0'] ?? null) ? $versions['3.0'] : [];
        $operations = is_array($v3['ota_operations'] ?? null) ? $v3['ota_operations'] : [];
        $operations['air_seat_map'] = ['soap_action' => 'https://example.test/AirSeatMap'];
        $v3['ota_operations'] = $operations;
        $versions['3.0'] = $v3;
        config(['suppliers.airblue.protocol_versions' => $versions]);

        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        $action = $method->invoke($client, 'air_seat_map', ['is_test' => true, 'protocol_version' => '3.0']);

        $this->assertSame('https://example.test/AirSeatMap', $action);
    }

    public function test_v3_read_soap_action_uses_supplier_document_hosts(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        $test = $method->invoke($client, 'read', ['is_test' => true, 'protocol_version' => '3.0']);
        $live = $method->invoke($client, 'read', ['is_test' => false, 'protocol_version' => '3.0']);

        $this->assertSame('https://ota.qa.zapways.com/Read', $test);
        $this->assertSame('https://ota.zapways.com/Read', $live);
    }

    public function test_uncertified_v3_does_not_displace_legacy_v2(): void
    {
        $v2 = $this->makeConnection(['id' => 1]);
        $v3 = $this->makeConnection([
            'id' => 2,
            'credentials' => $this->credentials([
                'protocol_version' => '3.0',
                'certification_status' => 'pending',
            ]),
        ]);

        $deduped = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$v2, $v3]));

        $this->assertCount(1, $deduped->filter(fn ($c) => $c->provider === SupplierProvider::Airblue));
        $this->assertSame(1, (int) $deduped->firstWhere('provider', SupplierProvider::Airblue)->id);
    }

    public function test_certified_v3_can_be_selected_over_legacy_v2(): void
    {
        $v2 = $this->makeConnection(['id' => 1]);
        $v3 = $this->makeConnection([
            'id' => 2,
            'credentials' => $this->credentials([
                'protocol_version' => '3.0',
                'certification_status' => 'certified',
            ]),
        ]);

        $deduped = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$v2, $v3]));

        $this->assertCount(1, $deduped->filter(fn ($c) => $c->provider === SupplierProvider::Airblue));
        $this->assertSame(2, (int) $deduped->firstWhere('provider', SupplierProvider::Airblue)->id);
    }

    public function test_generated_seat_map_matches_fixture_structure(): void
    {
        $generated = (new AirBlueOtaXmlBuilder)->buildAirSeatMapRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'flight_segments' => [$this->sampleSegment()],
        ]);
        $fixture = (string) file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/requests/air_seat_map_rq.xml'));

        $this->assertSameStructure(
            $this->xpath($fixture),
            $this->xpath($generated),
            [
                '//*[local-name()="airSeatMapRQ"]/*[local-name()="SeatMapRequests"]/*[local-name()="SeatMapRequest"]/*[local-name()="FlightSegmentInfo"]',
                '//*[local-name()="airSeatMapRQ"]/*[local-name()="BookingReferenceID"]',
            ],
        );
    }

    public function test_generated_ancillary_matches_fixture_structure(): void
    {
        $generated = (new AirBlueOtaXmlBuilder)->buildAirAncillaryItemsRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'flight_segments' => [$this->sampleSegment()],
        ]);
        $fixture = (string) file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/requests/air_ancillary_items_rq.xml'));

        $this->assertSameStructure(
            $this->xpath($fixture),
            $this->xpath($generated),
            [
                '//*[local-name()="airAncillaryItemsRQ"]/*[local-name()="AncillaryItemRequests"]/*[local-name()="AncillaryItemRequest"]/*[local-name()="FlightSegmentInfo"]',
                '//*[local-name()="airAncillaryItemsRQ"]/*[local-name()="BookingReferenceID"]',
            ],
        );
    }

    /**
     * @param  list<string>  $queries
     */
    private function assertSameStructure(DOMXPath $fixture, DOMXPath $generated, array $queries): void
    {
        foreach ($queries as $query) {
            $this->assertSame(
                $fixture->query($query)->length,
                $generated->query($query)->length,
                'Structure mismatch for query: '.$query,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeConnection(array $attributes = []): SupplierConnection
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => $this->credentials(),
            ...$attributes,
        ]);
        $connection->id = (int) ($attributes['id'] ?? 42);

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function credentials(array $overrides = []): array
    {
        return array_merge([
            'api_channel' => 'zapways_ota',
            'client_id' => 'client',
            'client_key' => 'key',
            'agent_type' => '5',
            'agent_id' => 'agent',
            'agent_password' => 'secret',
        ], $overrides);
    }
}
