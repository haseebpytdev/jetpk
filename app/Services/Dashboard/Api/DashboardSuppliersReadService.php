<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardSupplierDetailResource;
use App\Http\Resources\Dashboard\DashboardSupplierResource;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardSuppliersReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, facets: array<string, list<string>>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', SupplierConnection::class);

        $query = $this->scopedQuery($user)->with([
            'supplierBookings.booking.fareBreakdown',
            'supplierBookings.booking.holdSession',
        ]);
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', (int) $request->query('per_page', 25))));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(static fn (SupplierConnection $row): array => DashboardSupplierResource::fromModel($row))
            ->values()
            ->all();

        $allForFacets = (clone $this->scopedQuery($user))->limit(200)->get();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pageCount' => $paginator->lastPage(),
            ],
            'filters' => $this->activeFilters($request),
            'facets' => [
                'categories' => $allForFacets->map(static fn (SupplierConnection $c): string => DashboardSupplierResource::fromModel($c)['supplierCategory'])->unique()->values()->all(),
                'regions' => ['Pakistan', 'Global'],
                'operationalStatuses' => ['Active', 'Inactive'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        $connection = $this->resolveConnection($user, $id);
        if ($connection === null) {
            return null;
        }

        Gate::authorize('view', $connection);
        $connection->loadCount('supplierBookings');

        return DashboardSupplierDetailResource::fromModel($connection);
    }

    protected function resolveConnection(User $user, string $id): ?SupplierConnection
    {
        $query = $this->scopedQuery($user);
        if (preg_match('/^SC-(\d+)$/i', $id, $matches) === 1) {
            return (clone $query)->whereKey((int) $matches[1])->first();
        }
        if (ctype_digit($id)) {
            return (clone $query)->whereKey((int) $id)->first();
        }

        return (clone $query)->where('name', $id)->orWhere('display_name', $id)->first();
    }

    /**
     * @return Builder<SupplierConnection>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = SupplierConnection::query();

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    /**
     * @param  Builder<SupplierConnection>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('display_name', 'like', '%'.$search.'%')
                    ->orWhere('provider', 'like', '%'.$search.'%');
            });
        }

        $supplier = (string) $request->query('supplier', '');
        if ($supplier !== '' && $supplier !== 'all') {
            $query->where('provider', 'like', '%'.$supplier.'%');
        }

        $channel = (string) $request->query('channel', '');
        if ($channel === 'gds') {
            $query->where('provider', 'sabre');
        } elseif ($channel === 'ndc') {
            $query->whereIn('provider', ['duffel', 'pia_ndc']);
        }

        $enabled = (string) $request->query('enabled', '');
        if ($enabled === 'yes') {
            $query->where('is_active', true);
        } elseif ($enabled === 'no') {
            $query->where('is_active', false);
        }

        $health = (string) $request->query('health', '');
        if ($health === 'healthy') {
            $query->whereIn('last_test_status', ['air_shopping_success', 'ready_for_review', 'success']);
        } elseif ($health === 'degraded') {
            $query->whereNotNull('last_error');
        }

        $environment = (string) $request->query('environment', '');
        if ($environment !== '' && $environment !== 'all') {
            $query->where('environment', $environment);
        }

        $configured = (string) $request->query('configured', '');
        if ($configured === 'yes') {
            $query->whereNotNull('credentials');
        } elseif ($configured === 'no') {
            $query->whereNull('credentials');
        }
    }

    /**
     * @param  Builder<SupplierConnection>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'supplierName');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $column = match ($sort) {
            'newest' => 'created_at',
            'lastActivity' => 'updated_at',
            'statusPriority' => 'is_active',
            default => 'display_name',
        };

        $query->orderBy($column, $direction);
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(Request $request): array
    {
        return array_filter([
            'q' => $request->query('q', $request->query('search')),
            'supplier' => $request->query('supplier'),
            'channel' => $request->query('channel'),
            'enabled' => $request->query('enabled'),
            'health' => $request->query('health'),
            'environment' => $request->query('environment'),
            'configured' => $request->query('configured'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
