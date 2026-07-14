<?php

namespace Tests\Unit\Services\Branding;

use App\Services\Branding\LogoPaletteExtractor;
use Tests\TestCase;

class LogoPaletteExtractorTest extends TestCase
{
    public function test_extracts_colors_from_svg_logo(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg"><rect fill="#EA7A1E" width="10" height="10"/><circle stroke="#0BA5AD" fill="#16A34A"/></svg>
SVG;
        $path = storage_path('framework/testing-logo.svg');
        file_put_contents($path, $svg);

        $palette = app(LogoPaletteExtractor::class)->extractFromPath($path);

        $this->assertNotEmpty($palette['primary']);
        $this->assertStringStartsWith('#', $palette['primary']);
        $this->assertIsArray($palette['swatches']);
        @unlink($path);
    }

    public function test_fallback_palette_when_file_missing(): void
    {
        $palette = app(LogoPaletteExtractor::class)->extractFromPath('/nonexistent/logo.svg');
        $this->assertSame('#EA7A1E', $palette['primary']);
        $this->assertNotEmpty($palette['contrast_warnings']);
    }
}
