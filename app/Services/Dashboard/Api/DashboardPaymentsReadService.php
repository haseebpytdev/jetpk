<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardPaymentResource;
use App\Models\BookingPayment;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardPaymentsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>}
     */
    public function paginate(User $user, Request $request): array
    {
        if (! DashboardPermissionResolver::canViewPayments($user)) {
            abort(403);
        }

        $query = $this->scopedQuery($user);
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(fn (BookingPayment $payment): array => DashboardPaymentResource::fromModel($payment, $user))
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
        ];
    }

    /**
     * @return Builder<BookingPayment>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = BookingPayment::query()->with(['booking.passengers', 'booking.contact', 'booking.fareBreakdown', 'booking.agent.user', 'payer']);

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        if ($user->isAgentPortalUser()) {
            $agent = $user->agent();
            if ($agent !== null) {
                $query->whereHas('booking', fn (Builder $booking): Builder => $booking->where('agent_id', $agent->id));
            }
        }

        return $query;
    }

    /**
     * @param  Builder<BookingPayment>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('payment_reference', 'like', '%'.$search.'%')
                    ->orWhere('id', ctype_digit($search) ? (int) $search : 0)
                    ->orWhereHas('booking', function (Builder $booking) use ($search): void {
                        $booking->where('booking_reference', 'like', '%'.$search.'%')
                            ->orWhere('pnr', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = (string) $request->query('paymentStatus', $request->query('status', 'all'));
        if ($status !== '' && $status !== 'all') {
            $mapped = match ($status) {
                'paid' => 'verified',
                'pending' => 'pending',
                'failed' => 'rejected',
                default => $status,
            };
            $query->where('status', $mapped);
        }

        $method = (string) $request->query('method', 'all');
        if ($method !== '' && $method !== 'all') {
            $query->where('method', $method);
        }

        $currency = (string) $request->query('currency', '');
        if ($currency !== '' && $currency !== 'all') {
            $query->where('currency', strtoupper($currency));
        }

        $reconciliation = (string) $request->query('reconciliation', 'all');
        if ($reconciliation === 'reconciled') {
            $query->where('status', 'verified');
        } elseif ($reconciliation === 'pending_review') {
            $query->whereIn('status', ['pending', 'submitted']);
        } elseif ($reconciliation === 'disputed') {
            $query->where('status', 'rejected');
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('created_at', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('created_at', '<=', $request->query('dateTo'));
        }
    }

    /**
     * @param  Builder<BookingPayment>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'transactionDate');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $column = match ($sort) {
            'paymentId' => 'id',
            'grossAmount', 'netAmount' => 'amount',
            'lastUpdated' => 'updated_at',
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
            'paymentStatus' => $request->query('paymentStatus', $request->query('status')),
            'method' => $request->query('method'),
            'currency' => $request->query('currency'),
            'reconciliation' => $request->query('reconciliation'),
            'dateFrom' => $request->query('dateFrom'),
            'dateTo' => $request->query('dateTo'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
