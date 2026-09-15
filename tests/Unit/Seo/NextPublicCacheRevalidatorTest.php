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
}
