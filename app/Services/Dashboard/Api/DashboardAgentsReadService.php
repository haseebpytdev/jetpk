<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardAgentDetailResource;
use App\Http\Resources\Dashboard\DashboardAgentResource;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardAgentsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, facets: array<string, list<string>>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', Agent::class);

        $query = $this->scopedQuery($user)->with(['user', 'agency', 'wallet'])->withCount('bookings');
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', (int) $request->query('per_page', 25))));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(fn (Agent $agent): array => DashboardAgentResource::fromModel(
                $agent,
                Gate::forUser($user)->allows('viewWallet', $agent),
            ))
            ->values()
            ->all();

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
                'statuses' => ['Active', 'Inactive'],
                'regions' => ['Pakistan'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        $agent = $this->resolveAgent($user, $id);
        if ($agent === null) {
            return null;
        }

        Gate::authorize('view', $agent);
        $agent->loadCount('bookings');

        return DashboardAgentDetailResource::fromModel($agent, $user);
    }

    protected function resolveAgent(User $user, string $id): ?Agent
    {
        $query = $this->scopedQuery($user);
        if (preg_match('/^AG-(.+)$/i', $id, $matches) === 1) {
            $code = $matches[1];
            if (ctype_digit($code)) {
                return (clone $query)->whereKey((int) $code)->first();
            }

            return (clone $query)->where('code', $code)->first();
        }
        if (ctype_digit($id)) {
            return (clone $query)->whereKey((int) $id)->first();
        }

        return null;
    }

    /**
     * @return Builder<Agent>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = Agent::query();

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    /**
     * @param  Builder<Agent>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $u) use ($search): void {
                        $u->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($request->filled('createdFrom')) {
            $query->whereDate('created_at', '>=', $request->query('createdFrom'));
        }
        if ($request->filled('createdTo')) {
            $query->whereDate('created_at', '<=', $request->query('createdTo'));
        }
    }

    /**
     * @param  Builder<Agent>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'agentName');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $column = match ($sort) {
            'newest' => 'created_at',
            'bookingCount' => 'bookings_count',
            'lastBookingDate' => 'updated_at',
            default => 'code',
        };

        if ($sort === 'bookingCount') {
            $query->orderBy('bookings_count', $direction);
        } else {
            $query->orderBy($column, $direction);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(Request $request): array
    {
        return array_filter([
            'q' => $request->query('q', $request->query('search')),
            'status' => $request->query('status'),
            'createdFrom' => $request->query('createdFrom'),
            'createdTo' => $request->query('createdTo'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
