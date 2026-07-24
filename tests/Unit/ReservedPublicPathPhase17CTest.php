<?php

namespace Tests\Unit;

use App\Support\Client\ReservedPublicPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservedPublicPathPhase17CTest extends TestCase
{
    #[DataProvider('reservedSlugProvider')]
    public function test_reserved_slugs_fail_custom_page_constraint(string $slug): void
    {
        $pattern = '/^'.ReservedPublicPath::customPageSlugConstraint().'$/';
        $this->assertSame(0, preg_match($pattern, $slug));
        $this->assertTrue(ReservedPublicPath::isReservedFirstSegment($slug));
    }

    #[DataProvider('allowedSlugProvider')]
    public function test_non_reserved_slugs_pass_custom_page_constraint(string $slug): void
    {
        $pattern = '/^'.ReservedPublicPath::customPageSlugConstraint().'$/';
        $this->assertSame(1, preg_match($pattern, $slug));
        $this->assertFalse(ReservedPublicPath::isReservedFirstSegment($slug));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedSlugProvider(): array
    {
        $slugs = ['admin', 'login', 'password', 'booking', 'flights', 'dev', 'agent', 'staff'];

        $cases = [];
        foreach ($slugs as $slug) {
            $cases[$slug] = [$slug];
        }

        return $cases;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedSlugProvider(): array
    {
        return [
            'custom-page' => ['corporate-travel'],
            'hyphenated' => ['umrah-packages-2026'],
        ];
    }
}
