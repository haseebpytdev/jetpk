<?php

namespace Tests\Unit\Services\Next;

use App\Services\Next\JetpkNextCacheRevalidationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JetpkNextCacheRevalidationServiceTest extends TestCase
{
    public function test_skips_when_secret_missing(): void
    {
        config([
            'jetpk_next.public_base_url' => 'https://jetpakistan.pk',
            'jetpk_next.revalidate_secret' => '',
        ]);

        Http::fake();

        $result = app(JetpkNextCacheRevalidationService::class)->revalidateHomepageAndPublicConfig();

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_secret', $result['skipped']);
        Http::assertNothingSent();
    }

    public function test_skips_when_base_url_missing(): void
    {
        config([
            'jetpk_next.public_base_url' => '',
            'jetpk_next.revalidate_secret' => 'test-secret',
        ]);

        Http::fake();

        $result = app(JetpkNextCacheRevalidationService::class)->revalidateHomepageAndPublicConfig();

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_base_url', $result['skipped']);
        Http::assertNothingSent();
    }

    public function test_posts_to_configured_endpoint_with_secret_header(): void
    {
        config([
            'jetpk_next.public_base_url' => 'https://jetpakistan.pk',
            'jetpk_next.revalidate_secret' => 'test-secret',
            'jetpk_next.revalidate_homepage_path' => '/api/internal/revalidate/homepage',
            'jetpk_next.revalidate_timeout_seconds' => 8,
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate/homepage' => Http::response([
                'ok' => true,
                'revalidated' => ['homepage-cms', 'public-config'],
            ], 200),
        ]);

        $result = app(JetpkNextCacheRevalidationService::class)->revalidateHomepageAndPublicConfig();

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://jetpakistan.pk/api/internal/revalidate/homepage'
                && $request->hasHeader('X-Jetpk-Revalidate-Secret', 'test-secret');
        });
    }

    public function test_handles_non_success_response(): void
    {
        config([
            'jetpk_next.public_base_url' => 'https://jetpakistan.pk',
            'jetpk_next.revalidate_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://jetpakistan.pk/api/internal/revalidate/homepage' => Http::response(['ok' => false], 500),
        ]);

        $result = app(JetpkNextCacheRevalidationService::class)->revalidateHomepageAndPublicConfig();

        $this->assertFalse($result['ok']);
        $this->assertSame(500, $result['status']);
    }

    public function test_handles_exception_without_throwing(): void
    {
        config([
            'jetpk_next.public_base_url' => 'https://jetpakistan.pk',
            'jetpk_next.revalidate_secret' => 'test-secret',
        ]);

        Http::fake(function (): void {
            throw new \RuntimeException('connection refused');
        });

        $result = app(JetpkNextCacheRevalidationService::class)->revalidateHomepageAndPublicConfig();

        $this->assertFalse($result['ok']);
        $this->assertSame('exception', $result['skipped']);
    }
}
