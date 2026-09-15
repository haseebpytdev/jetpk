<?php

namespace Tests\Unit\Services\Client;

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
}
