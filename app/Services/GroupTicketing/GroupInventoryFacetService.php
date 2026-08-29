<?php

namespace App\Services\GroupTicketing;

use App\Enums\GroupHomepageTileTargetType;
use App\Models\GroupCategory;
use App\Models\GroupHomepageTile;
use App\Models\GroupInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Builds search dropdown facets from active group inventory only.
 */
class GroupInventoryFacetService
{
    /**
     * @return list<string>
     */
    public function sectors(): array
    {
        if (! Schema::hasTable('group_inventories')) {
            return [];
        }

        return $this->baseQuery()
            ->whereNotNull('sector')
            ->where('sector', '!=', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string}>
     */
    public function airlines(): array
    {
        if (! Schema::hasTable('group_inventories')) {
            return [];
        }

        return $this->baseQuery()
            ->whereNotNull('airline_name')
            ->where('airline_name', '!=', '')
            ->distinct()
            ->orderBy('airline_name')
            ->pluck('airline_name')
            ->map(fn ($name) => ['name' => (string) $name])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function departureDates(): array
    {
        if (! Schema::hasTable('group_inventories')) {
            return [];
        }

        return $this->baseQuery()
            ->whereNotNull('departure_date')
            ->distinct()
            ->orderBy('departure_date')
            ->pluck('departure_date')
            ->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d)
            ->values()
            ->all();
    }

    /**
     * @return list<array{slug: string, name: string, inventory_count: int}>
     */
    public function categoriesWithInventory(): array
    {
        if (! Schema::hasTable('group_categories') || ! Schema::hasTable('group_inventories')) {
            return [];
        }

        $counts = $this->baseQuery()
            ->whereNotNull('group_category_id')
            ->selectRaw('group_category_id, COUNT(*) as inventory_count')
            ->groupBy('group_category_id')
            ->pluck('inventory_count', 'group_category_id');
        if ($counts->isEmpty()) {
            return [];
        }

        return GroupCategory::query()
            ->whereIn('id', $counts->keys())
            ->where('is_active', true)
            ->get(['id', 'slug', 'name', 'sort_order'])
            ->map(fn (GroupCategory $cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'inventory_count' => (int) ($counts[$cat->id] ?? 0),
                'sort_order' => (int) $cat->sort_order,
            ])
            ->sortBy([
                ['inventory_count', 'desc'],
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(fn (array $row) => [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'inventory_count' => $row['inventory_count'],
            ])
            ->all();
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function categories(): array
    {
        return array_map(
            fn (array $row) => ['slug' => $row['slug'], 'name' => $row['name']],
            $this->categoriesWithInventory(),
        );
    }

    public function totalActiveInventoryCount(): int
    {
        if (! Schema::hasTable('group_inventories')) {
            return 0;
        }

        return $this->baseQuery()->count();
    }

    /**
     * All sync-created categories with inventory stats for read-only admin view.
     *
     * @return list<array{
     *     name: string,
     *     slug: string,
     *     is_active: bool,
     *     inventory_count: int,
     *     active_inventory_count: int,
     *     last_synced_at: ?string,
     *     homepage_title: string,
     *     has_public_tile: bool
     * }>
     */
    public function categoriesForAdmin(): array
    {
        if (! Schema::hasTable('group_categories') || ! Schema::hasTable('group_inventories')) {
            return [];
        }

        $activeCounts = $this->baseQuery()
            ->whereNotNull('group_category_id')
            ->selectRaw('group_category_id, COUNT(*) as active_inventory_count')
            ->groupBy('group_category_id')
            ->pluck('active_inventory_count', 'group_category_id');

        $allCounts = GroupInventory::query()
            ->whereNotNull('group_category_id')
            ->selectRaw('group_category_id, COUNT(*) as inventory_count, MAX(synced_at) as last_synced_at')
            ->groupBy('group_category_id')
            ->get()
            ->keyBy('group_category_id');

        return GroupCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'is_active', 'sort_order'])
            ->map(function (GroupCategory $cat) use ($activeCounts, $allCounts): array {
                $stats = $allCounts->get($cat->id);
                $activeCount = (int) ($activeCounts[$cat->id] ?? 0);
                $lastSynced = $stats?->last_synced_at;

                return [
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'is_active' => (bool) $cat->is_active,
                    'inventory_count' => (int) ($stats?->inventory_count ?? 0),
                    'active_inventory_count' => $activeCount,
                    'last_synced_at' => $lastSynced instanceof \DateTimeInterface
                        ? $lastSynced->format('Y-m-d H:i')
                        : (is_string($lastSynced) && $lastSynced !== '' ? $lastSynced : null),
                    'homepage_title' => GroupHomepageTilePresenter::categoryDisplayTitle($cat->name),
                    'has_public_tile' => $activeCount > 0 && $cat->is_active,
                ];
            })
            ->values()
            ->all();
    }

    public function lastInventorySyncAt(): ?\DateTimeInterface
    {
        if (! Schema::hasTable('group_inventories')) {
            return null;
        }

        $max = GroupInventory::query()->max('synced_at');

        if ($max instanceof \DateTimeInterface) {
            return $max;
        }

        return is_string($max) && $max !== '' ? new \DateTimeImmutable($max) : null;
    }

    /**
     * @return array{
     *     sectors: list<array{value: string, label: string}>,
     *     airlines: list<array{value: string, label: string}>,
     *     categories: list<array{value: string, label: string, inventory_count: int}>,
     *     date_bounds: ?array{minimum: string, maximum: string},
     *     travel_date_match: array{mode: string, tolerance_days: int}
     * }
     */
    public function forPublicSearch(): array
    {
        $all = $this->all();
        $departureDates = $all['departure_dates'] ?? [];
        $dateBounds = null;

        if ($departureDates !== []) {
            $sorted = $departureDates;
            sort($sorted);
            $dateBounds = [
                'minimum' => $sorted[0],
                'maximum' => $sorted[array_key_last($sorted)],
            ];
        }

        $categoriesWithCounts = $this->categoriesWithInventory();
        $categoryImages = $this->categoryImageUrlsBySlug();

        return [
            'sectors' => array_map(
                fn (string $sector): array => ['value' => $sector, 'label' => $sector],
                $all['sectors'] ?? [],
            ),
            'airlines' => array_map(
                fn (array $airline): array => [
                    'value' => (string) ($airline['name'] ?? ''),
                    'label' => (string) ($airline['name'] ?? ''),
                ],
                $all['airlines'] ?? [],
            ),
            'categories' => array_map(
                function (array $category) use ($categoryImages): array {
                    $slug = (string) ($category['slug'] ?? '');

                    return [
                        'value' => $slug,
                        'label' => (string) ($category['name'] ?? ''),
                        'inventory_count' => (int) ($category['inventory_count'] ?? 0),
                        'image_url' => $categoryImages[$slug] ?? null,
                        'subtitle' => null,
                    ];
                },
                $categoriesWithCounts,
            ),
            'date_bounds' => $dateBounds,
            'travel_date_match' => [
                'mode' => GroupInventorySearchService::TRAVEL_DATE_MATCH_MODE,
                'tolerance_days' => GroupInventorySearchService::TRAVEL_DATE_TOLERANCE_DAYS,
            ],
        ];
    }

    /**
     * CMS/admin homepage-tile media keyed by category slug (presentation enrichment only).
     *
     * @return array<string, string>
     */
    private function categoryImageUrlsBySlug(): array
    {
        if (! Schema::hasTable('group_homepage_tiles')) {
            return [];
        }

        $out = [];
        $tiles = GroupHomepageTile::query()
            ->where('target_type', GroupHomepageTileTargetType::Category)
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->get(['target_value', 'image_path']);

        foreach ($tiles as $tile) {
            $slug = trim((string) $tile->target_value);
            $path = trim((string) $tile->image_path);
            if ($slug === '' || $path === '') {
                continue;
            }
            $path = ltrim($path, '/');
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                $out[$slug] = $path;
            } elseif (str_starts_with($path, 'storage/')) {
                $out[$slug] = asset($path);
            } else {
                $out[$slug] = asset('storage/'.$path);
            }
        }

        return $out;
    }

    /**
     * @return array{sectors: list<string>, airlines: list<array{name: string}>, departure_dates: list<string>, categories: list<array{slug: string, name: string}>}
     */
    public function all(): array
    {
        return [
            'sectors' => $this->sectors(),
            'airlines' => $this->airlines(),
            'departure_dates' => $this->departureDates(),
            'categories' => $this->categories(),
        ];
    }

    /** @return Builder<GroupInventory> */
    private function baseQuery(): Builder
    {
        $query = GroupInventory::query()
            ->where('is_active', true)
            ->whereRaw('(total_seats - held_seats - sold_seats) > 0');

        // Match search visibility: guests / non-allowlisted users must not see manual_local in facets.
        if (! \App\Support\GroupTicketing\GroupManualLocalVisibility::userCanViewManualLocal(auth()->user())) {
            $query->where(function (Builder $inner): void {
                $inner->whereNull('supplier')
                    ->orWhere('supplier', '!=', GroupInventory::SUPPLIER_MANUAL_LOCAL);
            });
        }

        return $query;
    }
}
