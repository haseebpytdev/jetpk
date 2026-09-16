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
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate/seo' => Http::response(['ok' => true], 200),
        ]);

        app(NextPublicCacheRevalidator::class)->revalidateManagedPage('about');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://jetpakistan.pk/api/internal/revalidate/seo'
                && $request->hasHeader('x-jetpk-revalidate-secret', 'test-secret')
                && $request['page_keys'] === ['about']
                && $request['paths'] === ['/about-us']
                && $request['sitemap'] === true;
        });
    }

    public function test_skips_request_when_not_configured(): void
    {
        config([
            'jetpk_public.next_revalidate_url' => '',
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
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate/seo' => Http::response(['ok' => true], 200),
        ]);

        app(NextPublicCacheRevalidator::class)->revalidateHomepage();

        Http::assertSent(function ($request): bool {
            return $request['homepage'] === true
                && $request['page_keys'] === ['home']
                && in_array('/', $request['paths'], true);
        });
    }

    public function test_page_settings_about_publish_uses_managed_page_payload(): void
    {
        config([
            'jetpk_public.next_revalidate_url' => 'https://jetpakistan.pk/api/internal/revalidate/seo',
            'jetpk_public.next_revalidate_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate/seo' => Http::response(['ok' => true], 200),
        ]);

        app(NextPublicCacheRevalidator::class)->revalidatePublishedPageSettings('about');

        Http::assertSent(function ($request): bool {
            return $request['page_keys'] === ['about']
                && $request['paths'] === ['/about-us'];
        });
    }
}
