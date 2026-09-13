<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\AirBlue\AirBlueOtaResponseNormalizer;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Tests\TestCase;

class AirBlueOtaXmlBuilderTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function baseConfig(string $target = 'Production'): array
    {
        return [
            'client_id' => 'CLIENT',
            'client_key' => 'KEY',
            'agent_type' => '5',
            'agent_id' => 'AGENT',
            'agent_password' => 'secret',
            'service_target' => $target,
            'service_version' => '1.04',
        ];
    }

    public function test_air_low_fare_search_contains_pos_and_route(): void
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

        $xml = $builder->buildAirLowFareSearchRequest($request, $this->baseConfig());

        $this->assertStringContainsString('AirLowFareSearch', $xml);
        $this->assertStringContainsString('ERSP_UserID="CLIENT/KEY"', $xml);
        $this->assertStringContainsString('LocationCode="KHI"', $xml);
        $this->assertStringContainsString('LocationCode="ISB"', $xml);
        $this->assertStringContainsString('Version="1.04"', $xml);
        $this->assertStringContainsString('Target="Production"', $xml);
    }

    public function test_read_request_contains_pnr_and_instance(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $xml = $builder->buildReadRequest($this->baseConfig('Test'), 'ABC123', 'INST1');

        $this->assertStringContainsString('OTA_ReadRQ', $xml);
        $this->assertStringContainsString('ID="ABC123"', $xml);
        $this->assertStringContainsString('Instance="INST1"', $xml);
        $this->assertStringContainsString('Target="Test"', $xml);
    }

    public function test_air_demand_ticket_and_cancel_builders(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $config = $this->baseConfig('Test');

        $ticketXml = $builder->buildAirDemandTicketRequest($config, 'PNR1', 'INST1');
        $cancelXml = $builder->buildCancelRequest($config, 'PNR1', 'INST1');
        $modifyXml = $builder->buildAirBookModifyRequest($config, ['pnr' => 'PNR1', 'instance' => 'INST1']);

        $this->assertStringContainsString('OTA_AirDemandTicketRQ', $ticketXml);
        $this->assertStringContainsString('OTA_CancelRQ', $cancelXml);
        $this->assertStringContainsString('OTA_AirBookModifyRQ', $modifyXml);
        $this->assertStringContainsString('Version="1.04"', $ticketXml);
    }

    public function test_air_book_reproduces_supplier_ptc_fare_breakdowns(): void
    {
        $providerContext = $this->providerContextFromSearchFixture();
        $builder = new AirBlueOtaXmlBuilder;

        $xml = $builder->buildAirBookRequest(
            $this->baseConfig(),
            $providerContext,
            [['ptc' => 'ADT', 'given_name' => 'JOHN', 'surname' => 'DOE', 'gender' => 'M']],
            ['email' => 'booker@example.com', 'phone_number' => '3001234567'],
        );

        $this->assertStringContainsString('<ota:PriceInfo>', $xml);
        $this->assertStringContainsString('<ota:PTC_FareBreakdown>', $xml);
        $this->assertStringContainsString('Code="ADT"', $xml);
        $this->assertStringContainsString('Amount="12000.00"', $xml);
        $this->assertStringContainsString('Amount="2500.00"', $xml);
        $this->assertStringContainsString('Amount="14500.00"', $xml);
        $this->assertStringContainsString('YOW', $xml);
        $this->assertStringContainsString('ResBookDesigCode="Y"', $xml);
    }

    public function test_search_to_air_book_preserves_fare_fields(): void
    {
        $providerContext = $this->providerContextFromSearchFixture();
        $breakdown = $providerContext['priced_itineraries'][0]['fare_breakdowns'][0];

        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            $providerContext,
            [['ptc' => 'ADT', 'given_name' => 'JOHN', 'surname' => 'DOE', 'gender' => 'M']],
            ['email' => 'booker@example.com'],
        );

        $this->assertStringContainsString('Amount="'.number_format((float) $breakdown['base'], 2, '.', '').'"', $xml);
        $this->assertStringContainsString('Amount="'.number_format((float) $breakdown['taxes'], 2, '.', '').'"', $xml);
        $this->assertStringContainsString('Amount="'.number_format((float) $breakdown['total'], 2, '.', '').'"', $xml);
    }

    public function test_air_book_requires_fare_breakdowns(): void
    {
        $this->expectException(AirBlueValidationException::class);

        (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            ['priced_itineraries' => [['segments' => [['flight_number' => '401']], 'total_fare' => ['total' => 1]]]],
            [['ptc' => 'ADT', 'given_name' => 'JOHN', 'surname' => 'DOE', 'gender' => 'M']],
            [],
        );
    }

    public function test_passenger_mix_adt_chd_inf_includes_birthdates(): void
    {
        $providerContext = $this->providerContextFromSearchFixture();
        $providerContext['priced_itineraries'][0]['fare_breakdowns'] = [
            ['ptc' => 'ADT', 'quantity' => 1, 'base' => 10000, 'taxes' => 2000, 'total' => 12000, 'currency' => 'PKR'],
            ['ptc' => 'CHD', 'quantity' => 1, 'base' => 8000, 'taxes' => 1500, 'total' => 9500, 'currency' => 'PKR'],
            ['ptc' => 'INF', 'quantity' => 1, 'base' => 1000, 'taxes' => 200, 'total' => 1200, 'currency' => 'PKR'],
        ];

        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            $providerContext,
            [
                ['ptc' => 'ADT', 'given_name' => 'JOHN', 'surname' => 'DOE', 'gender' => 'M'],
                ['ptc' => 'CHD', 'given_name' => 'JANE', 'surname' => 'DOE', 'gender' => 'F', 'birthdate' => '2015-06-01'],
                ['ptc' => 'INF', 'given_name' => 'BABY', 'surname' => 'DOE', 'gender' => 'M', 'birthdate' => '2024-01-15'],
            ],
            ['email' => 'booker@example.com'],
        );

        $this->assertStringContainsString('PassengerTypeCode="ADT"', $xml);
        $this->assertStringContainsString('PassengerTypeCode="CHD" BirthDate="2015-06-01"', $xml);
        $this->assertStringContainsString('PassengerTypeCode="INF" BirthDate="2024-01-15"', $xml);
        $this->assertSame(3, substr_count($xml, '<ota:PTC_FareBreakdown>'));
    }

    public function test_chd_without_birthdate_fails_closed(): void
    {
        $this->expectException(AirBlueValidationException::class);

        (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            $this->providerContextFromSearchFixture(),
            [['ptc' => 'CHD', 'given_name' => 'JANE', 'surname' => 'DOE', 'gender' => 'F']],
            [],
        );
    }

    public function test_inf_without_birthdate_fails_closed(): void
    {
        $this->expectException(AirBlueValidationException::class);

        (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            $this->providerContextFromSearchFixture(),
            [['ptc' => 'INF', 'given_name' => 'BABY', 'surname' => 'DOE', 'gender' => 'M']],
            [],
        );
    }

    public function test_passenger_document_fields_are_included_when_present(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            $this->providerContextFromSearchFixture(),
            [[
                'ptc' => 'ADT',
                'given_name' => 'JOHN',
                'surname' => 'DOE',
                'gender' => 'M',
                'document_number' => 'AB1234567',
                'document_issuing_country' => 'PK',
                'document_expiry' => '2030-12-31',
            ]],
            [],
        );

        $this->assertStringContainsString('DocID="AB1234567"', $xml);
        $this->assertStringContainsString('DocIssueCountry="PK"', $xml);
        $this->assertStringContainsString('ExpireDate="2030-12-31"', $xml);
    }

    /**
     * @return array<string, mixed>
     */
    private function providerContextFromSearchFixture(): array
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/ota_air_low_fare_search_success.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
        ]);
        $connection->id = 1;
        $offers = (new AirBlueOtaResponseNormalizer)->normalizeSearchResponse($parsed, $connection, 'corr-1');

        return $offers[0]->raw_payload['provider_context'];
    }
}
