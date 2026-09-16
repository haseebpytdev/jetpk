<?php

namespace App\Services\Seo;

use App\Support\Client\ClientPageKeys;
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

    public function revalidateHomepage(): void
    {
        $this->post([
            'homepage' => true,
            'page_keys' => [ClientPageKeys::HOME],
            'paths' => ['/'],
            'sitemap' => true,
        ]);
    }

    public function revalidatePublishedPageSettings(string $pageKey): void
    {
        if ($pageKey === ClientPageKeys::HOME) {
            $this->revalidateHomepage();

            return;
        }

        if (in_array($pageKey, [ClientPageKeys::GLOBAL, ClientPageKeys::FOOTER], true)) {
            $this->revalidateGlobal();

            return;
        }

        if (array_key_exists($pageKey, SeoManagedPageCatalog::MANAGED_PAGES)) {
            $this->revalidateManagedPage($pageKey);

            return;
        }

        if (ClientPageKeys::isCustom($pageKey)) {
            $this->revalidateCustomPage(ClientPageKeys::customSlug($pageKey));

            return;
        }

        $paths = self::pageSettingsPaths()[$pageKey] ?? [];
        if ($paths === []) {
            return;
        }

        $this->post([
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
