<?php

namespace Tests\Unit\Support\Travel;

use App\Support\Travel\TravelDocumentFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TravelDocumentFormatterTest extends TestCase
{
    public function test_mask_person_name_masks_parts_and_never_throws_on_null(): void
    {
        $this->assertSame('Mr H***** K***', TravelDocumentFormatter::maskPersonName('Mr', 'Haseeb', 'Khan'));
        $this->assertSame('', TravelDocumentFormatter::maskPersonName(null, null, null));
        $this->assertSame('Mr', TravelDocumentFormatter::maskPersonName('Mr', null, null));
        $this->assertSame('A**', TravelDocumentFormatter::maskPersonName(null, 'Ali', null));
    }

    public function test_mask_email_and_phone_are_conservative_and_null_safe(): void
    {
        $this->assertSame('g*********@example.test', TravelDocumentFormatter::maskEmail('guestmatch@example.test'));
        $this->assertNull(TravelDocumentFormatter::maskEmail(null));
        $this->assertNull(TravelDocumentFormatter::maskEmail(''));

        $this->assertSame('0300****567', TravelDocumentFormatter::maskPhone('03001234567'));
        $this->assertNull(TravelDocumentFormatter::maskPhone(null));
        $this->assertSame('***', TravelDocumentFormatter::maskPhone('---'));
    }

    public function test_mask_passport_masks_document_numbers(): void
    {
        $this->assertSame('AB123•••', TravelDocumentFormatter::maskPassport('AB1234567'));
        $this->assertNull(TravelDocumentFormatter::maskPassport(null));
    }

    #[DataProvider('maskPersonNameCallableProvider')]
    public function test_mask_person_name_is_callable_without_undefined_method_error(
        ?string $title,
        ?string $firstName,
        ?string $lastName,
    ): void {
        $result = TravelDocumentFormatter::maskPersonName($title, $firstName, $lastName);

        $this->assertIsString($result);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string}>
     */
    public static function maskPersonNameCallableProvider(): array
    {
        return [
            'full name' => ['Mr', 'Haseeb', 'Khan'],
            'all null' => [null, null, null],
            'empty strings' => ['', '', ''],
        ];
    }
}
