<?php

namespace Tests\Unit\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Services\Client\ClientPageSeoResolver;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class ClientPageSeoResolverTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAgency();
    }

    public function test_homepage_seo_uses_cms_values_with_fallbacks(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'seo' => [
                'title' => 'CMS Homepage SEO Title',
                'description' => 'CMS homepage SEO description.',
                'robots' => 'index,follow',
            ],
        ]);

        $seo = app(ClientPageSeoResolver::class)->forPage(
            ClientPageKeys::HOME,
            'JetPakistan | Affordable Flights, Umrah Packages & Tours',
            'Search and compare domestic and international flights from Pakistan, explore Umrah packages, and plan travel with JetPakistan.',
        );

        $this->assertSame('CMS Homepage SEO Title', $seo['title']);
        $this->assertSame('CMS homepage SEO description.', $seo['description']);
        $this->assertSame('index,follow', $seo['robots']);
    }

    public function test_page_seo_overrides_global_defaults(): void
    {
        $profile = $this->makeJetpkProfile();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::GLOBAL,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'seo' => [
                    'title' => 'Global Title',
                    'description' => 'Global description.',
                    'og_title' => 'Global OG',
                ],
            ],
            'published_at' => now(),
        ]);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'seo' => [
                    'title' => 'About Override',
                    'og_title' => 'About OG',
                ],
            ],
            'published_at' => now(),
        ]);

        $seo = app(ClientPageSeoResolver::class)->forPage(ClientPageKeys::ABOUT, 'Fallback', 'Fallback description.');

        $this->assertSame('About Override', $seo['title']);
        $this->assertSame('About OG', $seo['og_title']);
    }
}
