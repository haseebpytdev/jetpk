<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkCanonicalResponsiveUiTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_homepage_has_no_legacy_mobile_shell_or_toggle_controls(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, []);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringNotContainsString('data-testid="jp-desktop-mobile-app-toggle"', $html);
        $this->assertStringNotContainsString('data-testid="ota-desktop-mobile-toggle"', $html);
        $this->assertStringNotContainsString('ota-mobile-app.css', $html);
        $this->assertStringNotContainsString('ota-mobile-bottom-bar', $html);
    }

    public function test_login_uses_canonical_jetpakistan_responsive_layout(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('jp-auth-page', $html);
        $this->assertStringContainsString('data-jp-login-form', $html);
        $this->assertStringNotContainsString('ota-mobile-auth', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }
}
