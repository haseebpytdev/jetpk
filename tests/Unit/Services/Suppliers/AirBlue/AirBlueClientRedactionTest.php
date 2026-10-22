<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueClient;
use ReflectionClass;
use Tests\TestCase;

class AirBlueClientRedactionTest extends AirBlueOfflineTestCase
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

    public function test_sanitize_xml_redacts_birthdate_and_document_attributes(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('sanitizeXml');
        $method->setAccessible(true);

        $xml = '<ota:AirTraveler BirthDate="2015-06-01"><ota:Document DocID="AB1234567"/></ota:AirTraveler>';

        $redacted = $method->invoke($client, $xml);

        $this->assertStringNotContainsString('2015-06-01', $redacted);
        $this->assertStringNotContainsString('AB1234567', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    public function test_summarize_soap_fault_classifies_gender_and_nre(): void
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('summarizeSoapFault');
        $method->setAccessible(true);

        $gender = $method->invoke($client, '<soap:Fault><faultstring>Instance validation error: \'M\' is not a valid value for DocumentTypeGender.</faultstring></soap:Fault>');
        $this->assertSame('soap_client_schema_gender', $gender['fault_class']);
        $this->assertStringContainsString('DocumentTypeGender', (string) $gender['fault_snippet']);

        $nre = $method->invoke($client, '<soap:Fault><faultstring>Server was unable to process request. ---&gt; Object reference not set to an instance of an object.</faultstring></soap:Fault>');
        $this->assertSame('provider_nre', $nre['fault_class']);
        $this->assertStringContainsString('Object reference', (string) $nre['fault_snippet']);
    }
}
