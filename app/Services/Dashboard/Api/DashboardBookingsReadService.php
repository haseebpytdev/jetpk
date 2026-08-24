<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardBookingDetailResource;
use App\Http\Resources\Dashboard\DashboardBookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Support\Branding\PlatformBrandingResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardBookingsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, facets: array<string, list<string>>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', Booking::class);

        $query = $this->scopedQuery($user);
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', (int) $request->query('per_page', 25))));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(static fn (Booking $booking): array => DashboardBookingResource::fromModel($booking))
            ->values()
            ->all();

        $allForFacets = (clone $this->scopedQuery($user))->limit(500)->get();
        $suppliers = $allForFacets->map(static fn (Booking $b): string => DashboardBookingResource::fromModel($b)['supplier'])->unique()->filter()->values()->all();
        $airlines = $allForFacets->map(static fn (Booking $b): string => (string) ($b->airline ?? ''))->unique()->filter()->values()->all();

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
                'suppliers' => array_values($suppliers),
                'airlines' => array_values($airlines),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        $booking = $this->resolveBooking($user, $id);
        if ($booking === null) {
            return null;
        }

        Gate::authorize('view', $booking);

        return DashboardBookingDetailResource::fromModel($booking, $user);
    }

    protected function resolveBooking(User $user, string $id): ?Booking
    {
        $query = $this->scopedQuery($user);
        if (ctype_digit($id)) {
            return (clone $query)->whereKey((int) $id)->first();
        }

        $candidates = PlatformBrandingResolver::lookupReferenceCandidates($id);

        return (clone $query)->where(function (Builder $inner) use ($id, $candidates): void {
            $inner->where('booking_reference', $id);
            if ($candidates !== [] && $candidates !== [$id]) {
                $inner->orWhereIn('booking_reference', $candidates);
            }
        })->first();
    }

    /**
     * @return Builder<Booking>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = Booking::query()
            ->with(['passengers', 'contact', 'fareBreakdown', 'agent.user', 'verifiedPayments', 'latestSupplierBooking']);

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        if ($user->isAgentPortalUser()) {
            $agent = $user->agent();
            if ($agent !== null) {
                $query->where('agent_id', $agent->id);
            }
        }

        return $query;
    }

    /**
     * @param  Builder<Booking>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $candidates = PlatformBrandingResolver::lookupReferenceCandidates($search);
            $query->where(function (Builder $inner) use ($search, $candidates): void {
                $inner->where('booking_reference', 'like', '%'.$search.'%');
                if ($candidates !== [] && $candidates !== [$search]) {
                    $inner->orWhereIn('booking_reference', $candidates);
                }
                $inner->orWhere('pnr', 'like', '%'.$search.'%')
                    ->orWhereHas('passengers', function (Builder $p) use ($search): void {
                        $p->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('contact', function (Builder $c) use ($search): void {
                        $c->where('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $this->mapStatusFilter($status));
        }

        $payment = (string) $request->query('payment', $request->query('paymentStatus', 'all'));
        if ($payment !== '' && $payment !== 'all') {
            $query->where('payment_status', $payment === 'pending' ? 'submitted' : $payment);
        }

        $supplier = (string) $request->query('supplier', '');
        if ($supplier !== '' && $supplier !== 'all') {
            $query->where(function (Builder $inner) use ($supplier): void {
                $inner->where('supplier', 'like', '%'.$supplier.'%')
                    ->orWhere('meta->supplier_provider', 'like', '%'.$supplier.'%');
            });
        }

        if ($request->filled('bookingDateFrom')) {
            $query->whereDate('created_at', '>=', $request->query('bookingDateFrom'));
        }
        if ($request->filled('bookingDateTo')) {
            $query->whereDate('created_at', '<=', $request->query('bookingDateTo'));
        }
        if ($request->filled('departureDateFrom')) {
            $query->whereDate('travel_date', '>=', $request->query('departureDateFrom'));
        }
        if ($request->filled('departureDateTo')) {
            $query->whereDate('travel_date', '<=', $request->query('departureDateTo'));
        }

        $channel = (string) $request->query('channel', '');
        if ($channel === 'ndc') {
            $query->where(function (Builder $inner): void {
                $inner->where('supplier', 'like', '%duffel%')
                    ->orWhere('meta->supplier_provider', 'like', '%duffel%')
                    ->orWhere('meta->supplier_provider', 'like', '%ndc%');
            });
        } elseif ($channel === 'gds') {
            $query->where(function (Builder $inner): void {
                $inner->where('supplier', 'not like', '%duffel%')
                    ->where(function (Builder $nested): void {
                        $nested->whereNull('meta->supplier_provider')
                            ->orWhere('meta->supplier_provider', 'not like', '%duffel%');
                    });
            });
        }

        $queue = (string) $request->query('queue', '');
        if ($queue !== '' && $queue !== 'all') {
            $this->applyQueueFilter($query, $queue);
        }
    }

    /**
     * Mirror of BookingManagementController::applyQueueFilter for Next operator queues.
     *
     * @param  Builder<Booking>  $q
     */
    protected function applyQueueFilter(Builder $q, string $queue): void
    {
        match ($queue) {
            'needs_action' => $q->where(function (Builder $inner): void {
                $inner->whereIn('payment_status', ['unpaid', 'partial'])
                    ->orWhereHas('payments', function (Builder $p): void {
                        $p->whereIn('status', ['submitted', 'pending']);
                    })
                    ->orWhereIn('supplier_booking_status', ['failed', 'manual_review'])
                    ->orWhere(function (Builder $pnr): void {
                        $pnr->where('payment_status', 'paid')
                            ->where(function (Builder $missingPnr): void {
                                $missingPnr->whereNull('pnr')
                                    ->orWhere('pnr', '');
                            });
                    })
                    ->orWhereIn('ticketing_status', ['pending', 'not_started', 'failed'])
                    ->orWhereHas('cancellationRequests', function (Builder $c): void {
                        $c->whereIn('status', ['requested', 'approved']);
                    })
                    ->orWhereHas('refunds', function (Builder $r): void {
                        $r->whereIn('status', ['pending', 'approved']);
                    });
            }),
            'payment_review' => $q->whereIn('payment_status', ['unpaid', 'partial']),
            'supplier_pnr' => $q->where(function (Builder $inner): void {
                $inner->where(function (Builder $paidNoPnr): void {
                    $paidNoPnr->where('payment_status', 'paid')
                        ->where(function (Builder $missingPnr): void {
                            $missingPnr->whereNull('pnr')
                                ->orWhere('pnr', '');
                        });
                })->orWhereIn('supplier_booking_status', ['failed', 'manual_review']);
            }),
            'ticketing' => $q->where(function (Builder $inner): void {
                $inner->where('payment_status', 'paid')
                    ->where(function (Builder $pnr): void {
                        $pnr->whereNotNull('pnr')->where('pnr', '<>', '');
                    })
                    ->where(function (Builder $notTicketed): void {
                        $notTicketed->whereNull('ticketed_at')
                            ->orWhereIn('ticketing_status', ['pending', 'not_started', 'failed']);
                    });
            }),
            'cancellations' => $q->whereHas('cancellationRequests', function (Builder $c): void {
                $c->whereIn('status', ['requested', 'approved']);
            }),
            'refunds' => $q->whereHas('refunds', function (Builder $r): void {
                $r->whereIn('status', ['pending', 'approved'])
                    ->orWhere(function (Builder $unpaid): void {
                        $unpaid->where('status', 'paid')->whereNull('paid_at');
                    });
            }),
            default => null,
        };
    }

    /**
     * @param  Builder<Booking>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'bookingDate');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $column = match ($sort) {
            'departureDate' => 'travel_date',
            'customer' => 'id',
            'route' => 'route',
            'amount' => 'id',
            'status' => 'status',
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
            'status' => $request->query('status'),
            'payment' => $request->query('payment', $request->query('paymentStatus')),
            'supplier' => $request->query('supplier'),
            'channel' => $request->query('channel'),
            'queue' => $request->query('queue'),
            'bookingDateFrom' => $request->query('bookingDateFrom'),
            'bookingDateTo' => $request->query('bookingDateTo'),
            'departureDateFrom' => $request->query('departureDateFrom'),
            'departureDateTo' => $request->query('departureDateTo'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }

    protected function mapStatusFilter(string $status): string
    {
        return match ($status) {
            'confirmed' => 'ticketed',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
            default => $status,
        };
    }
}
