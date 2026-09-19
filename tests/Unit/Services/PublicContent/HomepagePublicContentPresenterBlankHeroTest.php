<?php

namespace Tests\Unit\Services\PublicContent;

use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageSeoResolver;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use App\Support\Client\JetpkHomepageSectionData;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Explicit CMS blank hero text must round-trip as empty strings (no slogan backfill).
 */
final class HomepagePublicContentPresenterBlankHeroTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cms_blank_hero_text_fields_remain_empty_strings(): void
    {
        $homepage = Mockery::mock(JetpkHomepageSectionData::class);
        $homepage->shouldReceive('field')
            ->with('hero', [])
            ->andReturn([
                'eyebrow' => '',
                'headline' => '',
                'headline_highlight' => '',
                'subtitle' => '',
                'search_visible' => '1',
            ]);
        $homepage->shouldReceive('assetUrl')
            ->with('hero_background')
            ->andReturn(null);

        $contentResolver = Mockery::mock(ClientPageContentResolver::class);
        $seoResolver = Mockery::mock(ClientPageSeoResolver::class);

        $presenter = new HomepagePublicContentPresenter($homepage, $contentResolver, $seoResolver);

        $method = new ReflectionMethod(HomepagePublicContentPresenter::class, 'presentHero');
        $method->setAccessible(true);
        /** @var array<string, mixed> $hero */
        $hero = $method->invoke($presenter, true);

        $this->assertSame('', $hero['eyebrow']);
        $this->assertSame('', $hero['headline']);
        $this->assertSame('', $hero['headline_highlight']);
        $this->assertSame('', $hero['subtitle']);
        $this->assertStringNotContainsString('Explore the world', json_encode($hero, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Compare flights, pay in PKR', json_encode($hero, JSON_THROW_ON_ERROR));
    }
}
