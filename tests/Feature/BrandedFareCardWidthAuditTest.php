<?php

namespace Tests\Feature;

use App\Support\Ui\UiVersionAuditService;
use Tests\TestCase;

class BrandedFareCardWidthAuditTest extends TestCase
{
    public function test_branded_fare_card_width_token_is_264px(): void
    {
        $css = file_get_contents(base_path('public/css/ota-public.css'));

        $this->assertStringContainsString('--ota-branded-fare-card-width: 264px', $css);
        $this->assertStringContainsString('--ota-branded-fare-card-width-max: 264px', $css);
    }

    public function test_no_branded_fare_desktop_rule_uses_304px(): void
    {
        $css = file_get_contents(base_path('public/css/ota-public.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/--ota-branded-fare-card-width:\s*304px/i',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/ota-branded-fare-card[^\n{]*\{[^}]*304px/i',
            $css
        );
    }

    public function test_no_branded_fare_300px_token_override(): void
    {
        $css = file_get_contents(base_path('public/css/ota-public.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/--ota-branded-fare-card-width:\s*300px/i',
            $css
        );
    }

    public function test_ui_version_audit_branded_fare_width_passes(): void
    {
        $audit = app(UiVersionAuditService::class)->brandedFareWidthAudit();

        $this->assertTrue($audit['pass']);
        $this->assertSame(0, $audit['fail']);
        $this->assertTrue($audit['token_present']);
        $this->assertFalse($audit['forbidden_304px']);
        $this->assertFalse($audit['forbidden_300px_branded']);
    }

    public function test_v2_branded_fare_slider_uses_264px_token(): void
    {
        $css = file_get_contents(base_path('public/css/v2/ota-public-v2.css'));

        $this->assertStringContainsString('var(--ota-branded-fare-card-width, 264px)', $css);
        $this->assertStringNotContainsString('calc((100cqw - 2 * 0.625rem) / 3)', $css);
    }
}
