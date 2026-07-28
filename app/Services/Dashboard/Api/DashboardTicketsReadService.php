<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardTicketDetailResource;
use App\Http\Resources\Dashboard\DashboardTicketResource;
use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardTicketsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, facets: array<string, list<string>>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', Booking::class);

        $query = $this->scopedQuery($user)->with(['booking', 'passenger', 'supplierBooking']);
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', (int) $request->query('per_page', 25))));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(static fn (BookingTicket $row): array => DashboardTicketResource::fromModel($row))
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
                'documentTypes' => ['E-Ticket', 'NDC Fulfilment Document'],
                'issueStatuses' => ['Issued', 'Pending', 'Voided', 'Failed'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        $ticket = $this->resolveTicket($user, $id);
        if ($ticket === null) {
            return null;
        }

        Gate::authorize('view', $ticket->booking);

        return DashboardTicketDetailResource::fromModel($ticket);
    }

    protected function resolveTicket(User $user, string $id): ?BookingTicket
    {
        $query = $this->scopedQuery($user);
        if (preg_match('/^TKT-(\d+)$/i', $id, $matches) === 1) {
            return (clone $query)->whereKey((int) $matches[1])->first();
        }
        if (ctype_digit($id)) {
            return (clone $query)->whereKey((int) $id)->first();
        }

        return null;
    }

    /**
     * @return Builder<BookingTicket>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = BookingTicket::query();

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
     * @param  Builder<BookingTicket>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('ticket_number', 'like', '%'.$search.'%')
                    ->orWhere('pnr', 'like', '%'.$search.'%')
                    ->orWhereHas('booking', fn (Builder $b) => $b->where('booking_reference', 'like', '%'.$search.'%'));
            });
        }

        $supplier = (string) $request->query('supplier', '');
        if ($supplier !== '' && $supplier !== 'all') {
            $query->where('provider', 'like', '%'.$supplier.'%');
        }

        $issueState = (string) $request->query('issueStatus', '');
        if ($issueState === 'issued') {
            $query->whereIn('status', ['issued', 'ticketed']);
        } elseif ($issueState === 'pending') {
            $query->where('status', 'pending');
        } elseif ($issueState === 'voided') {
            $query->where('void_status', 'voided');
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('issued_at', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('issued_at', '<=', $request->query('dateTo'));
        }
    }

    /**
     * @param  Builder<BookingTicket>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'newest');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $column = match ($sort) {
            'issuedDate' => 'issued_at',
            'lastModified' => 'updated_at',
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
            'issueStatus' => $request->query('issueStatus'),
            'documentType' => $request->query('documentType'),
            'dateFrom' => $request->query('dateFrom'),
            'dateTo' => $request->query('dateTo'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
