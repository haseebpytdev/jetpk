<?php

namespace App\Services\Client;

use App\Support\Client\ClientPageKeys;
use Illuminate\Support\Arr;

/**
 * Canonical renderer for CMS-owned JetPK public content pages.
 */
final class ClientPageRenderer
{
    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
        private readonly ClientPageSeoResolver $seoResolver,
        private readonly ClientGlobalContactResolver $contactResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewModel(string $pageKey): array
    {
        $content = $this->contentResolver->contentFor($pageKey);

        return [
            'pageKey' => $pageKey,
            'content' => $content,
            'seo' => $this->seoResolver->forPage($pageKey),
            'contact' => $this->contactResolver->contact(is_array($content['contact'] ?? null) ? $content['contact'] : []),
            'sectionsOrder' => $this->sectionsOrder($content),
        ];
    }

    public function field(string $pageKey, string $path, mixed $default = ''): mixed
    {
        return $this->contentResolver->section($pageKey, $path, $default, true);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public function sectionsOrder(array $content): array
    {
        $order = $content['sections_order'] ?? [];
        if (! is_array($order) || $order === []) {
            return array_values(array_filter(array_keys($content), fn (string $key) => ! str_starts_with($key, '_') && $key !== 'seo'));
        }

        return array_values(array_map('strval', $order));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enabledItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item) && ($item['enabled'] ?? '1') !== '0' && ($item['enabled'] ?? true) !== false)
            ->sortBy(fn ($item) => (int) ($item['sort_order'] ?? 0))
            ->values()
            ->all();
    }

    public function resolveDestination(string $destination): string
    {
        if ($destination === '') {
            return '#';
        }
        if (str_starts_with($destination, 'route:')) {
            $route = substr($destination, 6);
            if ($route === 'home#jp-flight-search') {
                return client_home_flight_search_url();
            }
            if (\Illuminate\Support\Facades\Route::has($route)) {
                return client_route($route);
            }

            return '#';
        }
        if (str_starts_with($destination, 'tel:') || str_starts_with($destination, 'mailto:')) {
            return $destination;
        }
        if (str_starts_with($destination, 'https://')) {
            return $destination;
        }
        if (str_starts_with($destination, '/')) {
            return client_url($destination);
        }

        return client_url('/'.$destination);
    }
}
