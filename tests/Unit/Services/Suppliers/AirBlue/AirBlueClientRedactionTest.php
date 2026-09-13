<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueClient;
use ReflectionClass;
use Tests\TestCase;

class AirBlueClientRedactionTest extends TestCase
{
    public function test_sanitize_xml_redacts_zapways_secrets_and_pii(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('sanitizeXml');
        $method->setAccessible(true);

        $xml = <<<'XML'
<ota:POS>
  <ota:Source ERSP_UserID="CLIENT/SECRETKEY">
    <ota:RequestorID MessagePassword="agent-pass"/>
  </ota:Source>
</ota:POS>
<ota:GivenName>JOHN</ota:GivenName>
<ota:Email>john@example.com</ota:Email>
XML;

        $redacted = $method->invoke($client, $xml);

        $this->assertStringNotContainsString('SECRETKEY', $redacted);
        $this->assertStringNotContainsString('agent-pass', $redacted);
        $this->assertStringNotContainsString('john@example.com', $redacted);
        $this->assertStringNotContainsString('JOHN', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }
}
