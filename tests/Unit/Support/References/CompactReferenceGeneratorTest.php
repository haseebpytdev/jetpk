<?php

namespace Tests\Unit\Support\References;

use App\Models\Booking;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class CompactReferenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private CompactReferenceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new CompactReferenceGenerator;
    }

    public function test_generate_uses_allowed_alphabet_only(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $ref = $this->generator->generate(12);
            $this->assertSame(strlen($ref), 12);
            $this->assertMatchesRegularExpression('/^[A-Z2-9]+$/', $ref);
            $this->assertStringNotContainsString('I', $ref);
            $this->assertStringNotContainsString('O', $ref);
            $this->assertStringNotContainsString('0', $ref);
            $this->assertStringNotContainsString('1', $ref);
        }
    }

    public function test_generate_with_starts_with_prefix(): void
    {
        $ref = $this->generator->generate(8, 'P');

        $this->assertSame('P', $ref[0]);
        $this->assertSame(8, strlen($ref));
    }

    public function test_generate_rejects_invalid_starts_with(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator->generate(8, '0');
    }

    public function test_generate_unique_returns_unused_reference(): void
    {
        $ref = $this->generator->generateUnique('bookings', 'booking_reference', 8);

        $this->assertTrue($this->generator->matchesCompactFormat($ref, 8));
    }

    public function test_generate_unique_rejects_disallowed_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator->generateUnique('users', 'email', 8);
    }

    public function test_matches_compact_format(): void
    {
        $this->assertTrue($this->generator->matchesCompactFormat('GXJDHD8K', 8));
        $this->assertFalse($this->generator->matchesCompactFormat('OTA-1234', 8));
    }

    public function test_generate_unique_throws_after_exhausted_attempts(): void
    {
        Booking::factory()->create(['booking_reference' => 'AAAAAAAA']);

        $generator = new class extends CompactReferenceGenerator
        {
            public function generate(int $length = 8, ?string $startsWith = null): string
            {
                return 'AAAAAAAA';
            }
        };

        $this->expectException(RuntimeException::class);
        $generator->generateUnique('bookings', 'booking_reference', 8);
    }
}
