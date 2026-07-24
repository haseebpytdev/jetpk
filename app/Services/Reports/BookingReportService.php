<?php

namespace App\Services\Reports;

use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingRefund;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\SupplierDiagnosticLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user, Request $request): array
    {
        $filters = $this->resolveFilters($request);

        $baseQuery = $this->scopedBookingsQuery($user);
        $this->applyFilters($baseQuery, $filters);
        $hasLiveData = (clone $baseQuery)->exists();

        if (! $hasLiveData) {
            return array_merge($this->emptyPayload($filters), [
                'supplierPerformance' => $this->buildSupplierPerformance($user, $filters),
                'documentKpis' => $this->buildDocumentKpis($user, $filters),
                'documentRows' => collect(),
                'agentKpis' => $this->buildAgentKpis($user, 0),
            ]);
        }

        $grossSalesQuery = $this->grossSalesBookingsQuery($baseQuery);

        $summary = [
            'gross_sales' => $this->sumFare((clone $grossSalesQuery), 'fare.total'),
            'net_revenue' => $this->sumNetRevenue((clone $baseQuery)),
            'total_bookings' => (clone $baseQuery)->count(),
            'ticketed_bookings' => (clone $baseQuery)->where('status', BookingStatus::Ticketed)->count(),
            'pending_bookings' => (clone $baseQuery)->where('status', BookingStatus::Pending)->count(),
            'cancelled_bookings' => (clone $baseQuery)->where('status', BookingStatus::Cancelled)->count(),
            'cancelled_booking_value' => $this->sumFare(
                (clone $baseQuery)->where('bookings.status', BookingStatus::Cancelled),
                'fare.total',
            ),
            'agent_sales' => $this->sumFare((clone $grossSalesQuery)->whereNotNull('bookings.agent_id'), 'fare.total'),
            'direct_customer_sales' => $this->sumFare((clone $grossSalesQuery)->whereNull('bookings.agent_id'), 'fare.total'),
            'refund_paid_amount' => $this->refundPaidAmount($user, $filters),
            'pending_refund_count' => $this->pendingRefundCount($user, $filters),
            'cancellation_count' => (clone $baseQuery)->whereNotNull('bookings.cancellation_status')->count(),
            'markup_revenue' => $this->sumFare((clone $baseQuery), 'fare.markup'),
            'service_fees' => $this->sumFare((clone $baseQuery), 'fare.fees'),
            'outstanding_balance' => $this->outstandingBalance($baseQuery),
            'supplier_pnr_pending' => $this->countSupplierPnrPending($baseQuery),
            'ticketing_pending' => $this->countTicketingPending($baseQuery),
            'unpaid_partial_bookings' => (clone $baseQuery)->whereIn('payment_status', ['unpaid', 'partial'])->count(),
        ];

        $monthExpr = $this->monthExpression('bookings.created_at');
        $monthlySales = (clone $grossSalesQuery)
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'bookings.id')
            ->selectRaw("{$monthExpr} as month")
            ->selectRaw('COUNT(bookings.id) as bookings')
            ->selectRaw('COALESCE(SUM(fare.total), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(COALESCE(fare.markup, 0) + COALESCE(fare.fees, 0) - COALESCE(fare.discount, 0)), 0) as net_revenue')
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('month')
            ->toBase()
            ->get()
            ->map(fn ($row): array => [
                'month' => (string) $row->month,
                'bookings' => (int) $row->bookings,
                'gross_sales' => (float) $row->gross_sales,
                'net_revenue' => (float) $row->net_revenue,
            ]);

        $topRoutes = $this->routeAggregateQuery($baseQuery)
            ->selectRaw('COUNT(route_bookings.id) as bookings')
            ->selectRaw('COALESCE(SUM(fare.total), 0) as sales')
            ->selectRaw('COALESCE(AVG(fare.total), 0) as average_ticket')
            ->orderByDesc('sales')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'route' => (string) $row->route,
                'bookings' => (int) $row->bookings,
                'sales' => (float) $row->sales,
                'average_ticket' => (float) $row->average_ticket,
            ]);

        $topAgents = (clone $baseQuery)
            ->whereNotNull('bookings.agent_id')
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'bookings.id')
            ->leftJoin('agents', 'agents.id', '=', 'bookings.agent_id')
            ->leftJoin('users as agent_users', 'agent_users.id', '=', 'agents.user_id')
            ->selectRaw('bookings.agent_id as agent_id')
            ->selectRaw('agents.code as agent_code')
            ->selectRaw('agent_users.name as agent_name')
            ->selectRaw('COUNT(bookings.id) as bookings')
            ->selectRaw('COALESCE(SUM(fare.total), 0) as sales')
            ->selectRaw('COALESCE(SUM(fare.total * COALESCE(agents.commission_percent, 0) / 100), 0) as commission')
            ->groupBy('bookings.agent_id', 'agents.code', 'agent_users.name')
            ->orderByDesc('sales')
            ->limit(10)
            ->toBase()
            ->get()
            ->map(fn ($row): array => [
                'agent_code' => $row->agent_code ? (string) $row->agent_code : 'AGENT-'.$row->agent_id,
                'agent_name' => $row->agent_name ? (string) $row->agent_name : 'Unknown Agent',
                'bookings' => (int) $row->bookings,
                'sales' => (float) $row->sales,
                'commission' => (float) $row->commission,
            ]);

        $paymentBreakdown = $this->paymentStatusAggregateQuery($baseQuery)
            ->selectRaw('COUNT(status_bookings.id) as count')
            ->selectRaw('COALESCE(SUM(fare.total), 0) as amount')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => str_replace('_', ' ', (string) $row->status),
                'count' => (int) $row->count,
                'amount' => (float) $row->amount,
            ]);

        $salesByPeriod = $this->buildSalesByPeriod($baseQuery);
        $paymentRows = $this->buildPaymentRows($baseQuery);
        $bookingPipelineCounts = $this->buildBookingPipelineCounts($baseQuery);
        $bookingPipelineRows = $this->buildBookingPipelineRows($baseQuery);
        $supplierPerformance = $this->buildSupplierPerformance($user, $filters);
        $agentPerformance = $this->buildAgentPerformance($baseQuery);
        $routePerformance = $this->buildRoutePerformance($baseQuery);
        $refundKpis = $this->buildRefundKpis($user, $filters, $baseQuery);
        $refundRows = $this->buildRefundRows($user, $filters);
        $documentKpis = $this->buildDocumentKpis($user, $filters);
        $documentRows = $this->buildDocumentRows($user, $filters);

        $financialKpis = [
            'gross_sales' => (float) $summary['gross_sales'],
            'net_revenue' => (float) $summary['net_revenue'],
            'markup_revenue' => (float) $summary['markup_revenue'],
            'service_fees' => (float) $summary['service_fees'],
            'refund_paid' => (float) $summary['refund_paid_amount'],
            'outstanding_balance' => (float) $summary['outstanding_balance'],
        ];

        $operationalKpis = [
            'total_bookings' => (int) $summary['total_bookings'],
            'pending_bookings' => (int) $summary['pending_bookings'],
            'unpaid_partial_bookings' => (int) $summary['unpaid_partial_bookings'],
            'supplier_pnr_pending' => (int) $summary['supplier_pnr_pending'],
            'ticketing_pending' => (int) $summary['ticketing_pending'],
            'cancelled_bookings' => (int) $summary['cancelled_bookings'],
        ];

        $agentKpis = $this->buildAgentKpis($user, (float) $summary['agent_sales']);

        $salesTrendChart = $this->buildSeriesFromMonthly($monthlySales, 'gross_sales');
        $bookingVolumeChart = $this->buildSeriesFromMonthly($monthlySales, 'bookings');
        $paymentStatusChart = $paymentBreakdown
            ->map(fn (array $row): array => [
                'label' => (string) $row['status'],
                'value' => (int) $row['count'],
            ])
            ->values()
            ->all();

        return [
            'hasLiveData' => true,
            'filters' => $filters,
            'summary' => $summary,
            'monthlySales' => $monthlySales,
            'topRoutes' => $topRoutes,
            'topAgents' => $topAgents,
            'paymentBreakdown' => $paymentBreakdown,
            'financialKpis' => $financialKpis,
            'operationalKpis' => $operationalKpis,
            'agentKpis' => $agentKpis,
            'salesByPeriod' => $salesByPeriod,
            'paymentRows' => $paymentRows,
            'bookingPipelineCounts' => $bookingPipelineCounts,
            'bookingPipelineRows' => $bookingPipelineRows,
            'supplierPerformance' => $supplierPerformance,
            'agentPerformance' => $agentPerformance,
            'routePerformance' => $routePerformance,
            'refundKpis' => $refundKpis,
            'refundRows' => $refundRows,
            'documentKpis' => $documentKpis,
            'documentRows' => $documentRows,
            'salesTrendChart' => $salesTrendChart,
            'bookingVolumeChart' => $bookingVolumeChart,
            'paymentStatusChart' => $paymentStatusChart,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function resolveFilters(Request $request): array
    {
        $preset = $request->string('preset')->toString();
        [$dateFrom, $dateTo] = $this->resolvePresetRange($preset, $request);

        return [
            'preset' => $preset,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'channel' => $request->string('channel')->toString() ?: 'all',
            'supplier' => $request->string('supplier')->toString() ?: 'all',
            'status' => $request->string('status')->toString() ?: '',
            'payment_status' => $request->string('payment_status')->toString() ?: '',
            'agent_id' => $request->string('agent_id')->toString() ?: '',
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function resolvePresetRange(string $preset, Request $request): array
    {
        $now = Carbon::now();

        return match ($preset) {
            'today' => [$now->copy()->startOfDay()->toDateString(), $now->copy()->endOfDay()->toDateString()],
            '7d' => [$now->copy()->subDays(6)->toDateString(), $now->copy()->toDateString()],
            '30d' => [$now->copy()->subDays(29)->toDateString(), $now->copy()->toDateString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            default => [
                $request->string('date_from')->toString(),
                $request->string('date_to')->toString(),
            ],
        };
    }

    protected function scopedBookingsQuery(User $user): Builder
    {
        $query = Booking::query();

        if ($user->isPlatformAdmin() || $user->isStaff()) {
            return $query;
        }

        if ($user->isAgentPortalUser()) {
            $agent = $user->agent();
            if ($agent !== null) {
                $query->where('bookings.agency_id', $agent->agency_id);
            }

            return $query;
        }

        if ($user->current_agency_id !== null) {
            $query->where('bookings.agency_id', $user->current_agency_id);
        }

        return $query;
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['date_from'] !== '') {
            $query->whereDate('bookings.created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('bookings.created_at', '<=', $filters['date_to']);
        }

        if ($filters['channel'] === 'direct') {
            $query->whereNull('bookings.agent_id');
        } elseif ($filters['channel'] === 'agent') {
            $query->whereNotNull('bookings.agent_id');
        }

        if ($filters['supplier'] !== '' && $filters['supplier'] !== 'all') {
            if ($filters['supplier'] === 'none') {
                $query->whereNull('bookings.supplier');
            } else {
                $query->where('bookings.supplier', $filters['supplier']);
            }
        }

        if ($filters['status'] !== '') {
            $query->where('bookings.status', $filters['status']);
        }

        if ($filters['payment_status'] !== '') {
            $query->where('bookings.payment_status', $filters['payment_status']);
        }

        if ($filters['agent_id'] !== '' && ctype_digit($filters['agent_id'])) {
            $query->where('bookings.agent_id', (int) $filters['agent_id']);
        }
    }

    /**
     * Gross booking value: non-cancelled bookings only (excludes cancelled ticket value from sales KPIs).
     */
    protected function grossSalesBookingsQuery(Builder $baseQuery): Builder
    {
        return (clone $baseQuery)->where('bookings.status', '!=', BookingStatus::Cancelled);
    }

    protected function sumFare(Builder $query, string $column): float
    {
        return (float) $query
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'bookings.id')
            ->sum($column);
    }

    protected function sumNetRevenue(Builder $query): float
    {
        $row = $query
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'bookings.id')
            ->selectRaw('COALESCE(SUM(COALESCE(fare.markup, 0) + COALESCE(fare.fees, 0) - COALESCE(fare.discount, 0)), 0) as net_revenue')
            ->first();

        return (float) ($row?->net_revenue ?? 0);
    }

    protected function outstandingBalance(Builder $baseQuery): float
    {
        $sum = (clone $baseQuery)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->sum(DB::raw('COALESCE(balance_due, 0)'));

        return (float) $sum;
    }

    protected function countSupplierPnrPending(Builder $baseQuery): int
    {
        return (int) (clone $baseQuery)->where(function (Builder $inner): void {
            $inner->where(function (Builder $paidNoPnr): void {
                $paidNoPnr->where('payment_status', 'paid')
                    ->where(function (Builder $missingPnr): void {
                        $missingPnr->whereNull('pnr')->orWhere('pnr', '');
                    });
            })->orWhereIn('supplier_booking_status', ['failed', 'manual_review']);
        })->count();
    }

    protected function countTicketingPending(Builder $baseQuery): int
    {
        return (int) (clone $baseQuery)->where(function (Builder $inner): void {
            $inner->where('payment_status', 'paid')
                ->where(function (Builder $pnr): void {
                    $pnr->whereNotNull('pnr')->where('pnr', '<>', '');
                })
                ->where(function (Builder $notTicketed): void {
                    $notTicketed->whereNull('ticketed_at')
                        ->orWhereNotIn('ticketing_status', ['ticketed', 'issued']);
                });
        })->count();
    }

    protected function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    protected function dayExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function refundPaidAmount(User $user, array $filters): float
    {
        $query = BookingRefund::query()->where('status', 'paid');
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }
        if ($filters['date_from'] !== '') {
            $query->whereDate('paid_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $query->whereDate('paid_at', '<=', $filters['date_to']);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function pendingRefundCount(User $user, array $filters): int
    {
        $query = BookingRefund::query()->whereIn('status', ['pending', 'approved']);
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }
        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return (int) $query->count();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildSalesByPeriod(Builder $baseQuery): Collection
    {
        $monthExpr = $this->monthExpression('bookings.created_at');

        return $this->grossSalesBookingsQuery($baseQuery)
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'bookings.id')
            ->selectRaw("{$monthExpr} as period")
            ->selectRaw('COUNT(bookings.id) as bookings')
            ->selectRaw('COALESCE(SUM(fare.total), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(fare.base_fare), 0) as base_fare')
            ->selectRaw('COALESCE(SUM(fare.markup), 0) as markup')
            ->selectRaw('COALESCE(SUM(fare.fees), 0) as service_fee')
            ->selectRaw('COALESCE(AVG(fare.total), 0) as average_ticket')
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('period')
            ->toBase()
            ->get()
            ->map(fn ($row): array => [
                'period' => (string) $row->period,
                'bookings' => (int) $row->bookings,
                'gross_sales' => (float) $row->gross_sales,
                'base_fare' => (float) $row->base_fare,
                'markup' => (float) $row->markup,
                'service_fee' => (float) $row->service_fee,
                'net_revenue' => (float) $row->markup + (float) $row->service_fee,
                'average_ticket' => (float) $row->average_ticket,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildPaymentRows(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->with(['contact', 'fareBreakdown', 'payments' => function ($q): void {
                $q->orderByDesc('id')->limit(1);
            }])
            ->orderByDesc('bookings.created_at')
            ->limit(50)
            ->get()
            ->map(function (Booking $booking): array {
                $fare = $booking->fareBreakdown;
                $total = (float) ($fare?->total ?? 0);
                $paid = (float) ($booking->amount_paid ?? 0);
                $balance = (float) ($booking->balance_due ?? max(0, $total - $paid));
                $latestPayment = $booking->payments->first();

                return [
                    'id' => $booking->id,
                    'booking_ref' => $booking->booking_reference ?: ('Draft #'.$booking->id),
                    'preview_query' => $booking->booking_reference ?: (string) $booking->id,
                    'customer' => (string) ($booking->contact?->email ?? 'Guest'),
                    'route' => $booking->route ?: '—',
                    'total' => $total,
                    'paid' => $paid,
                    'balance' => $balance,
                    'payment_status' => str_replace('_', ' ', (string) ($booking->payment_status ?? 'unpaid')),
                    'method' => $latestPayment?->method?->value ? str_replace('_', ' ', $latestPayment->method->value) : '—',
                    'created_at' => $booking->created_at?->format('Y-m-d H:i') ?? '',
                ];
            });
    }

    /**
     * @return array<string, int>
     */
    protected function buildBookingPipelineCounts(Builder $baseQuery): array
    {
        $counts = [];
        foreach (BookingStatus::cases() as $status) {
            $counts[$status->value] = (int) (clone $baseQuery)->where('status', $status)->count();
        }

        return $counts;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildBookingPipelineRows(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->with(['contact', 'fareBreakdown'])
            ->orderByDesc('bookings.created_at')
            ->limit(50)
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_ref' => $booking->booking_reference ?: ('Draft #'.$booking->id),
                'preview_query' => $booking->booking_reference ?: (string) $booking->id,
                'customer' => (string) ($booking->contact?->email ?? 'Guest'),
                'route' => $booking->route ?: '—',
                'travel_date' => $booking->travel_date?->format('Y-m-d') ?? '—',
                'status' => str_replace('_', ' ', (string) ($booking->getRawOriginal('status') ?? $booking->status?->value ?? 'unknown')),
                'payment_status' => str_replace('_', ' ', (string) ($booking->payment_status ?? 'unpaid')),
                'supplier_status' => str_replace('_', ' ', (string) ($booking->supplier_booking_status ?? 'not started')),
                'ticketing_status' => str_replace('_', ' ', (string) ($booking->ticketing_status ?? 'not started')),
                'amount' => (float) ($booking->fareBreakdown?->total ?? 0),
            ]);
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildSupplierPerformance(User $user, array $filters): Collection
    {
        $providerLabels = [
            SupplierProvider::Duffel->value => 'Duffel',
            SupplierProvider::Sabre->value => 'Sabre',
            SupplierProvider::PiaNdc->value => 'PIA NDC',
            SupplierProvider::AirlineDirect->value => 'Airline Direct',
            SupplierProvider::Amadeus->value => 'Amadeus',
            SupplierProvider::Travelport->value => 'Travelport',
        ];

        $connectionsQuery = SupplierConnection::query();
        if (! $user->isPlatformAdmin()) {
            $connectionsQuery->where('agency_id', $user->current_agency_id);
        }
        $connections = $connectionsQuery->get()->keyBy(
            fn (SupplierConnection $c): string => $this->supplierProviderKey($c)
        );

        $diagnosticsQuery = SupplierDiagnosticLog::query();
        if (! $user->isPlatformAdmin()) {
            $diagnosticsQuery->where('agency_id', $user->current_agency_id);
        }
        if ($filters['date_from'] !== '') {
            $diagnosticsQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $diagnosticsQuery->whereDate('created_at', '<=', $filters['date_to']);
        }
        $diagnostics = $diagnosticsQuery->get();

        return collect(array_keys($providerLabels))->map(function (string $providerKey) use ($providerLabels, $connections, $diagnostics): array {
            $label = $providerLabels[$providerKey];
            /** @var SupplierConnection|null $connection */
            $connection = $connections->get($providerKey);

            /** @var Collection<int, SupplierDiagnosticLog> $providerDiagnostics */
            $providerDiagnostics = $diagnostics->where('provider', $providerKey);

            $searches = $providerDiagnostics->where('action', 'search')->count();
            $successfulSearches = $providerDiagnostics->where('action', 'search')->where('status', 'ok')->count();
            $validationFailures = $providerDiagnostics->where('action', 'readiness_check')->where('status', 'failed')->count();
            $offerUnavailable = $providerDiagnostics->filter(fn (SupplierDiagnosticLog $log): bool => str_contains(strtolower((string) ($log->safe_message ?? '')), 'offer_unavailable')
                || str_contains(strtolower((string) ($log->safe_message ?? '')), 'offer unavailable')
            )->count();
            $errors = $providerDiagnostics->whereIn('status', ['failed', 'error'])->count();
            $pnrCreated = $providerDiagnostics->where('action', 'create_order')->where('status', 'ok')->count();
            $ticketingSuccess = $providerDiagnostics->where('action', 'issue_ticket')->where('status', 'ok')->count();
            $lastSuccess = $providerDiagnostics->where('status', 'ok')->sortByDesc('created_at')->first();
            $lastError = $providerDiagnostics->whereIn('status', ['failed', 'error'])->sortByDesc('created_at')->first();

            $status = match (true) {
                $connection === null => 'not_configured',
                ! $connection->is_active => 'disabled',
                $errors > 0 && $successfulSearches === 0 => 'error',
                default => 'connected',
            };

            $statusLabel = match ($status) {
                'connected' => 'Connected',
                'disabled' => 'Disabled',
                'error' => 'Error',
                default => 'Not configured',
            };

            return [
                'provider_key' => $providerKey,
                'provider' => $label,
                'status' => $status,
                'status_label' => $statusLabel,
                'searches' => $searches,
                'successful_searches' => $successfulSearches,
                'validation_failures' => $validationFailures,
                'offer_unavailable' => $offerUnavailable,
                'errors' => $errors,
                'pnr_created' => $pnrCreated,
                'ticketing_success' => $ticketingSuccess,
                'last_success_at' => $lastSuccess?->created_at?->diffForHumans(),
                'last_error_at' => $lastError?->created_at?->diffForHumans(),
                'last_error_message' => $lastError ? Str::limit((string) ($lastError->safe_message ?? ''), 140) : null,
                'connection_id' => $connection?->id,
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildAgentPerformance(Builder $baseQuery): Collection
    {
        $agentRows = $this->grossSalesBookingsQuery($baseQuery)
            ->whereNotNull('bookings.agent_id')
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'bookings.id')
            ->leftJoin('agents', 'agents.id', '=', 'bookings.agent_id')
            ->leftJoin('users as agent_users', 'agent_users.id', '=', 'agents.user_id')
            ->selectRaw('bookings.agent_id as agent_id')
            ->selectRaw('agents.code as agent_code')
            ->selectRaw('agent_users.name as agent_name')
            ->selectRaw('COUNT(bookings.id) as bookings')
            ->selectRaw('COALESCE(SUM(fare.total), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(COALESCE(fare.markup, 0) + COALESCE(fare.fees, 0) - COALESCE(fare.discount, 0)), 0) as net_revenue')
            ->groupBy('bookings.agent_id', 'agents.code', 'agent_users.name')
            ->orderByDesc('gross_sales')
            ->limit(20)
            ->toBase()
            ->get();

        $agentIds = $agentRows->pluck('agent_id')->filter()->unique()->all();

        $commissionsByAgent = AgentCommissionEntry::query()
            ->whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, status, COALESCE(SUM(commission_amount), 0) as total')
            ->groupBy('agent_id', 'status')
            ->get()
            ->groupBy('agent_id');

        return $agentRows->map(function ($row) use ($commissionsByAgent): array {
            $agentCommissions = $commissionsByAgent->get($row->agent_id, collect());

            return [
                'agent_id' => (int) $row->agent_id,
                'agent_code' => $row->agent_code ? (string) $row->agent_code : 'AGENT-'.$row->agent_id,
                'agent_name' => $row->agent_name ? (string) $row->agent_name : 'Unknown Agent',
                'bookings' => (int) $row->bookings,
                'gross_sales' => (float) $row->gross_sales,
                'net_revenue' => (float) $row->net_revenue,
                'approved_commission' => (float) ($agentCommissions->firstWhere('status', 'approved')?->total ?? 0),
                'paid_commission' => (float) ($agentCommissions->firstWhere('status', 'paid')?->total ?? 0),
                'pending_commission' => (float) ($agentCommissions->firstWhere('status', 'pending')?->total ?? 0),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildRoutePerformance(Builder $baseQuery): Collection
    {
        return $this->routeAggregateQuery($baseQuery, includeAirlineFields: true)
            ->selectRaw('COUNT(route_bookings.id) as bookings')
            ->selectRaw('COALESCE(SUM(fare.total), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(COALESCE(fare.markup, 0) + COALESCE(fare.fees, 0) - COALESCE(fare.discount, 0)), 0) as net_revenue')
            ->selectRaw('COALESCE(AVG(fare.total), 0) as average_ticket')
            ->selectRaw('COUNT(DISTINCT route_bookings.airline) as airline_variety')
            ->selectRaw('SUM(CASE WHEN route_bookings.cancellation_status IS NOT NULL THEN 1 ELSE 0 END) as cancellations')
            ->orderByDesc('gross_sales')
            ->limit(20)
            ->get()
            ->map(function ($row) use ($baseQuery): array {
                $routeLabel = (string) $row->route;
                $routeMatch = $routeLabel === 'Unknown route' ? ['', null] : [$routeLabel, $routeLabel];
                $topAirlineRow = DB::query()
                    ->fromSub(
                        (clone $baseQuery)->select('bookings.id', 'bookings.route', 'bookings.airline'),
                        'route_airline_bookings'
                    )
                    ->when(
                        $routeLabel === 'Unknown route',
                        fn ($q) => $q->where(function ($inner): void {
                            $inner->whereNull('route_airline_bookings.route')
                                ->orWhere('route_airline_bookings.route', '');
                        }),
                        fn ($q) => $q->where('route_airline_bookings.route', $routeMatch[0])
                    )
                    ->whereNotNull('route_airline_bookings.airline')
                    ->where('route_airline_bookings.airline', '<>', '')
                    ->select('route_airline_bookings.airline')
                    ->selectRaw('COUNT(*) as airline_count')
                    ->groupBy('route_airline_bookings.airline')
                    ->orderByDesc('airline_count')
                    ->limit(1)
                    ->first();

                return [
                    'route' => $routeLabel,
                    'bookings' => (int) $row->bookings,
                    'gross_sales' => (float) $row->gross_sales,
                    'net_revenue' => (float) $row->net_revenue,
                    'average_ticket' => (float) $row->average_ticket,
                    'top_airline' => $topAirlineRow?->airline ? (string) $topAirlineRow->airline : '—',
                    'cancellations' => (int) $row->cancellations,
                ];
            });
    }

    /**
     * MySQL ONLY_FULL_GROUP_BY-safe route aggregates via booking subquery.
     */
    protected function routeAggregateQuery(Builder $baseQuery, bool $includeAirlineFields = false): \Illuminate\Database\Query\Builder
    {
        $routeExpr = "COALESCE(NULLIF(route_bookings.route, ''), 'Unknown route')";
        $subSelect = ['bookings.id', 'bookings.route'];
        if ($includeAirlineFields) {
            $subSelect[] = 'bookings.airline';
            $subSelect[] = 'bookings.cancellation_status';
        }

        return DB::query()
            ->fromSub(
                (clone $baseQuery)->select($subSelect),
                'route_bookings'
            )
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'route_bookings.id')
            ->selectRaw("{$routeExpr} as route")
            ->groupByRaw($routeExpr);
    }

    /**
     * MySQL ONLY_FULL_GROUP_BY-safe payment status aggregates via booking subquery.
     */
    protected function paymentStatusAggregateQuery(Builder $baseQuery): \Illuminate\Database\Query\Builder
    {
        $statusExpr = "COALESCE(NULLIF(status_bookings.payment_status, ''), 'unpaid')";

        return DB::query()
            ->fromSub(
                (clone $baseQuery)->select(['bookings.id', 'bookings.payment_status']),
                'status_bookings'
            )
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'status_bookings.id')
            ->selectRaw("{$statusExpr} as status")
            ->groupByRaw($statusExpr);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    protected function buildRefundKpis(User $user, array $filters, Builder $baseQuery): array
    {
        $refundQuery = BookingRefund::query();
        if (! $user->isPlatformAdmin()) {
            $refundQuery->where('agency_id', $user->current_agency_id);
        }
        if ($filters['date_from'] !== '') {
            $refundQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $refundQuery->whereDate('created_at', '<=', $filters['date_to']);
        }

        $cancellationRequests = (clone $baseQuery)
            ->whereHas('cancellationRequests', fn (Builder $q) => $q->whereIn('status', ['requested', 'approved']))
            ->count();
        $cancelledBookings = (clone $baseQuery)->where('status', BookingStatus::Cancelled)->count();

        return [
            'cancellation_requests' => $cancellationRequests,
            'cancelled_bookings' => $cancelledBookings,
            'refund_pending' => (int) (clone $refundQuery)->where('status', 'pending')->count(),
            'refund_approved' => (int) (clone $refundQuery)->where('status', 'approved')->count(),
            'refund_paid' => (float) (clone $refundQuery)->where('status', 'paid')->sum('amount'),
            'refund_liability' => (float) (clone $refundQuery)->whereIn('status', ['pending', 'approved'])->sum('amount'),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildRefundRows(User $user, array $filters): Collection
    {
        $query = BookingRefund::query()->with(['booking.contact', 'booking.fareBreakdown']);
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }
        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (BookingRefund $refund): array {
                $booking = $refund->booking;

                return [
                    'id' => $refund->id,
                    'booking_id' => $booking?->id,
                    'booking_ref' => $booking?->booking_reference ?: ('Draft #'.$booking?->id),
                    'preview_query' => $booking?->booking_reference ?: (string) $booking?->id,
                    'customer' => (string) ($booking?->contact?->email ?? 'Guest'),
                    'route' => $booking?->route ?: '—',
                    'paid_amount' => (float) ($booking?->amount_paid ?? 0),
                    'refund_amount' => (float) ($refund->amount ?? 0),
                    'refund_status' => str_replace('_', ' ', (string) ($refund->status?->value ?? '—')),
                    'cancellation_status' => $booking?->cancellation_status ? str_replace('_', ' ', (string) $booking->cancellation_status) : '—',
                    'created_at' => $refund->created_at?->format('Y-m-d H:i') ?? '',
                ];
            });
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, int>
     */
    protected function buildDocumentKpis(User $user, array $filters): array
    {
        $docQuery = BookingDocument::query();
        if (! $user->isPlatformAdmin()) {
            $docQuery->where('agency_id', $user->current_agency_id);
        }
        if ($filters['date_from'] !== '') {
            $docQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $docQuery->whereDate('created_at', '<=', $filters['date_to']);
        }

        $bookingsScoped = $this->scopedBookingsQuery($user);
        $this->applyFilters($bookingsScoped, $filters);

        $invoicesGenerated = (int) (clone $docQuery)
            ->where('document_type', BookingDocumentType::Invoice)
            ->where('status', BookingDocumentStatus::Generated)
            ->count();
        $bookingsTotal = (int) (clone $bookingsScoped)->count();
        $bookingsWithInvoice = (int) (clone $bookingsScoped)->whereHas('documents', function (Builder $q): void {
            $q->where('document_type', BookingDocumentType::Invoice);
        })->count();

        return [
            'invoices_generated' => $invoicesGenerated,
            'invoices_missing' => max(0, $bookingsTotal - $bookingsWithInvoice),
            'receipts_generated' => (int) (clone $docQuery)
                ->where('document_type', BookingDocumentType::PaymentReceipt)
                ->where('status', BookingDocumentStatus::Generated)
                ->count(),
            'itineraries_generated' => (int) (clone $docQuery)
                ->where('document_type', BookingDocumentType::TicketItinerary)
                ->where('status', BookingDocumentStatus::Generated)
                ->count(),
            'failed_documents' => (int) (clone $docQuery)
                ->where('status', BookingDocumentStatus::Failed)
                ->count(),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildDocumentRows(User $user, array $filters): Collection
    {
        $query = BookingDocument::query()->with('booking');
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }
        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (BookingDocument $doc): array {
                $booking = $doc->booking;

                return [
                    'id' => $doc->id,
                    'booking_id' => $booking?->id,
                    'booking_ref' => $booking?->booking_reference ?: ('Draft #'.$booking?->id),
                    'preview_query' => $booking?->booking_reference ?: (string) $booking?->id,
                    'document_type' => str_replace('_', ' ', (string) ($doc->document_type?->value ?? '—')),
                    'status' => (string) ($doc->status?->value ?? '—'),
                    'generated_at' => $doc->generated_at?->format('Y-m-d H:i') ?? '—',
                    'sent_at' => isset($doc->meta['sent_at']) ? (string) $doc->meta['sent_at'] : '—',
                ];
            });
    }

    /**
     * @return array<string, float>
     */
    protected function buildAgentKpis(User $user, float $agentSales): array
    {
        $commissionQuery = AgentCommissionEntry::query();
        if (! $user->isPlatformAdmin()) {
            $commissionQuery->where('agency_id', $user->current_agency_id);
        }

        return [
            'agent_sales' => $agentSales,
            'approved_commission' => (float) (clone $commissionQuery)->where('status', 'approved')->sum('commission_amount'),
            'paid_commission' => (float) (clone $commissionQuery)->where('status', 'paid')->sum('commission_amount'),
            'pending_commission' => (float) (clone $commissionQuery)->where('status', 'pending')->sum('commission_amount'),
        ];
    }

    /**
     * @return array<int, array{label:string,value:float}>
     */
    protected function buildSeriesFromMonthly(Collection $monthly, string $key): array
    {
        return $monthly->map(fn (array $row): array => [
            'label' => (string) ($row['month'] ?? ''),
            'value' => (float) ($row[$key] ?? 0),
        ])->values()->all();
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    protected function emptyPayload(array $filters): array
    {
        return [
            'hasLiveData' => false,
            'filters' => $filters,
            'summary' => [
                'gross_sales' => 0,
                'net_revenue' => 0,
                'total_bookings' => 0,
                'ticketed_bookings' => 0,
                'pending_bookings' => 0,
                'cancelled_bookings' => 0,
                'agent_sales' => 0,
                'direct_customer_sales' => 0,
                'refund_paid_amount' => 0,
                'pending_refund_count' => 0,
                'cancellation_count' => 0,
                'markup_revenue' => 0,
                'service_fees' => 0,
                'outstanding_balance' => 0,
                'supplier_pnr_pending' => 0,
                'ticketing_pending' => 0,
                'unpaid_partial_bookings' => 0,
            ],
            'monthlySales' => collect(),
            'topRoutes' => collect(),
            'topAgents' => collect(),
            'paymentBreakdown' => collect(),
            'financialKpis' => [
                'gross_sales' => 0, 'net_revenue' => 0, 'markup_revenue' => 0,
                'service_fees' => 0, 'refund_paid' => 0, 'outstanding_balance' => 0,
            ],
            'operationalKpis' => [
                'total_bookings' => 0, 'pending_bookings' => 0, 'unpaid_partial_bookings' => 0,
                'supplier_pnr_pending' => 0, 'ticketing_pending' => 0, 'cancelled_bookings' => 0,
            ],
            'salesByPeriod' => collect(),
            'paymentRows' => collect(),
            'bookingPipelineCounts' => array_fill_keys(array_map(fn ($s) => $s->value, BookingStatus::cases()), 0),
            'bookingPipelineRows' => collect(),
            'agentPerformance' => collect(),
            'routePerformance' => collect(),
            'refundKpis' => [
                'cancellation_requests' => 0, 'cancelled_bookings' => 0,
                'refund_pending' => 0, 'refund_approved' => 0,
                'refund_paid' => 0, 'refund_liability' => 0,
            ],
            'refundRows' => collect(),
            'salesTrendChart' => [],
            'bookingVolumeChart' => [],
            'paymentStatusChart' => [],
        ];
    }

    protected function supplierProviderKey(SupplierConnection $connection): string
    {
        $provider = $connection->getRawOriginal('provider') ?? $connection->provider;

        if ($provider instanceof SupplierProvider) {
            return $provider->value;
        }

        return strtolower(trim((string) $provider));
    }

    /**
     * Read-only PNR / manual-review digest metrics for platform-admin operational email.
     *
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     period_label: string,
     *     total_bookings: int,
     *     supplier_failed_count: int,
     *     manual_review_count: int,
     *     pending_ticketing_count: int,
     *     ticketing_failed_count: int,
     *     ticketing_not_supported_count: int,
     *     pnr_created_count: int,
     *     failed_ratio: float,
     *     top_failure_codes: array<string, int>,
     *     sample_refs: list<string>
     * }
     */
    public function buildPnrManualReviewDigestSummary(
        Agency $agency,
        CarbonInterface $start,
        CarbonInterface $end,
        int $sampleLimit = 10,
    ): array {
        $baseQuery = Booking::query()
            ->where('bookings.agency_id', $agency->id)
            ->whereBetween('bookings.created_at', [$start, $end]);

        $totalBookings = (int) (clone $baseQuery)->count();

        $supplierFailedCount = (int) (clone $baseQuery)
            ->where('supplier_booking_status', 'failed')
            ->count();

        $manualReviewCount = (int) (clone $baseQuery)
            ->where(function (Builder $inner): void {
                $inner->whereIn('supplier_booking_status', ['manual_review', 'review_required'])
                    ->orWhere('status', BookingStatus::FareReview);
            })
            ->count();

        $pendingTicketingCount = (int) (clone $baseQuery)
            ->where(function (Builder $inner): void {
                $inner->whereIn('ticketing_status', ['pending', 'ticketing_pending', 'pending_ticketing'])
                    ->where(function (Builder $pnr): void {
                        $pnr->whereNotNull('pnr')->where('pnr', '<>', '');
                    });
            })
            ->count();

        $ticketingFailedCount = (int) (clone $baseQuery)
            ->whereIn('ticketing_status', ['failed', 'error'])
            ->count();

        $ticketingNotSupportedCount = (int) (clone $baseQuery)
            ->where('ticketing_status', 'not_supported')
            ->count();

        $pnrCreatedCount = (int) (clone $baseQuery)
            ->where(function (Builder $inner): void {
                $inner->where(function (Builder $pnr): void {
                    $pnr->whereNotNull('pnr')->where('pnr', '<>', '');
                })->orWhere(function (Builder $reference): void {
                    $reference->whereNotNull('supplier_reference')->where('supplier_reference', '<>', '');
                });
            })
            ->count();

        $attentionCount = $supplierFailedCount + $manualReviewCount;
        $failedRatio = $totalBookings > 0
            ? round(($attentionCount / $totalBookings) * 100, 2)
            : 0.0;

        $topFailureCodes = SupplierBookingAttempt::query()
            ->where('agency_id', $agency->id)
            ->whereBetween('attempted_at', [$start, $end])
            ->whereIn('status', ['failed', 'needs_review'])
            ->whereNotNull('error_code')
            ->where('error_code', '<>', '')
            ->select('error_code', DB::raw('COUNT(*) as total'))
            ->groupBy('error_code')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'error_code')
            ->mapWithKeys(fn ($total, $code): array => [
                Str::headline(str_replace('_', ' ', (string) $code)) => (int) $total,
            ])
            ->all();

        $sampleRefs = (clone $baseQuery)
            ->where(function (Builder $inner): void {
                $inner->where('supplier_booking_status', 'failed')
                    ->orWhereIn('supplier_booking_status', ['manual_review', 'review_required'])
                    ->orWhereIn('ticketing_status', ['failed', 'error', 'not_supported']);
            })
            ->orderByDesc('updated_at')
            ->limit(max(1, $sampleLimit))
            ->get(['booking_reference', 'id'])
            ->map(fn (Booking $booking): string => trim((string) ($booking->booking_reference ?? '')) !== ''
                ? (string) $booking->booking_reference
                : 'Draft #'.$booking->id)
            ->take($sampleLimit)
            ->values()
            ->all();

        $periodLabel = $start->format('Y-m-d H:i').' to '.$end->format('Y-m-d H:i T');

        return [
            'period_start' => $start->toIso8601String(),
            'period_end' => $end->toIso8601String(),
            'period_label' => $periodLabel,
            'total_bookings' => $totalBookings,
            'supplier_failed_count' => $supplierFailedCount,
            'manual_review_count' => $manualReviewCount,
            'pending_ticketing_count' => $pendingTicketingCount,
            'ticketing_failed_count' => $ticketingFailedCount,
            'ticketing_not_supported_count' => $ticketingNotSupportedCount,
            'pnr_created_count' => $pnrCreatedCount,
            'failed_ratio' => $failedRatio,
            'top_failure_codes' => $topFailureCodes,
            'sample_refs' => $sampleRefs,
        ];
    }

    /**
     * Read-only agency booking activity summary for agency-admin operational email.
     *
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     period_label: string,
     *     total_bookings: int,
     *     agent_booking_count: int,
     *     direct_customer_booking_count: int,
     *     agent_staff_created_count: int,
     *     pending_count: int,
     *     confirmed_count: int,
     *     ticketed_count: int,
     *     cancelled_count: int,
     *     manual_review_count: int,
     *     pending_payment_count: int,
     *     pending_ticketing_count: int,
     *     total_booking_value: float,
     *     currency: string,
     *     sample_refs: list<string>
     * }
     */
    public function buildAgencyBookingActivitySummary(
        Agency $agency,
        CarbonInterface $start,
        CarbonInterface $end,
        int $sampleLimit = 10,
    ): array {
        $baseQuery = Booking::query()
            ->where('bookings.agency_id', $agency->id)
            ->whereBetween('bookings.created_at', [$start, $end]);

        $totalBookings = (int) (clone $baseQuery)->count();
        $agentBookingCount = (int) (clone $baseQuery)->whereNotNull('agent_id')->count();
        $directCustomerBookingCount = (int) (clone $baseQuery)->whereNull('agent_id')->count();
        $agentStaffCreatedCount = (int) (clone $baseQuery)
            ->whereNotNull('meta->creator_context->agent_staff_creator_user_id')
            ->count();

        $pendingCount = (int) (clone $baseQuery)->where('status', BookingStatus::Pending)->count();
        $confirmedCount = (int) (clone $baseQuery)->where('status', BookingStatus::Confirmed)->count();
        $ticketedCount = (int) (clone $baseQuery)->where('status', BookingStatus::Ticketed)->count();
        $cancelledCount = (int) (clone $baseQuery)->where('status', BookingStatus::Cancelled)->count();

        $manualReviewCount = (int) (clone $baseQuery)
            ->where(function (Builder $inner): void {
                $inner->whereIn('supplier_booking_status', ['manual_review', 'review_required'])
                    ->orWhere('status', BookingStatus::FareReview);
            })
            ->count();

        $pendingPaymentCount = (int) (clone $baseQuery)
            ->where(function (Builder $inner): void {
                $inner->whereIn('payment_status', ['unpaid', 'partial'])
                    ->orWhere('status', BookingStatus::PaymentPending);
            })
            ->count();

        $pendingTicketingCount = $this->countTicketingPending(clone $baseQuery);

        $totalBookingValue = $this->sumFare(clone $baseQuery, 'fare.total');

        $currencyRow = (clone $baseQuery)
            ->whereNotNull('currency')
            ->where('currency', '<>', '')
            ->select('currency')
            ->groupBy('currency')
            ->orderByRaw('COUNT(*) DESC')
            ->first();
        $currency = trim((string) ($currencyRow?->currency ?? 'PKR'));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $sampleRefs = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->limit(max(1, $sampleLimit))
            ->get(['booking_reference', 'id'])
            ->map(fn (Booking $booking): string => trim((string) ($booking->booking_reference ?? '')) !== ''
                ? (string) $booking->booking_reference
                : 'Draft #'.$booking->id)
            ->take($sampleLimit)
            ->values()
            ->all();

        $periodLabel = $start->format('Y-m-d H:i').' to '.$end->format('Y-m-d H:i T');

        return [
            'period_start' => $start->toIso8601String(),
            'period_end' => $end->toIso8601String(),
            'period_label' => $periodLabel,
            'total_bookings' => $totalBookings,
            'agent_booking_count' => $agentBookingCount,
            'direct_customer_booking_count' => $directCustomerBookingCount,
            'agent_staff_created_count' => $agentStaffCreatedCount,
            'pending_count' => $pendingCount,
            'confirmed_count' => $confirmedCount,
            'ticketed_count' => $ticketedCount,
            'cancelled_count' => $cancelledCount,
            'manual_review_count' => $manualReviewCount,
            'pending_payment_count' => $pendingPaymentCount,
            'pending_ticketing_count' => $pendingTicketingCount,
            'total_booking_value' => $totalBookingValue,
            'currency' => $currency,
            'sample_refs' => $sampleRefs,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, string>>
     */
    public function exportRows(string $type, array $report): array
    {
        switch ($type) {
            case 'sales':
                $out = [['Period', 'Bookings', 'Gross sales', 'Base fare', 'Markup', 'Service fee', 'Net revenue', 'Average ticket']];
                foreach ($report['salesByPeriod'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['period'] ?? ''),
                        (string) (int) ($row['bookings'] ?? 0),
                        (string) (int) ($row['gross_sales'] ?? 0),
                        (string) (int) ($row['base_fare'] ?? 0),
                        (string) (int) ($row['markup'] ?? 0),
                        (string) (int) ($row['service_fee'] ?? 0),
                        (string) (int) ($row['net_revenue'] ?? 0),
                        (string) (int) ($row['average_ticket'] ?? 0),
                    ];
                }

                return $out;
            case 'payments':
                $out = [['Booking', 'Customer', 'Route', 'Total', 'Paid', 'Balance', 'Payment status', 'Method', 'Created']];
                foreach ($report['paymentRows'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['booking_ref'] ?? ''),
                        (string) ($row['customer'] ?? ''),
                        (string) ($row['route'] ?? ''),
                        (string) (int) ($row['total'] ?? 0),
                        (string) (int) ($row['paid'] ?? 0),
                        (string) (int) ($row['balance'] ?? 0),
                        (string) ($row['payment_status'] ?? ''),
                        (string) ($row['method'] ?? ''),
                        (string) ($row['created_at'] ?? ''),
                    ];
                }

                return $out;
            case 'bookings':
                $out = [['Booking', 'Customer', 'Route', 'Travel date', 'Status', 'Payment', 'Supplier', 'Ticketing', 'Amount']];
                foreach ($report['bookingPipelineRows'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['booking_ref'] ?? ''),
                        (string) ($row['customer'] ?? ''),
                        (string) ($row['route'] ?? ''),
                        (string) ($row['travel_date'] ?? ''),
                        (string) ($row['status'] ?? ''),
                        (string) ($row['payment_status'] ?? ''),
                        (string) ($row['supplier_status'] ?? ''),
                        (string) ($row['ticketing_status'] ?? ''),
                        (string) (int) ($row['amount'] ?? 0),
                    ];
                }

                return $out;
            case 'agents':
                $out = [['Agent code', 'Agent', 'Bookings', 'Gross sales', 'Net revenue', 'Approved commission', 'Paid commission', 'Pending commission']];
                foreach ($report['agentPerformance'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['agent_code'] ?? ''),
                        (string) ($row['agent_name'] ?? ''),
                        (string) (int) ($row['bookings'] ?? 0),
                        (string) (int) ($row['gross_sales'] ?? 0),
                        (string) (int) ($row['net_revenue'] ?? 0),
                        (string) (int) ($row['approved_commission'] ?? 0),
                        (string) (int) ($row['paid_commission'] ?? 0),
                        (string) (int) ($row['pending_commission'] ?? 0),
                    ];
                }

                return $out;
            case 'refunds':
                $out = [['Booking', 'Customer', 'Route', 'Paid amount', 'Refund amount', 'Refund status', 'Cancellation status', 'Created']];
                foreach ($report['refundRows'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['booking_ref'] ?? ''),
                        (string) ($row['customer'] ?? ''),
                        (string) ($row['route'] ?? ''),
                        (string) (int) ($row['paid_amount'] ?? 0),
                        (string) (int) ($row['refund_amount'] ?? 0),
                        (string) ($row['refund_status'] ?? ''),
                        (string) ($row['cancellation_status'] ?? ''),
                        (string) ($row['created_at'] ?? ''),
                    ];
                }

                return $out;
            case 'supplier_diagnostics':
                $out = [['Provider', 'Status', 'Searches', 'Successful', 'Validation failures', 'Offer unavailable', 'Errors', 'PNRs created', 'Tickets issued', 'Last success', 'Last error']];
                foreach ($report['supplierPerformance'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['provider'] ?? ''),
                        (string) ($row['status_label'] ?? ''),
                        (string) (int) ($row['searches'] ?? 0),
                        (string) (int) ($row['successful_searches'] ?? 0),
                        (string) (int) ($row['validation_failures'] ?? 0),
                        (string) (int) ($row['offer_unavailable'] ?? 0),
                        (string) (int) ($row['errors'] ?? 0),
                        (string) (int) ($row['pnr_created'] ?? 0),
                        (string) (int) ($row['ticketing_success'] ?? 0),
                        (string) ($row['last_success_at'] ?? ''),
                        (string) ($row['last_error_at'] ?? ''),
                    ];
                }

                return $out;
            case 'documents':
                $out = [['Booking', 'Document type', 'Status', 'Generated at', 'Sent at']];
                foreach ($report['documentRows'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['booking_ref'] ?? ''),
                        (string) ($row['document_type'] ?? ''),
                        (string) ($row['status'] ?? ''),
                        (string) ($row['generated_at'] ?? ''),
                        (string) ($row['sent_at'] ?? ''),
                    ];
                }

                return $out;
            default:
                return [['type', 'message'], [$type, 'Unsupported']];
        }
    }
}
