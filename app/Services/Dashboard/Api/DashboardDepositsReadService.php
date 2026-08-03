<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardDepositResource;
use App\Models\AgentDepositRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardDepositsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', AgentDepositRequest::class);

        $query = $this->scopedQuery($user);
        $status = (string) $request->query('status', 'all');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));

        $paginator = (clone $query)->latest('id')->paginate($pageSize, ['*'], 'page', $page);

        $items = $paginator->getCollection()
            ->map(fn (AgentDepositRequest $deposit): array => DashboardDepositResource::fromModel($deposit, $user))
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
            'filters' => [
                'status' => $status,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        $deposit = $this->scopedQuery($user)->whereKey((int) $id)->first();
        if ($deposit === null) {
            return null;
        }

        Gate::authorize('view', $deposit);

        return DashboardDepositResource::fromModel($deposit, $user);
    }

    /**
     * @return Builder<AgentDepositRequest>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = AgentDepositRequest::query()->with(['agency', 'user', 'agent.user', 'reviewer']);

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }
}
