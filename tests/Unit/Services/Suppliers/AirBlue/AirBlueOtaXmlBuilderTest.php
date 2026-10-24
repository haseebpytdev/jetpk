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

class AirBlueOtaXmlBuilderTest extends AirBlueOfflineTestCase
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

    public function test_read_current_pnr_omits_type_and_instance(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $xml = $builder->buildReadRequest($this->baseConfig('Test'), 'TESTPNR');

        $this->assertStringContainsString('readRQ', $xml);
        $this->assertStringContainsString('xmlns:zap="http://zapways.com/air/ota/2.0"', $xml);
        $this->assertStringContainsString('<ota:UniqueID ID="TESTPNR"/>', $xml);
        $this->assertStringNotContainsString('Type="14"', $xml);
        $this->assertStringNotContainsString('Instance=', $xml);
        $this->assertStringContainsString('Target="Test"', $xml);
    }

    public function test_read_specific_instance_includes_instance_without_type(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $xml = $builder->buildReadRequest($this->baseConfig('Test'), 'ABC123', 'INST1');

        $this->assertStringContainsString('<ota:UniqueID ID="ABC123" Instance="INST1"/>', $xml);
        $this->assertStringNotContainsString('Type="14"', $xml);
    }

    public function test_air_demand_ticket_and_cancel_builders(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $config = $this->baseConfig('Test');

        $ticketXml = $builder->buildAirDemandTicketRequest($config, 'PNR1', 'INST1', [
            'amount' => 14500.00,
            'currency' => 'PKR',
            'payment_type' => 'Cash',
        ]);
        $cancelXml = $builder->buildCancelRequest($config, 'PNR1', 'INST1');
        $modifyXml = $builder->buildAirBookModifyRequest($config, ['pnr' => 'PNR1', 'instance' => 'INST1']);

        $this->assertStringContainsString('airDemandTicketRQ', $ticketXml);
        $this->assertStringContainsString('cancelRQ', $cancelXml);
        $this->assertStringContainsString('Type="14"', $cancelXml);
        $this->assertStringContainsString('Instance="INST1"', $cancelXml);
        $this->assertStringContainsString('airBookModifyRQ', $modifyXml);
        $this->assertStringContainsString('Version="1.04"', $ticketXml);
    }

    public function test_air_book_reproduces_supplier_ptc_fare_breakdowns(): void
    {
        $providerContext = $this->providerContextFromSearchFixture();
        $builder = new AirBlueOtaXmlBuilder;

        $xml = $builder->buildAirBookRequest(
            $this->baseConfig(),
            $providerContext,
            [['ptc' => 'ADT', 'given_name' => 'JOHN', 'surname' => 'DOE', 'gender' => 'M', 'title' => 'MR']],
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
        // Zapways DocumentTypeGender rejects M/F; wire Male/Female.
        $this->assertStringContainsString('Gender="Male"', $xml);
        $this->assertStringNotContainsString('Gender="M"', $xml);
        // Zapways OTA v2.06 documents NameTitle (not NamePrefix).
        $this->assertStringContainsString('<ota:NameTitle>MR</ota:NameTitle>', $xml);
        $this->assertStringNotContainsString('<ota:NamePrefix>', $xml);
        $this->assertStringContainsString('<ota:OperatingAirline Code="PA"/>', $xml);
        $this->assertStringContainsString('FareBasisCode="YOW"', $xml);
        $this->assertStringContainsString('PhoneLocationType="10"', $xml);
        $this->assertStringContainsString('CountryAccessCode="92"', $xml);
        $this->assertStringContainsString('<ota:PassengerTypeQuantity Code="ADT" Quantity="1"/>', $xml);
        // Exact OriginDestinationOptions / pricing fragments from search preserved.
        $this->assertStringContainsString('<ota:OriginDestinationOptions>', $xml);
        $this->assertStringContainsString('StopQuantity="0"', $xml);
        $this->assertStringContainsString('TaxCode="YR"', $xml);
    }

    public function test_air_book_falls_back_fare_basis_to_segment_rbd(): void
    {
        $providerContext = $this->providerContextFromSearchFixture();
        // Force reconstruct path (no lossless pricing/ODO fragments).
        $providerContext['priced_itineraries'][0]['origin_destination_options_xml'] = null;
        $providerContext['priced_itineraries'][0]['air_itinerary_pricing_info_xml'] = null;
        $providerContext['priced_itineraries'][0]['fare_breakdowns'][0]['fare_basis'] = null;
        $providerContext['priced_itineraries'][0]['segments'][0]['fare_basis'] = '';
        $providerContext['priced_itineraries'][0]['segments'][0]['rbd'] = 'U';

        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            $providerContext,
            [['ptc' => 'ADT', 'given_name' => 'JOHN', 'surname' => 'DOE', 'gender' => 'F']],
            ['email' => 'booker@example.com'],
        );

        $this->assertStringContainsString('<ota:FareBasisCode>U</ota:FareBasisCode>', $xml);
        $this->assertStringContainsString('Gender="Female"', $xml);
    }

    public function test_air_book_uses_documented_name_title_and_telephone(): void
    {
        $providerContext = $this->providerContextFromSearchFixture();
        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            $providerContext,
            [['ptc' => 'ADT', 'given_name' => 'JOHN', 'surname' => 'DOE', 'gender' => 'M', 'title' => 'MR']],
            [
                'email' => 'booker@example.com',
                'phone_country' => '92',
                'phone_number' => '3001234567',
            ],
        );

        $this->assertStringContainsString('<ota:NameTitle>MR</ota:NameTitle>', $xml);
        $this->assertMatchesRegularExpression(
            '/<ota:Telephone[^>]*PhoneLocationType="10"[^>]*CountryAccessCode="92"[^>]*PhoneNumber="3001234567"/',
            $xml,
        );
        $this->assertStringNotContainsString('PassengerTypeCode=', $xml);
    }

    public function test_round_trip_preserved_fragments_use_single_odo_and_price_wrappers(): void
    {
        $providerContext = $this->providerContextFromSearchFixture();
        $outbound = $providerContext['priced_itineraries'][0];
        $inbound = $outbound;

        $inbound['origin_destination_options_xml'] = str_replace(
            ['RPH="0-0"', 'LocationCode="KHI"', 'LocationCode="ISB"', 'FlightNumber="401"'],
            ['RPH="1-0"', 'LocationCode="ISB"', 'LocationCode="KHI"', 'FlightNumber="402"'],
            (string) $outbound['origin_destination_options_xml'],
        );
        $inbound['air_itinerary_pricing_info_xml'] = str_replace(
            ['FareBasisCode="YOW"', '<ota:FareBasisCode>YOW</ota:FareBasisCode>'],
            ['FareBasisCode="YRT"', '<ota:FareBasisCode>YRT</ota:FareBasisCode>'],
            (string) $outbound['air_itinerary_pricing_info_xml'],
        );
        $providerContext['priced_itineraries'] = [$outbound, $inbound];

        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookRequest(
            $this->baseConfig(),
            $providerContext,
            [['ptc' => 'ADT', 'given_name' => 'JOHN', 'surname' => 'DOE', 'gender' => 'M', 'title' => 'MR']],
            ['email' => 'booker@example.com', 'phone_country' => '92', 'phone_number' => '3001234567'],
        );

        $this->assertSame(1, substr_count($xml, '<ota:OriginDestinationOptions>'));
        $this->assertSame(2, substr_count($xml, '<ota:FlightSegment '));
        $this->assertStringContainsString('FlightNumber="401"', $xml);
        $this->assertStringContainsString('FlightNumber="402"', $xml);

        $this->assertSame(1, substr_count($xml, '<ota:PriceInfo>'));
        $this->assertSame(1, substr_count($xml, '<ota:PTC_FareBreakdowns>'));
        $this->assertSame(2, substr_count($xml, '<ota:PTC_FareBreakdown>'));
        $this->assertStringContainsString('FareBasisCode="YOW"', $xml);
        $this->assertStringContainsString('FareBasisCode="YRT"', $xml);
        $this->assertStringNotContainsString('<ota:RuleInfo', $xml);
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
        // Reconstruct pricing for multi-PTC mix (search fixture is ADT-only).
        $providerContext['priced_itineraries'][0]['air_itinerary_pricing_info_xml'] = null;
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

        $this->assertStringContainsString('<ota:PassengerTypeQuantity Code="ADT" Quantity="1"/>', $xml);
        $this->assertStringContainsString('BirthDate="2015-06-01"', $xml);
        $this->assertStringContainsString('<ota:PassengerTypeQuantity Code="CHD" Quantity="1"/>', $xml);
        $this->assertStringContainsString('BirthDate="2024-01-15"', $xml);
        $this->assertStringContainsString('<ota:PassengerTypeQuantity Code="INF" Quantity="1"/>', $xml);
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
                'nationality' => 'PK',
                'document_issuing_country' => 'PK',
                'document_expiry' => '2030-12-31',
            ]],
            [],
        );

        $this->assertStringContainsString('DocID="AB1234567"', $xml);
        $this->assertStringContainsString('DocIssueCountry="PK"', $xml);
        $this->assertStringContainsString('ExpireDate="2030-12-31"', $xml);
        $this->assertStringContainsString('DocHolderNationality="PK"', $xml);
    }

    public function test_air_demand_ticket_requires_payment_info(): void
    {
        $this->expectException(AirBlueValidationException::class);

        (new AirBlueOtaXmlBuilder)->buildAirDemandTicketRequest($this->baseConfig('Test'), 'PNR1', 'INST1');
    }

    public function test_air_demand_ticket_includes_payment_info(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirDemandTicketRequest(
            $this->baseConfig('Test'),
            'PNR1',
            'INST1',
            ['amount' => 14500.00, 'currency' => 'PKR', 'payment_type' => 'Cash'],
        );

        $this->assertStringContainsString('PaymentInfo', $xml);
        $this->assertStringContainsString('Amount="14500.00"', $xml);
        $this->assertStringContainsString('CurrencyCode="PKR"', $xml);
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
