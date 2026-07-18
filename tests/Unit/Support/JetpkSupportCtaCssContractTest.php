<?php

namespace Tests\Unit\Support;

use Tests\TestCase;

class JetpkSupportCtaCssContractTest extends TestCase
{
    public function test_theme_css_does_not_override_uploaded_support_cta_background(): void
    {
        $css = (string) file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css'));

        $this->assertStringContainsString('.support-cta--has-bg .bg{background-color:var(--jp-primary);background-image:var(--jp-support-bg)', $css);
        $this->assertStringContainsString('.jp-home .support-cta:not(.support-cta--has-bg) .bg{background:linear-gradient(135deg,#73C23E', $css);
        $this->assertStringNotContainsString('.jp-home .support-cta .bg{background:linear-gradient(135deg,#73C23E', $css);
    }

    public function test_uploaded_overlay_uses_translucent_before_pseudo_element(): void
    {
        $css = (string) file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css'));

        $this->assertStringContainsString('.support-cta--mode-uploaded_overlay.support-cta--has-bg::before', $css);
        $this->assertStringContainsString('.support-cta--mode-uploaded_overlay.support-cta--overlay-light.support-cta--has-bg::before', $css);
        $this->assertStringContainsString('.support-cta--mode-uploaded_overlay.support-cta--overlay-strong.support-cta--has-bg::before', $css);
    }

    public function test_mobile_support_cta_background_uses_mobile_variable(): void
    {
        $css = (string) file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css'));

        $this->assertStringContainsString('@media (max-width:768px){.support-cta--has-bg .bg{background-image:var(--jp-support-bg-mobile,var(--jp-support-bg))}', $css);
    }
}
