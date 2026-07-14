<?php

namespace Tests\Unit\Support\Geo;

use App\Support\Geo\CountryList;
use Tests\TestCase;

class CountryListTest extends TestCase
{
    public function test_country_list_is_sorted_alphabetically_by_name(): void
    {
        $names = array_column(CountryList::all(), 'name');

        $sorted = $names;
        usort($sorted, static fn (string $a, string $b): int => strcasecmp($a, $b));

        $this->assertSame($sorted, $names);
    }

    public function test_for_select_includes_key_markets(): void
    {
        $codes = array_column(CountryList::forSelect(), 'code');

        foreach (['PK', 'AE', 'GB', 'US', 'SA'] as $code) {
            $this->assertContains($code, $codes, "Expected {$code} in shared country list.");
        }
    }

    public function test_alpha2_validation(): void
    {
        $this->assertTrue(CountryList::isValidAlpha2('pk'));
        $this->assertTrue(CountryList::isValidAlpha2('PK'));
        $this->assertFalse(CountryList::isValidAlpha2('XX'));
        $this->assertSame('PK', CountryList::normalizeAlpha2('pk'));
    }
}
