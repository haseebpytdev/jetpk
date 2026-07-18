<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    public function test_register_uses_canonical_layout_on_mobile_user_agent(): void
    {
        $html = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
            ->get(route('register'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('jp-auth-form', $html);
        $this->assertStringNotContainsString('ota-mobile-auth', $html);
    }

    public function test_forgot_password_uses_canonical_layout_on_mobile_user_agent(): void
    {
        $html = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
            ->get(route('password.request'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('jp-auth-form', $html);
        $this->assertStringNotContainsString('ota-mobile-forgot-password', $html);
    }

    public function test_support_and_lookup_use_canonical_layout_on_mobile_user_agent(): void
    {
        foreach (['support', 'booking.lookup'] as $routeName) {
            $html = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
                ->get(route($routeName))
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('ota-mobile-app-shell', $html);
            $this->assertStringNotContainsString('ota-mobile-auth', $html);
        }
    }

    public function test_homepage_section_stack_renders_without_preview_context(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'routes' => ['enabled' => '1', 'items' => [['from' => 'KHI', 'to' => 'DXB', 'enabled' => '1']]],
            'destinations' => ['enabled' => '1', 'items' => [['code' => 'DXB', 'title' => 'Dubai', 'enabled' => '1']]],
            'support_cta' => ['enabled' => '1', 'title' => 'PROBE-SUPPORT-CTA'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('jp-section-start:routes', false)
            ->assertSee('jp-section-start:destinations', false)
            ->assertSee('PROBE-SUPPORT-CTA', false);
    }

    public function test_hero_cta_keys_stripped_after_normalization(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'hero' => [
                'cta_primary_text' => 'Search flights',
                'cta_primary_url' => '/flights',
                'cta_secondary_text' => 'Group fares',
                'cta_secondary_url' => '/group-ticketing',
            ],
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('hero-cta-primary', $html);
        $this->assertStringNotContainsString('hero-cta-secondary', $html);
    }

    public function test_view_preference_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('view-preference.mobile'));
        $this->assertFalse(Route::has('view-preference.desktop'));
        $this->assertFalse(Route::has('view-preference.mobile-get'));
    }

    public function test_ota_view_mode_cookie_does_not_switch_login_shell(): void
    {
        $cookie = config('ota-mobile.cookie_name', 'ota_view_mode');

        $html = $this->withCookie($cookie, 'mobile')
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
            ->get(route('login'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('jp-auth-page', $html);
        $this->assertStringNotContainsString('ota-mobile-auth', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_no_mobile_app_blade_references_in_canonical_home_response(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, []);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('ota-mobile-home-trust-bar', $html);
        $this->assertStringNotContainsString('layouts.mobile-app', $html);
        $this->assertStringNotContainsString('themes.mobile.', $html);
    }

    public function test_agent_agency_controller_route_registry_is_valid(): void
    {
        $this->assertTrue(Route::has('agent.agency.show'));
        $this->assertTrue(class_exists(\App\Http\Controllers\Agent\AgentAgencyController::class));
    }
}
