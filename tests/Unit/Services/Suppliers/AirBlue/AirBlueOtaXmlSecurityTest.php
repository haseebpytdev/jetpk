<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;

class AirBlueOtaXmlSecurityTest extends AirBlueOfflineTestCase
{
    public function test_rejects_oversized_xml(): void
    {
        $this->expectException(AirBlueXmlException::class);
        $this->expectExceptionMessage('oversized');

        (new AirBlueOtaXmlParser)->parse(str_repeat('a', 5_000_001));
    }

    public function test_rejects_malformed_xml(): void
    {
        $this->expectException(AirBlueXmlException::class);
        (new AirBlueOtaXmlParser)->parse('<not-closed>');
    }

    public function test_xxe_entity_does_not_expand_file_contents(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <Response>&xxe;</Response>
  </soap:Body>
</soap:Envelope>
XML;

        // Must not throw a fatal and must not leak file contents into parsed output.
        try {
            $parsed = (new AirBlueOtaXmlParser)->parse($xml);
            $encoded = json_encode($parsed);
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('root:', $encoded);
        } catch (AirBlueXmlException $exception) {
            $this->assertTrue(true);
        }
    }
}
