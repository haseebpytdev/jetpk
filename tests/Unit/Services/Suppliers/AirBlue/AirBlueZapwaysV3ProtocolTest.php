<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueAncillaryService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueConnectionSearchPolicy;
use App\Services\Suppliers\AirBlue\AirBlueOtaResponseNormalizer;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use App\Services\Suppliers\AirBlue\AirBlueProtocolGuard;
use App\Services\Suppliers\AirBlue\AirBlueSeatSelectionGate;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use Illuminate\Support\Collection;
use ReflectionClass;
use Tests\TestCase;

class AirBlueZapwaysV3ProtocolTest extends TestCase
{
    public function test_protocol_defaults_to_v2(): void
    {
        $connection = $this->makeConnection();

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertSame('2.0', $config['protocol_version']);
        $this->assertSame('http://zapways.com/air/ota/2.0', $config['namespace']);
    }

    public function test_v3_test_endpoint_and_namespace(): void
    {
        $connection = $this->makeConnection([
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => null,
            'credentials' => $this->credentials(['protocol_version' => '3.0']),
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertSame('3.0', $config['protocol_version']);
        $this->assertStringContainsString('/v3.0/', $config['endpoint_url']);
        $this->assertStringContainsString('otatest4.zapways.com', $config['endpoint_url']);
        $this->assertSame('http://zapways.com/air/ota/3.0', $config['namespace']);
        $this->assertSame('Test', $config['service_target']);
        $this->assertSame('1.04', $config['service_version']);
    }

    public function test_v2_live_endpoint_and_namespace(): void
    {
        $connection = $this->makeConnection([
            'environment' => SupplierEnvironment::Live,
            'base_url' => null,
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertFalse($config['is_test']);
        $this->assertStringContainsString('ota4.zapways.com/v2.0/', $config['endpoint_url']);
        $this->assertSame('Production', $config['service_target']);
        $this->assertSame('http://zapways.com/air/ota/2.0', $config['namespace']);
    }

    public function test_v3_live_is_gated_without_explicit_enablement(): void
    {
        $connection = $this->makeConnection([
            'environment' => SupplierEnvironment::Live,
            'base_url' => null,
            'credentials' => $this->credentials(['protocol_version' => '3.0']),
        ]);

        $this->expectException(AirBlueValidationException::class);
        app(AirBlueConfigResolver::class)->resolveOta($connection);
    }

    public function test_v3_search_builder_uses_v3_namespace(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $request = new FlightSearchRequestData(
            origin: 'KHI',
            destination: 'ISB',
            departure_date: '2025-05-28',
            return_date: null,
            adults: 1,
            children: 0,
            infants: 0,
            cabin: 'Y',
        );

        $xml = $builder->buildAirLowFareSearchRequest($request, $this->v3Config());

        $this->assertStringContainsString('xmlns:zap="http://zapways.com/air/ota/3.0"', $xml);
        $this->assertStringContainsString('airLowFareSearchRQ', $xml);
        $this->assertStringContainsString('Version="1.04"', $xml);
        $this->assertStringContainsString('Target="Test"', $xml);
    }

    public function test_v3_search_parses_fare_type_and_cabin_class(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_low_fare_search_v3.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $segment = $parsed['parsed']['priced_itineraries'][0]['segments'][0];

        $this->assertSame('PUBLISHED', $segment['fare_type']);
        $this->assertSame('ECONOMY', $segment['cabin_class']);
    }

    public function test_v3_search_preserves_fare_type_and_cabin_class_in_provider_context(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_low_fare_search_v3.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $connection = $this->makeConnection([
            'credentials' => $this->credentials(['protocol_version' => '3.0']),
        ]);
        $offers = (new AirBlueOtaResponseNormalizer)->normalizeSearchResponse($parsed, $connection, 'corr-v3');
        $context = $offers[0]->raw_payload['provider_context'];

        $this->assertSame('3.0', $context['protocol_version']);
        $this->assertSame('PUBLISHED', $context['priced_itineraries'][0]['segments'][0]['fare_type']);
        $this->assertSame('ECONOMY', $context['priced_itineraries'][0]['segments'][0]['cabin_class']);
    }

    public function test_air_seat_map_request_and_parser(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $requestXml = $builder->buildAirSeatMapRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'flight_segments' => [[
                'departure_datetime' => '2026-10-15T08:00:00',
                'flight_number' => '401',
                'rbd' => 'Y',
                'fare_type' => 'PUBLISHED',
                'cabin_class' => 'ECONOMY',
                'departure_airport' => 'KHI',
                'arrival_airport' => 'ISB',
                'marketing_carrier' => 'PA',
            ]],
        ]);

        $this->assertStringContainsString('airSeatMapRQ', $requestXml);
        $this->assertStringContainsString('FlightSegmentInfo', $requestXml);
        $this->assertStringContainsString('FareType="PUBLISHED"', $requestXml);
        $this->assertStringContainsString('CabinClass="ECONOMY"', $requestXml);

        $responseXml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_seat_map_success.xml'));
        $this->assertIsString($responseXml);
        $parsed = (new AirBlueOtaXmlParser)->parse($responseXml);
        $seat = $parsed['parsed']['seat_maps'][0]['rows'][0]['seats'][0];

        $this->assertSame('A', $seat['seat_number']);
        $this->assertSame('true', $seat['available_ind']);
        $this->assertSame('1500.00', $seat['cost']);
        $this->assertSame('PKR', $seat['currency']);
        $this->assertContains('NOT_SUITABLE_FOR_CHILD', $seat['restrictions']);
    }

    public function test_air_ancillary_items_request_and_parser(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $requestXml = $builder->buildAirAncillaryItemsRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'flight_segments' => [[
                'departure_datetime' => '2026-10-15T08:00:00',
                'flight_number' => '401',
                'rbd' => 'Y',
                'fare_type' => 'PUBLISHED',
                'cabin_class' => 'ECONOMY',
                'departure_airport' => 'KHI',
                'arrival_airport' => 'ISB',
                'marketing_carrier' => 'PA',
            ]],
        ]);

        $this->assertStringContainsString('airAncillaryItemsRQ', $requestXml);

        $responseXml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_ancillary_items_success.xml'));
        $this->assertIsString($responseXml);
        $parsed = (new AirBlueOtaXmlParser)->parse($responseXml);
        $groups = $parsed['parsed']['ancillary_items'];

        $this->assertSame('XBAG', $groups[0]['group_code']);
        $this->assertSame('XBAG20', $groups[0]['items'][0]['item_code']);
        $this->assertSame('false', $groups[0]['multiple_choice']);
        $this->assertSame('WCHR', $groups[1]['group_code']);
        $this->assertSame('true', $groups[1]['multiple_choice']);
    }

    public function test_air_book_modify_seat_and_item_changes(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $xml = $builder->buildAirBookModifyRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'modification_type' => '5',
            'seat_changes' => [[
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ]],
            'item_changes' => [
                [
                    'item_code' => 'XBAG20',
                    'traveler_ref_number_rph' => '1',
                    'flight_ref_number_rph' => '1',
                ],
                [
                    'item_code' => 'XBAG20',
                    'traveler_ref_number_rph' => '1',
                    'flight_ref_number_rph' => '1',
                    'item_count' => 0,
                ],
            ],
        ]);

        $this->assertStringContainsString('ModificationType="5"', $xml);
        $this->assertStringContainsString('SeatRequest', $xml);
        $this->assertStringContainsString('SpecialServiceRequest', $xml);
        $this->assertStringContainsString('ItemCount="0"', $xml);
    }

    public function test_read_transaction_history_and_negative_values(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_read_transaction_history.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $history = $parsed['parsed']['transaction_history'];

        $this->assertCount(3, $history);
        $this->assertSame('Additional_Collection', $history[1]['fare_amount_type']);
        $this->assertSame(-500.0, $history[2]['amount']);
        $this->assertNotEmpty($parsed['parsed']['seats']);
        $this->assertNotEmpty($parsed['parsed']['items']);
    }

    public function test_protocol_mismatch_fails_closed(): void
    {
        $connection = $this->makeConnection(['credentials' => $this->credentials(['protocol_version' => '3.0'])]);
        $guard = app(AirBlueProtocolGuard::class);

        $this->expectException(AirBlueValidationException::class);
        $guard->assertCompatible($connection, ['protocol_version' => '2.0'], 'booking');
    }

    public function test_v2_offer_cannot_be_booked_through_v3_connection(): void
    {
        $connection = $this->makeConnection(['credentials' => $this->credentials(['protocol_version' => '3.0'])]);

        $this->expectException(AirBlueValidationException::class);
        app(AirBlueProtocolGuard::class)->assertCompatible($connection, ['protocol_version' => '2.0'], 'booking');
    }

    public function test_v3_offer_cannot_be_booked_through_v2_connection(): void
    {
        $connection = $this->makeConnection();

        $this->expectException(AirBlueValidationException::class);
        app(AirBlueProtocolGuard::class)->assertCompatible($connection, ['protocol_version' => '3.0'], 'booking');
    }

    public function test_dual_connection_search_prefers_v3(): void
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

        $this->assertCount(1, $deduped);
        $this->assertSame(2, (int) $deduped->first()->id);
    }

    public function test_v3_seat_selection_gate_blocks_ticketing_without_seats(): void
    {
        $gate = app(AirBlueSeatSelectionGate::class);

        $this->expectException(AirBlueValidationException::class);
        $gate->assertSeatSelectionSatisfied($this->v3Config(), ['ticketing_mode' => 'ticket'], ['seats' => []]);
    }

    public function test_v2_does_not_require_seat_selection_gate(): void
    {
        app(AirBlueSeatSelectionGate::class)->assertSeatSelectionSatisfied(
            ['protocol_version' => '2.0'],
            ['ticketing_mode' => 'ticket'],
            ['seats' => []],
        );

        $this->assertTrue(true);
    }

    public function test_v3_soap_actions_fail_closed_when_not_configured(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        try {
            $method->invoke($client, 'air_seat_map', ['is_test' => true, 'protocol_version' => '3.0']);
            $this->fail('Expected missing_soap_action for unconfigured v3 air_seat_map');
        } catch (AirBlueValidationException $exception) {
            $this->assertSame('missing_soap_action', $exception->normalizedCode);
        }
    }

    public function test_v3_soap_actions_resolve_configured_value_exactly(): void
    {
        $versions = (array) config('suppliers.airblue.protocol_versions');
        $v3 = is_array($versions['3.0'] ?? null) ? $versions['3.0'] : [];
        $operations = is_array($v3['ota_operations'] ?? null) ? $v3['ota_operations'] : [];
        $operations['air_seat_map'] = ['soap_action' => 'https://supplier.example/AirSeatMap'];
        $v3['ota_operations'] = $operations;
        $versions['3.0'] = $v3;
        config(['suppliers.airblue.protocol_versions' => $versions]);

        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        $action = $method->invoke($client, 'air_seat_map', ['is_test' => true, 'protocol_version' => '3.0']);

        $this->assertSame('https://supplier.example/AirSeatMap', $action);
    }

    public function test_v2_ancillary_service_is_not_supported(): void
    {
        $connection = $this->makeConnection();
        $this->assertFalse(app(AirBlueAncillaryService::class)->isSupported($connection));
    }

    public function test_v3_ancillary_service_is_supported(): void
    {
        $connection = $this->makeConnection(['credentials' => $this->credentials(['protocol_version' => '3.0'])]);
        $this->assertTrue(app(AirBlueAncillaryService::class)->isSupported($connection));
    }

    public function test_seat_map_is_v3_only(): void
    {
        $this->expectException(AirBlueValidationException::class);
        (new AirBlueOtaXmlBuilder)->buildAirSeatMapRequest($this->v2Config(), [
            'pnr' => 'ABC123',
            'flight_segments' => [['departure_datetime' => '2026-10-15T08:00:00', 'flight_number' => '401']],
        ]);
    }

    public function test_tls_cert_and_key_paths_are_exposed(): void
    {
        $connection = $this->makeConnection([
            'credentials' => $this->credentials([
                'tls_cert_path' => '/secure/jetpakistan-zapways.crt.pem',
                'tls_key_path' => '/secure/jetpakistan-zapways.key.pem',
            ]),
        ]);

        $config = app(AirBlueConfigResolver::class)->resolveOta($connection);

        $this->assertSame('/secure/jetpakistan-zapways.crt.pem', $config['tls_cert_path']);
        $this->assertSame('/secure/jetpakistan-zapways.key.pem', $config['tls_key_path']);
    }

    public function test_crane_ndc_remains_fail_closed(): void
    {
        $connection = $this->makeConnection([
            'credentials' => array_merge($this->credentials(), ['api_channel' => 'crane_ndc']),
        ]);

        $this->expectException(AirBlueValidationException::class);
        app(AirBlueConfigResolver::class)->resolve($connection);
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

    /**
     * @return array<string, mixed>
     */
    private function v2Config(): array
    {
        return [
            'protocol_version' => AirBlueZapwaysProtocolVersion::V2->value,
            'namespace' => AirBlueZapwaysProtocolVersion::V2->namespaceUri(),
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
}
