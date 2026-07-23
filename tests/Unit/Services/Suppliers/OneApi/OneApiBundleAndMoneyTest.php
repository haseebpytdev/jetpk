<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Services\Suppliers\OneApi\Bundles\OneApiBundleParser;
use App\Services\Suppliers\OneApi\Bundles\OneApiBundleSelectionBuilder;
use App\Services\Suppliers\OneApi\Money\OneApiMoney;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OneApiBundleAndMoneyTest extends TestCase
{
    #[Test]
    public function money_adds_with_bcmath(): void
    {
        $a = OneApiMoney::fromParts('10.50', 'AED');
        $b = OneApiMoney::fromParts('2.25', 'AED');
        $sum = $a->add($b);
        $this->assertSame('12.75', $sum->amount);
        $this->assertTrue($sum->equals(OneApiMoney::fromParts('12.75', 'AED')));
    }

    #[Test]
    public function bundle_parser_preserves_vendor_misspellings(): void
    {
        $xml = <<<'XML'
<root>
  <bundledService>
    <bunldedServiceId>B1</bunldedServiceId>
    <bundledServiceName>Value</bundledServiceName>
    <includedServies>Bag</includedServies>
  </bundledService>
</root>
XML;
        $parsed = app(OneApiBundleParser::class)->parseFromPriceXml($xml);
        $this->assertSame('B1', $parsed[0]['bunldedServiceId']);
        $this->assertSame('Bag', $parsed[0]['includedServies']);
    }

    #[Test]
    public function bundle_selection_builder_uses_wire_field_names(): void
    {
        $builder = app(OneApiBundleSelectionBuilder::class);
        $rows = $builder->buildSelections([['id' => 'X1', 'name' => 'Flex']]);
        $this->assertSame('X1', $rows[0]['bunldedServiceId']);
        $this->assertArrayHasKey('includedServies', $rows[0]);
    }
}
