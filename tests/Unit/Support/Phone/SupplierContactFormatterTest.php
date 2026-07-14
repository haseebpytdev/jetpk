<?php

namespace Tests\Unit\Support\Phone;

use App\Support\Phone\SupplierContactFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierContactFormatterTest extends TestCase
{
    #[Test]
    public function test_pakistan_duplicate_country_input_normalizes_to_clean_supplier_shapes(): void
    {
        $formatted = SupplierContactFormatter::format('+92356789876', '92', '0');

        $this->assertSame('92', $formatted['country_code']);
        $this->assertSame('356789876', $formatted['national_number']);
        $this->assertSame('+92356789876', $formatted['e164']);
        $this->assertSame('CTCM 92356789876', $formatted['ctcm_text']);
        $this->assertSame('CTCB 92356789876', $formatted['ctcb_text']);
        $this->assertSame('+92356789876', $formatted['contact_person_phone']);
        $this->assertStringNotContainsString('92 0', $formatted['ctcm_text']);
        $this->assertStringNotContainsString('92 0', $formatted['ctcb_text']);
        $this->assertStringNotContainsString('+92 0 +92', $formatted['contact_person_phone']);
        $this->assertTrue($formatted['valid']);
    }

    #[Test]
    #[DataProvider('pakistanPhoneProvider')]
    public function test_pakistan_phone_variants(string $input, ?string $country, ?string $area): void
    {
        $formatted = SupplierContactFormatter::format($input, $country, $area);

        $this->assertSame('92', $formatted['country_code']);
        $this->assertSame('3211234567', $formatted['national_number']);
        $this->assertSame('+923211234567', $formatted['e164']);
        $this->assertSame('CTCM 923211234567', $formatted['ctcm_text']);
        $this->assertSame('CTCB 923211234567', $formatted['ctcb_text']);
        $this->assertSame('', $formatted['phone_area']);
        $this->assertStringNotContainsString('92 0', $formatted['ctcm_text']);
        $this->assertStringNotContainsString('92 0', $formatted['ctcb_text']);
    }

    /**
     * @return list<array{string, ?string, ?string}>
     */
    public static function pakistanPhoneProvider(): array
    {
        return [
            ['03211234567', null, null],
            ['+923211234567', null, null],
            ['00923211234567', null, null],
            ['92 3211234567', null, null],
            ['+92 03211234567', null, null],
            ['+923211234567', '+92', '0'],
        ];
    }

    #[Test]
    public function test_legacy_bad_previews_show_concatenation_bug(): void
    {
        $legacy = SupplierContactFormatter::legacyBadPreviews('+92356789876', '92', '0');

        $this->assertSame('+92 0 +92356789876', $legacy['contact_person']);
        $this->assertSame('92 0 92356789876', $legacy['ctcm_ssr']);
        $this->assertSame('92 0 +92356789876', $legacy['ctcb_osi']);
    }

    #[Test]
    public function test_xml_contact_shape_has_no_plus_in_national_number(): void
    {
        $formatted = SupplierContactFormatter::format('+92356789876', '+92', '0');
        $xml = SupplierContactFormatter::toXmlContact($formatted);

        $this->assertSame('92', $xml['phone_country']);
        $this->assertSame('', $xml['phone_area']);
        $this->assertSame('356789876', $xml['phone_number']);
        $this->assertFalse(str_contains($xml['phone_number'], '+'));
    }
}
