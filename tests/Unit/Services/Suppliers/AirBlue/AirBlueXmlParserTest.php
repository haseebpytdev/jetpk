<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueXmlParser;
use Tests\TestCase;

class AirBlueXmlParserTest extends TestCase
{
    public function test_parses_air_shopping_fixture(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doAirShopping_OW_res.xml'));
        $parser = new AirBlueXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertNull($parsed['soap_fault']);
        $this->assertNotSame([], $parsed['parsed']['offers']);
    }

    public function test_parses_order_create_fixture_order_id(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml'));
        $parser = new AirBlueXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertSame('7UU0J3', $parsed['parsed']['order']['order_id'] ?? null);
    }
}
