<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\FlightSearchRequestData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueOfferPriceService;
use App\Services\Suppliers\AirBlue\AirBlueOtaResponseNormalizer;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use ReflectionClass;

/**
 * Offline contract closure for offer/UI/provider-gap classifications.
 */
class AirBlueContractClosureTest extends AirBlueOfflineTestCase
{
    public function test_search_baggage_absent_stays_null_not_invented(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/ota_air_low_fare_search_success.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
        ]);
        $connection->id = 22;

        $offers = (new AirBlueOtaResponseNormalizer)->normalizeSearchResponse($parsed, $connection, 'corr-bag');
        $this->assertNotEmpty($offers);
        $this->assertNull($offers[0]->baggage->checked);
        $this->assertNull($offers[0]->baggage->cabin);
        $this->assertNotSame('20 KG', $offers[0]->baggage->checked);
        $this->assertNotSame('7 KG', $offers[0]->baggage->cabin);
        $this->assertNotSame('0 KG', $offers[0]->baggage->checked);
    }

    public function test_local_offer_snapshot_fields_are_server_side(): void
    {
        $snapshot = [
            'offer_id' => 'airblue-offer-1',
            'supplier_connection_id' => 22,
            'currency' => 'PKR',
            'fare_total' => 14500.0,
            'booking_class' => 'Y',
            'protocol_version' => '2.0',
            'segments' => [[
                'origin' => 'KHI',
                'destination' => 'ISB',
                'flight_number' => 'PA401',
            ]],
            'passengers' => ['adults' => 1, 'children' => 0, 'infants' => 0],
        ];

        $clientTamper = array_merge($snapshot, [
            'supplier_connection_id' => 999,
            'fare_total' => 1.0,
            'currency' => 'USD',
            'booking_class' => 'Z',
            'protocol_version' => '9.9',
            'segments' => [['origin' => 'LHR', 'destination' => 'JFK']],
            'passengers' => ['adults' => 9],
        ]);

        foreach (['supplier_connection_id', 'fare_total', 'currency', 'booking_class', 'protocol_version', 'segments', 'passengers'] as $key) {
            $this->assertNotEquals($clientTamper[$key], $snapshot[$key]);
            $this->assertArrayHasKey($key, $snapshot);
        }
    }

    public function test_provider_revalidation_is_not_supported_by_current_contract(): void
    {
        $service = app(AirBlueOfferPriceService::class);
        $offer = new NormalizedFlightOfferData(
            offer_id: 'airblue-offer-1',
            supplier_provider: SupplierProvider::Airblue->value,
            supplier_connection_id: 22,
            airline_code: 'PA',
            airline_name: 'AirBlue',
            flight_number: 'PA401',
            origin: 'KHI',
            destination: 'ISB',
            departure_at: '2026-11-01T08:00:00Z',
            arrival_at: '2026-11-01T10:00:00Z',
            duration_minutes: 120,
            stops: 0,
            cabin: 'economy',
            fare_family: null,
            refundable: false,
            seats_left: null,
            segments: [[
                'origin' => 'KHI',
                'destination' => 'ISB',
                'airline_code' => 'PA',
            ]],
            baggage: new BaggageAllowanceData(checked: null, cabin: null, summary: null),
            fare_breakdown: new FareBreakdownData(
                base_fare: 12000.0,
                taxes: 2500.0,
                supplier_fees: 0.0,
                supplier_total: 14500.0,
                currency: 'PKR',
            ),
            raw_payload: ['provider_context' => ['api_channel' => 'zapways_ota']],
        );
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => ['api_channel' => 'zapways_ota', 'protocol_version' => '2.0'],
        ]);
        $connection->id = 22;

        $result = $service->revalidate($offer, $connection);
        $this->assertFalse((bool) ($result->meta['offer_price_supported'] ?? true));
    }

    public function test_external_calls_config_default_is_false(): void
    {
        $this->assertFalse((bool) config('suppliers.airblue.external_calls_enabled', true));
    }

    public function test_return_and_segmented_search_contracts_exist_in_builder(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $config = [
            'protocol_version' => '2.0',
            'namespace' => 'http://zapways.com/air/ota/2.0',
            'client_id' => 'CLIENT',
            'client_key' => 'KEY',
            'agent_type' => '5',
            'agent_id' => 'AGENT',
            'agent_password' => 'secret',
            'service_target' => 'Test',
            'service_version' => '1.04',
            'currency' => 'PKR',
            'is_test' => true,
        ];
        $ow = $builder->buildAirLowFareSearchRequest(new FlightSearchRequestData(
            origin: 'KHI',
            destination: 'ISB',
            departure_date: '2026-11-01',
            adults: 1,
        ), $config);
        $rt = $builder->buildAirLowFareSearchRequest(new FlightSearchRequestData(
            origin: 'KHI',
            destination: 'ISB',
            departure_date: '2026-11-01',
            return_date: '2026-11-10',
            trip_type: 'return',
            adults: 1,
        ), $config);

        $this->assertStringContainsString('OriginDestinationInformation', $ow);
        // Opening + closing tags for two OD segments on return.
        $this->assertSame(4, substr_count($rt, 'OriginDestinationInformation'));
        $this->assertGreaterThan(substr_count($ow, 'OriginDestinationInformation'), substr_count($rt, 'OriginDestinationInformation'));
    }

    public function test_ui_baggage_component_uses_not_provided_copy(): void
    {
        $path = base_path('frontend/features/flight-details/components/BaggageDetails.tsx');
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('Not provided by airline', $src);
        $this->assertStringNotContainsString('20 KG', $src);
        $this->assertStringNotContainsString('7 KG', $src);
    }

    public function test_ticketing_requires_feature_flag_default_false(): void
    {
        $reflection = new ReflectionClass(AirBlueTicketingService::class);
        $method = $reflection->getMethod('isTicketingFeatureEnabled');
        $method->setAccessible(true);
        $service = app(AirBlueTicketingService::class);
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'credentials' => [],
        ]);
        $this->assertFalse($method->invoke($service, $connection));

        $connection->credentials = ['ticketing_enabled' => true];
        $this->assertTrue($method->invoke($service, $connection));
    }
}
