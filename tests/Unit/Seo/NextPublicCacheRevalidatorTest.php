<?php

namespace Tests\Unit\Seo;

use App\Services\Seo\NextPublicCacheRevalidator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NextPublicCacheRevalidatorTest extends TestCase
{
    public function test_publish_managed_posts_revalidation_payload(): void
    {
        config([
            'jetpk_public.next_revalidate_url' => 'https://jetpakistan.pk/api/internal/revalidate/seo',
            'jetpk_public.next_public_content_revalidate_url' => '',
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate-public-content' => Http::response(['ok' => true], 200),
            'https://jetpakistan.pk/api/internal/revalidate/seo' => Http::response(['ok' => true], 200),
        ]);

        $result = app(NextPublicCacheRevalidator::class)->revalidateManagedPage('about');

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://jetpakistan.pk/api/internal/revalidate-public-content'
                && $request->hasHeader('x-jetpk-revalidate-secret', 'test-secret')
                && $request['page_keys'] === ['about']
                && $request['paths'] === ['/about-us']
                && $request['sitemap'] === true
                && in_array('jp-public-page-about', $request['tags'], true);
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://jetpakistan.pk/api/internal/revalidate/seo'
                && $request['page_keys'] === ['about'];
        });
    }

    public function test_skips_request_when_not_configured(): void
    {
        config([
            'jetpk_public.next_revalidate_url' => '',
            'jetpk_public.next_public_content_revalidate_url' => '',
            'jetpk_public.next_revalidate_secret' => '',
        ]);

        Http::fake();

        app(NextPublicCacheRevalidator::class)->revalidateGlobal();

        Http::assertNothingSent();
    }

    public function test_homepage_publish_posts_homepage_flag(): void
    {
        config([
            'jetpk_public.next_revalidate_url' => 'https://jetpakistan.pk/api/internal/revalidate/seo',
            'jetpk_public.next_public_content_revalidate_url' => '',
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate-public-content' => Http::response(['ok' => true], 200),
            'https://jetpakistan.pk/api/internal/revalidate/seo' => Http::response(['ok' => true], 200),
        ]);

        app(NextPublicCacheRevalidator::class)->revalidateHomepage();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://jetpakistan.pk/api/internal/revalidate-public-content'
                && $request['homepage'] === true
                && $request['page_keys'] === ['home']
                && in_array('/', $request['paths'], true);
        });
    }

    public function test_page_settings_about_publish_uses_managed_page_payload(): void
    {
        config([
            'jetpk_public.next_revalidate_url' => 'https://jetpakistan.pk/api/internal/revalidate/seo',
            'jetpk_public.next_public_content_revalidate_url' => '',
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate-public-content' => Http::response(['ok' => true], 200),
            'https://jetpakistan.pk/api/internal/revalidate/seo' => Http::response(['ok' => true], 200),
        ]);

        app(NextPublicCacheRevalidator::class)->revalidatePublishedPageSettings('about');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://jetpakistan.pk/api/internal/revalidate-public-content'
                && $request['page_keys'] === ['about']
                && $request['paths'] === ['/about-us'];
        });
    }

    public function test_revalidation_failure_does_not_throw(): void
    {
        config([
            'jetpk_public.next_revalidate_url' => 'https://jetpakistan.pk/api/internal/revalidate/seo',
            'jetpk_public.next_public_content_revalidate_url' => '',
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate-public-content' => Http::response(['ok' => false], 500),
            'https://jetpakistan.pk/api/internal/revalidate/seo' => Http::response(['ok' => false], 500),
        ]);

        $result = app(NextPublicCacheRevalidator::class)->revalidateManagedPage('about');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['endpoints']);
    }
}
