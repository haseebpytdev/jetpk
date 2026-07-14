<?php

namespace App\Services\GroupTicketing;

use App\Models\GroupCategory;
use App\Models\GroupInventory;
use App\Support\GroupTicketing\GroupHomepageTilePresenter;
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
        if (! Schema::hasTable('group_categories')) {
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
        return GroupInventory::query()
            ->where('is_active', true)
            ->whereRaw('(total_seats - held_seats - sold_seats) > 0');
    }
}
