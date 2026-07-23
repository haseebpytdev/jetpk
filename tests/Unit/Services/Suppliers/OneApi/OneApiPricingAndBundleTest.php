<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Services\Suppliers\OneApi\Bundles\OneApiBundleParser;
use App\Services\Suppliers\OneApi\Pricing\OneApiAirPriceResponseParser;
use Tests\TestCase;

class OneApiPricingAndBundleTest extends TestCase
{
    public function test_bundle_parser_preserves_vendor_spelling(): void
    {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'));
        $bundles = app(OneApiBundleParser::class)->parseFromPriceXml($xml);
        $this->assertNotEmpty($bundles);
        $this->assertSame('BUNDLE_FIXTURE_001', $bundles[0]['bunldedServiceId']);
        $this->assertSame('BAGGAGE', $bundles[0]['includedServies']);
    }

    public function test_price_parser_reads_total_and_tid(): void
    {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'));
        $soapParsed = app(\App\Services\Suppliers\OneApi\Transport\OneApiXmlParser::class)->parse($xml);
        $price = app(OneApiAirPriceResponseParser::class)->parse($soapParsed);
        $this->assertSame('TID_FIXTURE_001', $price['transaction_identifier']);
        $this->assertSame('250.00', $price['total_fare']['amount']);
        $this->assertSame('AED', $price['total_fare']['currency']);
    }
}
