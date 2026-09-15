<?php

namespace App\Services\Seo;

use App\Support\Seo\SeoManagedPageCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Triggers Next.js on-demand cache revalidation after SEO Management publishes.
 */
final class NextPublicCacheRevalidator
{
    /**
     * @param  list<string>  $paths
     */
    public function revalidateManagedPage(string $pageKey, array $paths = []): void
    {
        $path = SeoManagedPageCatalog::managedPath($pageKey);
        if ($path !== null && ! in_array($path, $paths, true)) {
            $paths[] = $path;
        }

        $this->post([
            'page_keys' => [$pageKey],
            'paths' => $paths,
            'sitemap' => true,
        ]);
    }

    public function revalidateGlobal(): void
    {
        $this->post([
            'global' => true,
            'sitemap' => true,
        ]);
    }

    public function revalidateCmsPage(string $slug): void
    {
        $this->post([
            'cms_slugs' => [$slug],
            'sitemap' => true,
        ]);
    }

    public function revalidateCustomPage(string $slug): void
    {
        $this->post([
            'paths' => ['/'.$slug],
            'sitemap' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(array $payload): void
    {
        $url = (string) config('jetpk_public.next_revalidate_url');
        $secret = (string) config('jetpk_public.next_revalidate_secret');

        if ($url === '' || $secret === '') {
            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders(['x-jetpk-revalidate-secret' => $secret])
                ->acceptJson()
                ->post($url, $payload)
                ->throw();
        } catch (\Throwable $exception) {
            Log::warning('Next public cache revalidation failed', [
                'message' => $exception->getMessage(),
                'payload' => $payload,
            ]);
        }
    }
}
