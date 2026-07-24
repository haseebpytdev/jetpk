<?php

namespace Tests\Unit\Support\Client\Homepage;

use App\Support\Client\Homepage\HomepageSectionOrderResolver;
use PHPUnit\Framework\TestCase;

class HomepageSectionOrderResolverTest extends TestCase
{
    public function test_custom_order_values_reorder_sections(): void
    {
        $ordered = (new HomepageSectionOrderResolver)->orderedSections([
            'support_cta' => ['order' => 1],
            'why_book' => ['order' => 20],
        ]);

        $keys = array_column($ordered, 'key');
        $this->assertSame('support_cta', $keys[0]);
        $this->assertSame('why_book', $keys[array_key_last($keys)]);
    }
}
