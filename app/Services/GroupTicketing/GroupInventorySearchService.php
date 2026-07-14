<?php

namespace App\Services\GroupTicketing;

use App\Models\GroupCategory;
use App\Models\GroupInventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Searches local group_inventories by sector, airline, date, category, and sort.
 */
class GroupInventorySearchService
{
    public const DEFAULT_PER_PAGE = 15;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, GroupInventory>
     */
    public function search(array $filters = []): Collection
    {
        return $this->searchQuery($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function searchPaginated(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $this->searchQuery($filters)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<GroupInventory>
     */
    public function searchQuery(array $filters = []): Builder
    {
        $query = GroupInventory::query()
            ->where('is_active', true)
            ->whereRaw('(total_seats - held_seats - sold_seats) > 0')
            ->with('category');

        $this->applyFilters($query, $filters);
        $this->applySort($query, (string) ($filters['sort'] ?? 'departure'));

        return $query;
    }

    public function findActive(int $id): ?GroupInventory
    {
        return GroupInventory::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->whereRaw('(total_seats - held_seats - sold_seats) > 0')
            ->first();
    }

    public function findByPublicId(string $publicId): ?GroupInventory
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        return GroupInventory::query()
            ->where(function (Builder $q) use ($publicId): void {
                $q->where('public_id', $publicId)
                    ->orWhere('supplier_package_id', $publicId);

                if (str_starts_with(strtoupper($publicId), 'ALH-')) {
                    $q->orWhere('supplier_package_id', substr($publicId, 4));
                }
            })
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  Builder<GroupInventory>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $sector = trim((string) ($filters['sector'] ?? ''));
        if ($sector !== '') {
            $query->where('sector', 'like', '%'.$sector.'%');
        }

        $airline = trim((string) ($filters['airline'] ?? ''));
        if ($airline !== '') {
            $query->where('airline_name', $airline);
        } else {
            $airlineId = trim((string) ($filters['airline_id'] ?? ''));
            if ($airlineId !== '' && ctype_digit($airlineId)) {
                $query->where('airline_id', (int) $airlineId);
            }
        }

        $categorySlug = trim((string) ($filters['category'] ?? $filters['type'] ?? ''));
        if ($categorySlug !== '') {
            $categoryIds = GroupCategory::query()
                ->where('slug', $categorySlug)
                ->orWhere('name', 'like', $categorySlug)
                ->pluck('id');

            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('group_category_id', $categoryIds);
            } else {
                $query->where('package_type', 'like', '%'.$categorySlug.'%');
            }
        }

        $minSeats = (int) ($filters['min_seats'] ?? 0);
        if ($minSeats > 0) {
            $query->whereRaw('(total_seats - held_seats - sold_seats) >= ?', [$minSeats]);
        }

        $priceMin = $filters['price_min'] ?? null;
        if ($priceMin !== null && $priceMin !== '') {
            $query->where('price', '>=', (float) $priceMin);
        }

        $priceMax = $filters['price_max'] ?? null;
        if ($priceMax !== null && $priceMax !== '') {
            $query->where('price', '<=', (float) $priceMax);
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($dateFrom !== '' || $dateTo !== '') {
            if ($dateFrom !== '' && $dateTo !== '') {
                $query->whereBetween('departure_date', [$dateFrom, $dateTo]);
            } elseif ($dateFrom !== '') {
                $query->whereDate('departure_date', '>=', $dateFrom);
            } else {
                $query->whereDate('departure_date', '<=', $dateTo);
            }
        } else {
            $flexible = filter_var($filters['flexible'] ?? false, FILTER_VALIDATE_BOOL);
            $deptDate = trim((string) ($filters['dept_date'] ?? $filters['departure_date'] ?? ''));

            if ($deptDate !== '') {
                if ($flexible) {
                    try {
                        $date = Carbon::parse($deptDate);
                        $query->whereBetween('departure_date', [
                            $date->copy()->startOfMonth()->toDateString(),
                            $date->copy()->endOfMonth()->toDateString(),
                        ]);
                    } catch (\Throwable) {
                        $query->whereDate('departure_date', $deptDate);
                    }
                } else {
                    $query->whereDate('departure_date', $deptDate);
                }
            }
        }
    }

    /**
     * @param  Builder<GroupInventory>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price' => $query->orderBy('price')->orderBy('departure_date'),
            'seats' => $query->orderByRaw('(total_seats - held_seats - sold_seats) DESC')->orderBy('departure_date'),
            'airline' => $query->orderBy('airline_name')->orderBy('departure_date'),
            default => $query->orderBy('departure_date')->orderBy('title'),
        };
    }
}
