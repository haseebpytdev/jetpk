<?php

namespace Tests\Unit\Support\Homepage;

use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Enums\ClientPageSettingStatus;
use App\Support\Client\ClientPageKeys;
use App\Support\Homepage\JetpkHeroLcpPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHeroLcpPresenterTest extends TestCase
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

    public function test_presenter_returns_responsive_sources_for_lcp_directory(): void
    {
        $heroUrl = asset('client-assets/jetpk-assets/pages/home/hero_background-20260710115655.jpg');
        if (! is_file(public_path('client-assets/jetpk-assets/pages/home/lcp/hero-desktop.webp'))) {
            $this->markTestSkipped('LCP variants not generated on disk.');
        }

        $presented = app(JetpkHeroLcpPresenter::class)->present($heroUrl);

        $this->assertNotNull($presented);
        $this->assertTrue($presented['has_responsive_variants']);
        $this->assertSame(1200, $presented['width']);
        $this->assertSame(600, $presented['height']);
        $this->assertStringContainsString('hero-desktop.avif', $presented['preload_url']);
        $this->assertNotSame('', $presented['alt']);
    }

    public function test_jetpk_homepage_renders_semantic_hero_picture_with_priority_hints(): void
    {
        if (! is_file(public_path('client-assets/jetpk-assets/pages/home/lcp/hero-desktop.webp'))) {
            $this->markTestSkipped('LCP variants not generated on disk.');
        }

        $profile = $this->makeJetpkProfile();
        $heroPath = 'client-assets/jetpk-assets/pages/home/hero_background-20260710115655.jpg';

        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'hero_background',
            'disk' => 'public',
            'path' => $heroPath,
            'public_url' => asset($heroPath),
            'alt_text' => 'JetPakistan flights',
        ]);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => app(\App\Services\Client\ClientPageContentResolver::class)->defaultHomeContent(),
            'published_at' => now(),
        ]);

        $response = $this->get(route('client.preview.home', ['clientSlug' => 'jetpk']));
        if ($response->isRedirect()) {
            $response = $this->followRedirects($response);
        }
        $response->assertOk();
        $html = $response->getContent();
        $this->assertIsString($html);

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('class="hero-img"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="600"', $html);
        $this->assertStringContainsString('rel="preload"', $html);
        $this->assertStringContainsString('jp-loader done', $html);
        $this->assertStringNotContainsString('--jp-hero-bg-image', $html);
        $this->assertStringContainsString('name="trip_type"', $html);
    }
}
