<?php

namespace App\Services\Seo;

use App\Support\Client\ClientPageKeys;
use App\Support\PublicContent\PublicCacheTags;
use App\Support\Seo\SeoManagedPageCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Triggers Next.js on-demand public-content cache revalidation after CMS publish.
 *
 * Posts to the protected allowlisted endpoint
 * `/api/internal/revalidate-public-content` (and the legacy SEO route when configured)
 * without blocking successful CMS persistence when notification fails.
 */
final class NextPublicCacheRevalidator
{
    /**
     * @param  list<string>  $paths
     * @return array{ok: bool, endpoints: list<array{url: string, ok: bool, message?: string}>}
     */
    public function revalidateManagedPage(string $pageKey, array $paths = []): array
    {
        $path = SeoManagedPageCatalog::managedPath($pageKey);
        if ($path !== null && ! in_array($path, $paths, true)) {
            $paths[] = $path;
        }

        return $this->post([
            'page_keys' => [$pageKey],
            'paths' => $paths,
            'tags' => [
                PublicCacheTags::CONTENT,
                PublicCacheTags::page($pageKey),
            ],
            'sitemap' => true,
        ]);
    }

    /**
     * @return array{ok: bool, endpoints: list<array{url: string, ok: bool, message?: string}>}
     */
    public function revalidateGlobal(): array
    {
        return $this->post([
            'global' => true,
            'config' => true,
            'tags' => [
                PublicCacheTags::CONTENT,
                PublicCacheTags::CONFIG,
            ],
            'sitemap' => true,
        ]);
    }

    /**
     * @return array{ok: bool, endpoints: list<array{url: string, ok: bool, message?: string}>}
     */
    public function revalidateCmsPage(string $slug): array
    {
        return $this->post([
            'cms_slugs' => [$slug],
            'sitemap' => true,
        ]);
    }

    /**
     * @return array{ok: bool, endpoints: list<array{url: string, ok: bool, message?: string}>}
     */
    public function revalidateCustomPage(string $slug): array
    {
        return $this->post([
            'paths' => ['/'.$slug],
            'sitemap' => true,
        ]);
    }

    /**
     * @return array{ok: bool, endpoints: list<array{url: string, ok: bool, message?: string}>}
     */
    public function revalidateHomepage(): array
    {
        return $this->post([
            'homepage' => true,
            'page_keys' => [ClientPageKeys::HOME],
            'paths' => ['/'],
            'tags' => [
                PublicCacheTags::CONTENT,
                PublicCacheTags::page(ClientPageKeys::HOME),
            ],
            'sitemap' => true,
        ]);
    }

    /**
     * @return array{ok: bool, endpoints: list<array{url: string, ok: bool, message?: string}>}
     */
    public function revalidatePublishedPageSettings(string $pageKey): array
    {
        if ($pageKey === ClientPageKeys::HOME) {
            return $this->revalidateHomepage();
        }

        if (in_array($pageKey, [ClientPageKeys::GLOBAL, ClientPageKeys::FOOTER], true)) {
            return $this->revalidateGlobal();
        }

        if (array_key_exists($pageKey, SeoManagedPageCatalog::MANAGED_PAGES)) {
            return $this->revalidateManagedPage($pageKey);
        }

        if (ClientPageKeys::isCustom($pageKey)) {
            return $this->revalidateCustomPage(ClientPageKeys::customSlug($pageKey));
        }

        $paths = self::pageSettingsPaths()[$pageKey] ?? [];
        if ($paths === []) {
            return ['ok' => true, 'endpoints' => []];
        }

        return $this->post([
            'paths' => $paths,
            'sitemap' => false,
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function pageSettingsPaths(): array
    {
        return [
            ClientPageKeys::GROUP_SEARCH => ['/groups/search'],
            ClientPageKeys::LOGIN => ['/login'],
            ClientPageKeys::REGISTER => ['/register'],
            ClientPageKeys::BOOKING_LOOKUP => ['/lookup-booking'],
            ClientPageKeys::AGENT_REGISTRATION => ['/agent/register'],
        ];
    }

    /**
     * @return list<string>
     */
    private function endpointUrls(): array
    {
        $urls = [];

        $primary = rtrim((string) config('jetpk_public.next_public_content_revalidate_url'), '/');
        if ($primary === '') {
            $legacy = rtrim((string) config('jetpk_public.next_revalidate_url'), '/');
            if ($legacy !== '') {
                if (str_ends_with($legacy, '/api/internal/revalidate/seo')) {
                    $primary = substr($legacy, 0, -strlen('/api/internal/revalidate/seo')).'/api/internal/revalidate-public-content';
                } elseif (str_ends_with($legacy, '/api/internal/revalidate-public-content')) {
                    $primary = $legacy;
                } else {
                    $primary = $legacy;
                }
            }
        }

        if ($primary !== '') {
            $urls[] = $primary;
        }

        $legacySeo = rtrim((string) config('jetpk_public.next_revalidate_url'), '/');
        if ($legacySeo !== '' && ! in_array($legacySeo, $urls, true)) {
            // Dual-write legacy SEO tags during cutover when a distinct SEO URL is configured.
            if (str_ends_with($legacySeo, '/api/internal/revalidate/seo')) {
                $urls[] = $legacySeo;
            }
        }

        return $urls;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, endpoints: list<array{url: string, ok: bool, message?: string}>}
     */
    private function post(array $payload): array
    {
        $secret = (string) config('jetpk_public.next_revalidate_secret');
        $urls = $this->endpointUrls();

        if ($urls === [] || $secret === '') {
            Log::info('Next public cache revalidation skipped (not configured)', [
                'payload' => $payload,
            ]);

            return ['ok' => true, 'endpoints' => []];
        }

        $results = [];
        $allOk = true;

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['x-jetpk-revalidate-secret' => $secret])
                    ->acceptJson()
                    ->post($url, $payload)
                    ->throw();

                $results[] = [
                    'url' => $url,
                    'ok' => true,
                    'message' => 'status='.$response->status(),
                ];

                Log::info('Next public cache revalidation succeeded', [
                    'url' => $url,
                    'payload' => $payload,
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $exception) {
                $allOk = false;
                $results[] = [
                    'url' => $url,
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ];

                Log::warning('Next public cache revalidation failed', [
                    'url' => $url,
                    'message' => $exception->getMessage(),
                    'payload' => $payload,
                    'safety_ttl_seconds' => 3600,
                ]);
            }
        }

        return ['ok' => $allOk, 'endpoints' => $results];
    }
}
