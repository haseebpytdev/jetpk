<?php

namespace Tests\Unit\Support\Client\Homepage;

use App\Support\Client\Homepage\JetpkHomepageHeroSizing;
use PHPUnit\Framework\TestCase;

class JetpkHomepageHeroSizingTest extends TestCase
{
    public function test_hero_text_percent_clamps_and_defaults(): void
    {
        $this->assertSame(100, JetpkHomepageHeroSizing::normalizeHeroTextPercent(null));
        $this->assertSame(75, JetpkHomepageHeroSizing::normalizeHeroTextPercent(50));
        $this->assertSame(140, JetpkHomepageHeroSizing::normalizeHeroTextPercent(200));
        $this->assertSame(1.1, JetpkHomepageHeroSizing::heroTextScaleDecimal(110));
    }

    public function test_search_ui_percent_maps_directly_to_css_scale(): void
    {
        $this->assertSame(100, JetpkHomepageHeroSizing::normalizeSearchUiPercent(''));
        $this->assertSame(80, JetpkHomepageHeroSizing::normalizeSearchUiPercent(10));
        $this->assertSame(115, JetpkHomepageHeroSizing::normalizeSearchUiPercent(500));
        $this->assertSame(0.8, JetpkHomepageHeroSizing::searchUiScaleDecimal(80));
        $this->assertSame(0.9, JetpkHomepageHeroSizing::searchUiScaleDecimal(90));
        $this->assertSame(1.0, JetpkHomepageHeroSizing::searchUiScaleDecimal(100));
        $this->assertSame(1.15, JetpkHomepageHeroSizing::searchUiScaleDecimal(115));
    }

    public function test_css_variables_from_hero_content(): void
    {
        $vars = JetpkHomepageHeroSizing::cssVariablesFromHero([
            'eyebrow_size' => '90',
            'headline_size' => '110',
            'highlight_size' => '105',
            'subtitle_size' => '95',
            'search_ui_scale' => '90',
        ]);

        $this->assertSame('0.9', $vars['--jp-hero-eyebrow-scale']);
        $this->assertSame('1.1', $vars['--jp-hero-headline-scale']);
        $this->assertSame('1.05', $vars['--jp-hero-highlight-scale']);
        $this->assertSame('0.95', $vars['--jp-hero-subtitle-scale']);
        $this->assertSame('0.9', $vars['--jp-search-ui-scale']);
    }

    public function test_normalize_hero_section_coerces_invalid_values(): void
    {
        $normalized = JetpkHomepageHeroSizing::normalizeHeroSection([
            'eyebrow_size' => 'nope',
            'headline_size' => '120',
            'search_ui_scale' => '150',
        ]);

        $this->assertSame('100', $normalized['eyebrow_size']);
        $this->assertSame('120', $normalized['headline_size']);
        $this->assertSame('115', $normalized['search_ui_scale']);
    }

    public function test_search_tokens_do_not_use_hidden_compact_multiplier(): void
    {
        $tokens = file_get_contents(dirname(__DIR__, 5).'/public/themes/frontend/jetpakistan/css/tokens.css') ?: '';
        $this->assertStringNotContainsString('SEARCH_UI_COMPACT_BASELINE', file_get_contents(dirname(__DIR__, 5).'/app/Support/Client/Homepage/JetpkHomepageHeroSizing.php') ?: '');
        $this->assertStringNotContainsString('max(44px,calc(var(--jp-search-field-height-base)', $tokens);
    }
}
