<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Tests\TestCase;

class AirBlueOtaXmlParserTest extends AirBlueOfflineTestCase
{
    public function test_parses_successful_search_response(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/ota_air_low_fare_search_success.xml'));
        $this->assertIsString($xml);

        $parsed = (new AirBlueOtaXmlParser)->parse($xml);

        $this->assertNull($parsed['soap_fault']);
        $this->assertSame([], $parsed['errors']);
        $itineraries = $parsed['parsed']['priced_itineraries'];
        $this->assertCount(1, $itineraries);
        $this->assertSame('PA', $itineraries[0]['segments'][0]['marketing_carrier']);
        $this->assertSame(14500.0, $itineraries[0]['total_fare']['total']);
        $breakdowns = $itineraries[0]['fare_breakdowns'];
        $this->assertCount(1, $breakdowns);
        $this->assertSame('ADT', $breakdowns[0]['ptc']);
        $this->assertSame(12000.0, $breakdowns[0]['base']);
        $this->assertSame(2500.0, $breakdowns[0]['taxes']);
        $this->assertSame('YOW', $breakdowns[0]['fare_basis']);
        $this->assertSame('fare_info_attr', $breakdowns[0]['fare_basis_source']);
        $this->assertNotEmpty($itineraries[0]['origin_destination_options_xml']);
        $this->assertStringContainsString('<ota:OriginDestinationOptions>', (string) $itineraries[0]['origin_destination_options_xml']);
        $this->assertStringContainsString('StopQuantity="0"', (string) $itineraries[0]['origin_destination_options_xml']);
        $this->assertNotEmpty($itineraries[0]['air_itinerary_pricing_info_xml']);
        $this->assertStringContainsString('FareBasisCode="YOW"', (string) $itineraries[0]['air_itinerary_pricing_info_xml']);
    }

    public function test_fare_basis_prefers_fare_info_attribute_over_nested_codes(): void
    {
        $xml = <<<'XML'
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <OTA_AirLowFareSearchRS xmlns="http://www.opentravel.org/OTA/2003/05">
      <PricedItineraries>
        <PricedItinerary>
          <AirItinerary>
            <OriginDestinationOptions>
              <OriginDestinationOption>
                <FlightSegment FlightNumber="1" ResBookDesigCode="U" DepartureDateTime="2026-11-01T10:00:00" ArrivalDateTime="2026-11-01T12:00:00">
                  <DepartureAirport LocationCode="ISB"/>
                  <ArrivalAirport LocationCode="KHI"/>
                  <MarketingAirline Code="PA"/>
                </FlightSegment>
              </OriginDestinationOption>
            </OriginDestinationOptions>
          </AirItinerary>
          <AirItineraryPricingInfo>
            <ItinTotalFare><TotalFare Amount="100.00" CurrencyCode="PKR"/></ItinTotalFare>
            <PTC_FareBreakdowns>
              <PTC_FareBreakdown>
                <PassengerTypeQuantity Code="ADT" Quantity="1"/>
                <FareInfo FareBasisCode="UDOC"/>
                <PassengerFare>
                  <BaseFare Amount="80.00" CurrencyCode="PKR"/>
                  <Taxes Amount="20.00" CurrencyCode="PKR"/>
                  <TotalFare Amount="100.00" CurrencyCode="PKR"/>
                  <FareBasisCodes><FareBasisCode>UNEST</FareBasisCode></FareBasisCodes>
                </PassengerFare>
              </PTC_FareBreakdown>
            </PTC_FareBreakdowns>
          </AirItineraryPricingInfo>
        </PricedItinerary>
      </PricedItineraries>
    </OTA_AirLowFareSearchRS>
  </soap:Body>
</soap:Envelope>
XML;

        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $breakdown = $parsed['parsed']['priced_itineraries'][0]['fare_breakdowns'][0];
        $this->assertSame('UDOC', $breakdown['fare_basis']);
        $this->assertSame('fare_info_attr', $breakdown['fare_basis_source']);
    }

    public function test_rejects_empty_xml(): void
    {
        $this->expectException(AirBlueXmlException::class);
        (new AirBlueOtaXmlParser)->parse('');
    }

    public function test_parses_soap_fault(): void
    {
        $xml = <<<'XML'
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <soap:Fault>
      <faultcode>soap:Client</faultcode>
      <faultstring>Authentication failed</faultstring>
    </soap:Fault>
  </soap:Body>
</soap:Envelope>
XML;

        $parsed = (new AirBlueOtaXmlParser)->parse($xml);

        $this->assertNotNull($parsed['soap_fault']);
        $this->assertStringContainsString('Authentication', $parsed['soap_fault']['message']);
    }
}
