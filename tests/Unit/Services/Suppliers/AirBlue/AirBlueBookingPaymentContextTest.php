<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaResponseNormalizer;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBluePassengerPayloadBuilder;
use App\Services\Suppliers\AirBlue\AirBlueProtocolGuard;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AirBlueBookingPaymentContextTest extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_successful_airbook_persists_supplier_payable_not_customer_payable(): void
    {
        $agency = Agency::query()->firstOrFail();
        $actor = User::factory()->create();
        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Payment Context Fixture',
            'environment' => SupplierEnvironment::Sandbox,
            'is_active' => true,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '2.0',
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
            ],
        ]);

        $supplierTotal = 26000.0;
        $customerPayable = 26500.0;

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'selected_fare_total' => $customerPayable,
            'payable_after_promo' => $customerPayable,
            'payable_before_promo' => $customerPayable,
            'meta' => [
                'validated_offer_snapshot' => [
                    'fare_breakdown' => [
                        'base_fare' => 18030,
                        'taxes' => 7970,
                        'supplier_total' => $supplierTotal,
                        'currency' => 'PKR',
                    ],
                ],
                'airblue_context' => [
                    'protocol_version' => '2.0',
                    'api_channel' => 'zapways_ota',
                    'priced_itineraries' => [[
                        'segments' => [['flight_number' => '401', 'rbd' => 'U']],
                        'fare_breakdowns' => [['fare_basis' => 'EFTAOWU', 'total' => $supplierTotal]],
                        'total_fare' => ['base' => 18030, 'taxes' => 7970, 'total' => $supplierTotal, 'currency' => 'PKR'],
                        'origin_destination_options_xml' => '<OriginDestinationOption/>',
                        'air_itinerary_pricing_info_xml' => '<AirItineraryPricingInfo><PTC_FareBreakdown/></AirItineraryPricingInfo>',
                    ]],
                ],
            ],
        ]);

        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andReturn(['raw_xml' => '<AirBookRS/>']);

        $passengers = Mockery::mock(AirBluePassengerPayloadBuilder::class);
        $passengers->shouldReceive('buildPassengersFromBooking')->andReturn([['ptc' => 'ADT']]);
        $passengers->shouldReceive('buildContactFromBooking')->andReturn(['email' => 'fixture@example.test']);

        $configResolver = Mockery::mock(AirBlueConfigResolver::class);
        $configResolver->shouldReceive('resolveOta')->andReturn([
            'endpoint_url' => 'https://example.test/ota',
            'protocol_version' => '2.0',
            'currency' => 'PKR',
            'is_test' => true,
        ]);

        $xmlBuilder = Mockery::mock(AirBlueOtaXmlBuilder::class);
        $xmlBuilder->shouldReceive('buildAirBookRequest')->andReturn('<AirBookRQ/>');

        $normalizer = Mockery::mock(AirBlueOtaResponseNormalizer::class);
        $normalizer->shouldReceive('normalizeBookingResponse')->once()->andReturn([
            'pnr' => 'TESTPNR1',
            'provider_booking_reference' => 'TESTPNR1',
            'provider_context' => [
                'pnr' => 'TESTPNR1',
                'instance' => 'INST-1',
                'protocol_version' => '2.0',
                'api_channel' => 'zapways_ota',
            ],
        ]);

        $protocolGuard = Mockery::mock(AirBlueProtocolGuard::class);
        $protocolGuard->shouldReceive('assertCompatible')->once();

        $service = new AirBlueBookingService(
            $client,
            $configResolver,
            $passengers,
            $xmlBuilder,
            $normalizer,
            $protocolGuard,
            app(SupplierDiagnosticLogger::class),
        );

        $result = $service->createSupplierBooking($booking, $connection, $actor);

        $this->assertTrue($result->success);
        $ctx = is_array($result->safe_summary['airblue_context'] ?? null)
            ? $result->safe_summary['airblue_context']
            : [];

        $this->assertSame(26000.0, (float) ($ctx['supplier_payable_amount'] ?? 0));
        $this->assertNotSame(26500.0, (float) ($ctx['supplier_payable_amount'] ?? 0));
        $this->assertSame('PKR', $ctx['payment_currency'] ?? null);
        $this->assertSame('Cash', $ctx['payment_type'] ?? null);

        $payloadCtx = is_array($result->response_payload['provider_context'] ?? null)
            ? $result->response_payload['provider_context']
            : [];
        $this->assertSame(26000.0, (float) ($payloadCtx['supplier_payable_amount'] ?? 0));

        $ticketXml = (new AirBlueOtaXmlBuilder)->buildAirDemandTicketRequest(
            [
                'client_id' => 'CLIENT',
                'client_key' => 'KEY',
                'agent_type' => '5',
                'agent_id' => 'AGENT',
                'agent_password' => 'secret',
                'service_target' => 'Test',
                'service_version' => '1.04',
                'currency' => 'PKR',
            ],
            'TESTPNR1',
            'INST-1',
            [
                'amount' => $ctx['supplier_payable_amount'],
                'currency' => $ctx['payment_currency'],
                'payment_type' => $ctx['payment_type'],
            ],
        );

        $this->assertStringContainsString('Amount="26000.00"', $ticketXml);
        $this->assertStringNotContainsString('Amount="26500.00"', $ticketXml);
    }

    public function test_missing_supplier_total_does_not_invent_customer_payable(): void
    {
        $agency = Agency::query()->firstOrFail();
        $actor = User::factory()->create();
        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Payment Context Missing Fare',
            'environment' => SupplierEnvironment::Sandbox,
            'is_active' => true,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '2.0',
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
            ],
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'selected_fare_total' => 26500,
            'payable_after_promo' => 26500,
            'meta' => [
                'airblue_context' => [
                    'protocol_version' => '2.0',
                    'priced_itineraries' => [['segments' => [['flight_number' => '401']]]],
                ],
            ],
        ]);

        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andReturn(['raw_xml' => '<AirBookRS/>']);

        $passengers = Mockery::mock(AirBluePassengerPayloadBuilder::class);
        $passengers->shouldReceive('buildPassengersFromBooking')->andReturn([['ptc' => 'ADT']]);
        $passengers->shouldReceive('buildContactFromBooking')->andReturn(['email' => 'fixture@example.test']);

        $configResolver = Mockery::mock(AirBlueConfigResolver::class);
        $configResolver->shouldReceive('resolveOta')->andReturn([
            'endpoint_url' => 'https://example.test/ota',
            'protocol_version' => '2.0',
            'currency' => 'PKR',
            'is_test' => true,
        ]);

        $xmlBuilder = Mockery::mock(AirBlueOtaXmlBuilder::class);
        $xmlBuilder->shouldReceive('buildAirBookRequest')->andReturn('<AirBookRQ/>');

        $normalizer = Mockery::mock(AirBlueOtaResponseNormalizer::class);
        $normalizer->shouldReceive('normalizeBookingResponse')->once()->andReturn([
            'pnr' => 'TESTPNR2',
            'provider_booking_reference' => 'TESTPNR2',
            'provider_context' => [
                'pnr' => 'TESTPNR2',
                'instance' => 'INST-2',
            ],
        ]);

        $protocolGuard = Mockery::mock(AirBlueProtocolGuard::class);
        $protocolGuard->shouldReceive('assertCompatible')->once();

        $service = new AirBlueBookingService(
            $client,
            $configResolver,
            $passengers,
            $xmlBuilder,
            $normalizer,
            $protocolGuard,
            app(SupplierDiagnosticLogger::class),
        );

        $result = $service->createSupplierBooking($booking, $connection, $actor);
        $this->assertTrue($result->success);
        $ctx = is_array($result->safe_summary['airblue_context'] ?? null)
            ? $result->safe_summary['airblue_context']
            : [];

        $this->assertArrayNotHasKey('supplier_payable_amount', $ctx);
        $this->assertArrayNotHasKey('payment_amount', $ctx);
    }
}
