<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * CMS blank hero fields must round-trip through the public homepage API.
 * Fixture/JetPakistan copy must not replace intentional blanks.
 */
final class HomepageCmsBlankHeroApiTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    public function test_cms_blank_highlight_stays_blank_in_public_api(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->productionLikeHomepageContent();
        $content['hero']['headline'] = 'Every Flight from Pakistan';
        $content['hero']['headline_highlight'] = '';
        $content['hero']['eyebrow'] = '';
        $content['hero']['subtitle'] = '';
        $this->seedPublishedHome($profile, $content);

        $response = $this->getJson('/api/public/content/homepage')->assertOk();

        $this->assertSame('cms', $response->json('source'));
        $this->assertSame('Every Flight from Pakistan', $response->json('hero.headline'));
        $this->assertSame('', $response->json('hero.headline_highlight'));
        $this->assertSame('', $response->json('hero.eyebrow'));
        $this->assertSame('', $response->json('hero.subtitle'));

        $encoded = (string) $response->getContent();
        $this->assertStringNotContainsString('JetPakistan', $response->json('hero.headline_highlight') ?? 'x');
        $this->assertStringNotContainsString('"headline_highlight":"JetPakistan"', $encoded);
        $this->assertStringNotContainsString('"headline_highlight":"Book Now!"', $encoded);
    }

    public function test_cms_two_line_headline_and_highlight(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->productionLikeHomepageContent();
        $content['hero']['headline'] = 'Every Flight from Pakistan';
        $content['hero']['headline_highlight'] = 'Book Now!';
        $this->seedPublishedHome($profile, $content);

        $response = $this->getJson('/api/public/content/homepage')->assertOk();

        $this->assertSame('Every Flight from Pakistan', $response->json('hero.headline'));
        $this->assertSame('Book Now!', $response->json('hero.headline_highlight'));
    }

    public function test_cms_empty_headline_preserves_highlight_only(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->productionLikeHomepageContent();
        $content['hero']['headline'] = '';
        $content['hero']['headline_highlight'] = 'Book Now!';
        $this->seedPublishedHome($profile, $content);

        $response = $this->getJson('/api/public/content/homepage')->assertOk();

        $this->assertSame('', $response->json('hero.headline'));
        $this->assertSame('Book Now!', $response->json('hero.headline_highlight'));
    }

    public function test_cms_both_blank_returns_empty_hero_copy(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->productionLikeHomepageContent();
        $content['hero']['headline'] = '';
        $content['hero']['headline_highlight'] = '';
        $this->seedPublishedHome($profile, $content);

        $response = $this->getJson('/api/public/content/homepage')->assertOk();

        $this->assertSame('', $response->json('hero.headline'));
        $this->assertSame('', $response->json('hero.headline_highlight'));
    }
}
