<?php

namespace App\Support\GroupTicketing;

use App\Enums\GroupHomepageTileTargetType;
use App\Models\GroupHomepageTile;
use App\Services\GroupTicketing\GroupInventoryFacetService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves inventory-driven homepage tiles for the public home page and admin overrides UI.
 */
class GroupHomepageTilePresenter
{
    public function __construct(
        protected GroupInventoryFacetService $facetService,
    ) {}

    /**
     * @return Collection<int, array{title: string, url: string, image_url: ?string, package_count: int}>
     */
    public function presentForHome(): Collection
    {
        return $this->presentDynamicInventoryTilesForHome();
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     target_type: GroupHomepageTileTargetType,
     *     target_value: ?string,
     *     default_title: string,
     *     title_override: ?string,
     *     display_title: string,
     *     image_url: ?string,
     *     package_count: int,
     *     url: string,
     *     override_id: ?int,
     *     sort_order: int,
     *     is_active_override: bool,
     *     is_hidden: bool
     * }>
     */
    public function presentForAdmin(): Collection
    {
        if (! Schema::hasTable('group_inventories')) {
            return collect();
        }

        $overrides = $this->loadAdminTileOverrides(activeOnly: false);
        $totalCount = $this->facetService->totalActiveInventoryCount();
        $categories = $this->facetService->categoriesWithInventory();

        $tiles = collect();

        $allOverride = $overrides['all'] ?? null;
        $tiles->push($this->buildAdminTileRow(
            key: 'all',
            targetType: GroupHomepageTileTargetType::All,
            targetValue: null,
            defaultTitle: 'All Groups',
            packageCount: $totalCount,
            url: route('group-ticketing.search'),
            override: $allOverride,
            sortOrder: $allOverride !== null ? (int) $allOverride->sort_order : 0,
        ));

        $categoryTiles = collect();
        foreach ($categories as $index => $category) {
            $slug = $category['slug'];
            $categoryOverride = $overrides['category'][$slug] ?? null;

            $categoryTiles->push(array_merge(
                $this->buildAdminTileRow(
                    key: $slug,
                    targetType: GroupHomepageTileTargetType::Category,
                    targetValue: $slug,
                    defaultTitle: self::categoryDisplayTitle($category['name']),
                    packageCount: $category['inventory_count'],
                    url: route('group-ticketing.search', ['category' => $slug]),
                    override: $categoryOverride,
                    sortOrder: $categoryOverride !== null
                        ? (int) $categoryOverride->sort_order
                        : 1000 + $index,
                ),
                ['display_order' => $categoryOverride !== null
                    ? (int) $categoryOverride->sort_order
                    : 1000 + $index],
            ));
        }

        foreach ($categoryTiles->sortBy('display_order')->values() as $categoryTile) {
            unset($categoryTile['display_order']);
            $tiles->push($categoryTile);
        }

        return $tiles;
    }

    /**
     * @return Collection<int, array{title: string, url: string, image_url: ?string, package_count: int}>
     */
    public function presentDynamicInventoryTilesForHome(): Collection
    {
        if (! Schema::hasTable('group_inventories')) {
            return collect();
        }

        $overrides = $this->loadAdminTileOverrides(activeOnly: false);
        $totalCount = $this->facetService->totalActiveInventoryCount();
        $categories = $this->facetService->categoriesWithInventory();

        $tiles = collect();

        $allOverride = $overrides['all'] ?? null;
        $allOverrideActive = $allOverride === null || $allOverride->is_active;
        $tiles->push([
            'title' => ($allOverrideActive && $allOverride?->title)
                ? $allOverride->title
                : 'All Groups',
            'url' => route('group-ticketing.search'),
            'image_url' => $allOverrideActive ? $this->resolveImageUrl($allOverride) : null,
            'package_count' => $totalCount,
        ]);

        $categoryTiles = collect();
        foreach ($categories as $index => $category) {
            $slug = $category['slug'];
            $categoryOverride = $overrides['category'][$slug] ?? null;

            if ($categoryOverride !== null && ! $categoryOverride->is_active) {
                continue;
            }

            $categoryTiles->push([
                'title' => $categoryOverride?->title ?: self::categoryDisplayTitle($category['name']),
                'url' => route('group-ticketing.search', ['category' => $slug]),
                'image_url' => $this->resolveImageUrl($categoryOverride),
                'package_count' => $category['inventory_count'],
                'display_order' => $categoryOverride !== null
                    ? (int) $categoryOverride->sort_order
                    : 1000 + $index,
            ]);
        }

        foreach ($categoryTiles->sortBy('display_order')->values() as $categoryTile) {
            unset($categoryTile['display_order']);
            $tiles->push($categoryTile);
        }

        return $tiles;
    }

    public static function categoryDisplayTitle(string $name): string
    {
        $normalized = strtoupper(trim($name));

        return match ($normalized) {
            'KSA ONEWAY' => 'KSA Groups',
            'UAE' => 'UAE Groups',
            'MUSCAT' => 'Muscat Groups',
            default => trim($name).' Groups',
        };
    }

    /**
     * @return array{
     *     key: string,
     *     target_type: GroupHomepageTileTargetType,
     *     target_value: ?string,
     *     default_title: string,
     *     title_override: ?string,
     *     display_title: string,
     *     image_url: ?string,
     *     package_count: int,
     *     url: string,
     *     override_id: ?int,
     *     sort_order: int,
     *     is_active_override: bool,
     *     is_hidden: bool
     * }
     */
    private function buildAdminTileRow(
        string $key,
        GroupHomepageTileTargetType $targetType,
        ?string $targetValue,
        string $defaultTitle,
        int $packageCount,
        string $url,
        ?GroupHomepageTile $override,
        int $sortOrder,
    ): array {
        $titleOverride = ($override !== null && is_string($override->title) && $override->title !== '')
            ? $override->title
            : null;
        $isHidden = $override !== null && ! $override->is_active;

        return [
            'key' => $key,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'default_title' => $defaultTitle,
            'title_override' => $titleOverride,
            'display_title' => $titleOverride ?: $defaultTitle,
            'image_url' => $this->resolveImageUrl($override),
            'package_count' => $packageCount,
            'url' => $url,
            'override_id' => $override?->id,
            'sort_order' => $sortOrder,
            'is_active_override' => $override?->is_active ?? true,
            'is_hidden' => $isHidden,
        ];
    }

    /**
     * @return array{all: ?GroupHomepageTile, category: array<string, GroupHomepageTile>}
     */
    private function loadAdminTileOverrides(bool $activeOnly = false): array
    {
        $result = ['all' => null, 'category' => []];

        if (! Schema::hasTable('group_homepage_tiles')) {
            return $result;
        }

        $query = GroupHomepageTile::query()
            ->whereIn('target_type', [
                GroupHomepageTileTargetType::All,
                GroupHomepageTileTargetType::Category,
            ])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        foreach ($query->get() as $tile) {
            $type = $tile->target_type instanceof GroupHomepageTileTargetType
                ? $tile->target_type
                : GroupHomepageTileTargetType::tryFrom((string) $tile->target_type);

            if ($type === GroupHomepageTileTargetType::All && $result['all'] === null) {
                $result['all'] = $tile;
            } elseif ($type === GroupHomepageTileTargetType::Category) {
                $slug = trim((string) $tile->target_value);
                if ($slug !== '' && ! isset($result['category'][$slug])) {
                    $result['category'][$slug] = $tile;
                }
            }
        }

        return $result;
    }

    private function resolveImageUrl(?GroupHomepageTile $tile): ?string
    {
        if ($tile === null || ! is_string($tile->image_path) || $tile->image_path === '') {
            return null;
        }

        $path = ltrim($tile->image_path, '/');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }
}
