<?php

namespace Tests\Unit\Support\Phone;

use App\Support\Phone\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    #[Test]
    #[DataProvider('pakistanSupplierDialingProvider')]
    public function test_pakistan_supplier_dialing_shapes(string $input, ?string $countryCode, string $expectedE164): void
    {
        $parts = PhoneNumberNormalizer::splitForSupplierDialing($input, $countryCode);

        $this->assertSame('92', $parts['phone_country']);
        $this->assertSame('', $parts['phone_area']);
        $this->assertSame('3211234567', $parts['phone_number']);
        $this->assertSame($expectedE164, $parts['e164']);
        $this->assertTrue($parts['valid']);
        $this->assertTrue(PhoneNumberNormalizer::isSupplierDialingShapeValid($parts));
    }

    /**
     * @return list<array{string, ?string, string}>
     */
    public static function pakistanSupplierDialingProvider(): array
    {
        return [
            ['03211234567', null, '+923211234567'],
            ['+923211234567', null, '+923211234567'],
            ['00923211234567', null, '+923211234567'],
            ['923211234567', null, '+923211234567'],
            ['+9203211234567', null, '+923211234567'],
            ['03211234567', '+92', '+923211234567'],
            ['+923211234567', '+92', '+923211234567'],
            ['+92 03211234567', null, '+923211234567'],
            ['92 3211234567', null, '+923211234567'],
        ];
    }

    #[Test]
    public function test_rejects_double_country_in_supplier_number_field(): void
    {
        $bad = [
            'phone_country' => '92',
            'phone_area' => '',
            'phone_number' => '+923211234567',
        ];

        $this->assertFalse(PhoneNumberNormalizer::isSupplierDialingShapeValid($bad));
    }

    #[Test]
    public function test_country_hint_with_national_digits_prefixed_by_country_strips_duplicate(): void
    {
        $parts = PhoneNumberNormalizer::splitForSupplierDialing('923211234567', '+92');

        $this->assertSame('92', $parts['phone_country']);
        $this->assertSame('3211234567', $parts['phone_number']);
        $this->assertSame('+923211234567', $parts['e164']);
    }
}
