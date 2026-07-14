<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DisplayTextHelperTest extends TestCase
{
    public function test_clean_display_text_strips_replacement_character(): void
    {
        $this->assertSame('Hello', clean_display_text("Hello\u{FFFD}"));
    }

    public function test_clean_display_text_returns_fallback_for_blank(): void
    {
        $this->assertSame('--', clean_display_text(''));
        $this->assertSame('--', clean_display_text(null));
        $this->assertSame('n/a', clean_display_text('   ', 'n/a'));
    }

    public function test_display_unknown_uses_fallback(): void
    {
        $this->assertSame('--', display_unknown(null));
        $this->assertSame('--', display_unknown(''));
        $this->assertSame('DXB', display_unknown('DXB'));
    }

    public function test_display_separators(): void
    {
        $this->assertSame(' · ', display_sep_dot());
        $this->assertSame(' - ', display_sep_dash());
    }
}
