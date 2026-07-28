<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardPnrOrderDetailResource;
use App\Http\Resources\Dashboard\DashboardPnrOrderResource;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardPnrOrdersReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, facets: array<string, list<string>>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', Booking::class);

        $query = $this->scopedQuery($user)->with(['booking.passengers', 'booking.contact', 'booking.agent.user', 'supplierConnection']);
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', (int) $request->query('per_page', 25))));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(static fn (SupplierBooking $row): array => DashboardPnrOrderResource::fromModel($row))
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
                'channels' => $allForFacets->map(static fn (SupplierBooking $r): string => DashboardPnrOrderResource::fromModel($r)['channel'])->unique()->values()->all(),
                'recordTypes' => $allForFacets->map(static fn (SupplierBooking $r): string => DashboardPnrOrderResource::fromModel($r)['referenceType'])->unique()->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        $record = $this->resolveRecord($user, $id);
        if ($record === null) {
            return null;
        }

        Gate::authorize('view', $record->booking);

        return DashboardPnrOrderDetailResource::fromModel($record);
    }

    protected function resolveRecord(User $user, string $id): ?SupplierBooking
    {
        $query = $this->scopedQuery($user);
        if (preg_match('/^PNR-(\d+)$/i', $id, $matches) === 1) {
            return (clone $query)->whereKey((int) $matches[1])->first();
        }
        if (ctype_digit($id)) {
            return (clone $query)->whereKey((int) $id)->first();
        }

        return (clone $query)->where('pnr', $id)->orWhere('supplier_reference', $id)->first();
    }

    /**
     * @return Builder<SupplierBooking>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = SupplierBooking::query();

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        if ($user->isAgentPortalUser()) {
            $agent = $user->agent();
            if ($agent !== null) {
                $query->whereHas('booking', fn (Builder $b) => $b->where('agent_id', $agent->id));
            }
        }

        return $query;
    }

    /**
     * @param  Builder<SupplierBooking>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('pnr', 'like', '%'.$search.'%')
                    ->orWhere('supplier_reference', 'like', '%'.$search.'%')
                    ->orWhereHas('booking', fn (Builder $b) => $b->where('booking_reference', 'like', '%'.$search.'%'));
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
            $query->whereIn('provider', ['duffel', 'pia_ndc', 'sabre']);
        }

        $recordType = (string) $request->query('recordType', '');
        if ($recordType === 'gds_pnr') {
            $query->where('provider', 'sabre');
        } elseif ($recordType === 'ndc_order') {
            $query->whereIn('provider', ['duffel', 'pia_ndc']);
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('created_at', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('created_at', '<=', $request->query('dateTo'));
        }
    }

    /**
     * @param  Builder<SupplierBooking>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'newest');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $column = match ($sort) {
            'departureDate' => 'created_at',
            'lastActivity' => 'updated_at',
            'oldest' => 'created_at',
            default => 'created_at',
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
            'recordType' => $request->query('recordType'),
            'dateFrom' => $request->query('dateFrom'),
            'dateTo' => $request->query('dateTo'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
