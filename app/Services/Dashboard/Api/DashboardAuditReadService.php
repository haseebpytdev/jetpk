<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardAuditEventResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardAuditReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, summary: array<string, int>}
     */
    public function paginate(User $user, Request $request): array
    {
        DashboardPermissionResolver::assertPermission($user, 'audit.view');

        $query = $this->scopedQuery($user)->with('user');
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(static fn (AuditLog $log): array => DashboardAuditEventResource::fromModel($log))
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
            'summary' => $this->summary($items),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        DashboardPermissionResolver::assertPermission($user, 'audit.view');
        $log = $this->resolveLog($user, $id);
        if ($log === null) {
            return null;
        }

        $log->loadMissing('user');

        return DashboardAuditEventResource::detail($log);
    }

    /**
     * @return Builder<AuditLog>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = AuditLog::query();
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    protected function resolveLog(User $user, string $id): ?AuditLog
    {
        $query = $this->scopedQuery($user);
        if (preg_match('/^JP-AUD-(\d+)$/i', $id, $matches) === 1) {
            return (clone $query)->whereKey((int) $matches[1])->first();
        }
        if (ctype_digit($id)) {
            return (clone $query)->whereKey((int) $id)->first();
        }

        return null;
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('action', 'like', '%'.$search.'%')
                    ->orWhere('auditable_type', 'like', '%'.$search.'%');
            });
        }

        $category = (string) $request->query('category', 'all');
        if ($category !== '' && $category !== 'all') {
            $query->where('action', 'like', '%'.$category.'%');
        }

        $severity = (string) $request->query('severity', 'all');
        if ($severity === 'warning') {
            $query->where(function (Builder $inner): void {
                $inner->where('action', 'like', '%failed%')
                    ->orWhere('action', 'like', '%denied%');
            });
        }

        $start = (string) $request->query('startDate', '');
        $end = (string) $request->query('endDate', '');
        if ($start !== '') {
            $query->whereDate('created_at', '>=', $start);
        }
        if ($end !== '') {
            $query->whereDate('created_at', '<=', $end);
        }
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $direction)->orderBy('id', $direction);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    protected function summary(array $items): array
    {
        return [
            'totalEvents' => count($items),
            'securityEvents' => count(array_filter($items, static fn (array $row): bool => ($row['category'] ?? '') === 'security')),
            'warningCriticalEvents' => count(array_filter($items, static fn (array $row): bool => ($row['severity'] ?? '') === 'warning')),
            'successfulOutcomes' => count($items),
            'deniedOutcomes' => 0,
            'previewOnlyEvents' => count($items),
            'highRiskEvents' => count(array_filter($items, static fn (array $row): bool => ($row['risk'] ?? '') === 'elevated')),
            'eventsRequiringReview' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(Request $request): array
    {
        return array_filter([
            'q' => $request->query('q', $request->query('search')),
            'category' => $request->query('category'),
            'severity' => $request->query('severity'),
            'outcome' => $request->query('outcome'),
            'startDate' => $request->query('startDate'),
            'endDate' => $request->query('endDate'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
