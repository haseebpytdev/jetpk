<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Tests\TestCase;

class AirBlueOtaXmlParserTest extends TestCase
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
