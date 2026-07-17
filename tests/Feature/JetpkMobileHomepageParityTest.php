<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * Strategy 1: canonical responsive JetPakistan homepage for mobile and desktop.
 */
class JetpkMobileHomepageParityTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_mobile_user_agent_sees_page_settings_hero_content(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'hero' => ['headline' => 'PAGE-SETTINGS-HEADLINE-VISIBLE-ON-MOBILE'],
        ]);

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
        ])->get('/');

        $response->assertOk();
        $response->assertSee('PAGE-SETTINGS-HEADLINE-VISIBLE-ON-MOBILE', false);
        $response->assertViewIs('themes.frontend.jetpakistan.frontend.home');
    }

    public function test_desktop_user_agent_sees_page_settings_hero_content(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'hero' => ['headline' => 'PAGE-SETTINGS-HEADLINE-VISIBLE-ON-DESKTOP'],
        ]);

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ])->get('/');

        $response->assertOk();
        $response->assertSee('PAGE-SETTINGS-HEADLINE-VISIBLE-ON-DESKTOP', false);
        $response->assertViewIs('themes.frontend.jetpakistan.frontend.home');
    }
}
