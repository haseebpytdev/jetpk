<?php

namespace Tests\Feature;

use Tests\TestCase;

class JetPakistanLegacyTypographyAuthorityTest extends TestCase
{
    public function test_jetpakistan_blade_theme_tokens_declare_legacy_font_authority(): void
    {
        $tokensPath = public_path('themes/frontend/jetpakistan/css/tokens.css');
        $this->assertFileExists($tokensPath);

        $css = (string) file_get_contents($tokensPath);

        $normalized = str_replace([' ', "'", '"'], '', $css);

        $this->assertStringContainsString('--font-body:PlusJakartaSans', $normalized);
        $this->assertStringContainsString('--font-display:PlusJakartaSans', $normalized);
        $this->assertStringContainsString('--font-mono:IBMPlexMono', $normalized);
    }

    public function test_jetpakistan_typography_authority_stylesheet_exists(): void
    {
        $path = public_path('css/jetpk-typography-authority.css');
        $this->assertFileExists($path);

        $css = (string) file_get_contents($path);
        $this->assertStringContainsString('--font-jetpk-ui', $css);
        $this->assertStringContainsString('--font-jetpk-display', $css);
        $this->assertStringContainsString('Inter', $css);
        $this->assertStringNotContainsString('Space Grotesk', $css);
        $this->assertStringNotContainsString('Plus Jakarta', $css);
    }
}
