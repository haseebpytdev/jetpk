<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JetpkHeroVisualClarityTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ota_client.slug', 'jetpk');
        Config::set('ota_client.asset_profile', 'jetpk-assets');
        Config::set('ota_client.single_client_mode', true);
        Config::set('ota_client.single_client_root', true);
        $this->seedJetpkProfile();
    }

    private function seedJetpkProfile(): void
    {
        $profile = \App\Models\ClientProfile::query()->firstOrCreate(
            ['slug' => 'jetpk'],
            [
                'name' => 'Jet Pakistan',
                'environment' => 'staging',
                'active_frontend_theme' => 'jetpakistan',
                'active_admin_theme' => 'jetpakistan',
                'active_staff_theme' => 'jetpakistan',
                'asset_profile' => 'jetpk-assets',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'is_master_profile' => false,
                'is_active' => true,
            ],
        );
        app(\App\Services\Client\CurrentClientContext::class)->set($profile);
        config(['ota.default_client_slug' => 'jetpk']);
    }

    public function test_homepage_hero_css_removes_glow_stroke_and_uses_clear_header_nav(): void
    {
        $css = (string) file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css'));

        $this->assertStringContainsString('.jp-site-header .logo__img{filter:none', $css);
        $this->assertStringContainsString('color:#102a38', $css);
        $this->assertStringContainsString('.jp-home .hero h1 .gold{', $css);
        $this->assertStringNotContainsString('0 0 18px rgba(99,179,46,.55)', $css);
        $this->assertStringNotContainsString('0 2px 10px rgba(7,15,24,.42)', $css);
        $this->assertStringContainsString('-webkit-text-stroke:0', $css);
    }

    public function test_homepage_renders_jetpakistan_hero_markup(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('class="hero', $html);
        $this->assertStringContainsString('one honest fare.', $html);
        $this->assertStringContainsString('theme.css?v=47', $html);
        $this->assertStringContainsString('jp-header-nav', $html);
    }
}
