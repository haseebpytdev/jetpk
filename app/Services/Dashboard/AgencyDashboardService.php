<?php

namespace App\Services\Dashboard;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Models\AgentApplication;
use App\Models\AgentDepositRequest;
use App\Models\Booking;
use App\Models\BookingNote;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\CommunicationLog;
use App\Models\SupplierBookingAttempt;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Platform\PlatformModuleGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Agency-scoped dashboard aggregates for admin/staff home pages.
 * Admin command-center panels: {@see buildAdminCommandCenter()}.
 */
class AgencyDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $baseQuery = $this->scopedBookingsQuery($user);
        $hasLiveData = (clone $baseQuery)->exists();

        if (! $hasLiveData) {
            return [
                'hasLiveData' => false,
                'stats' => [
                    'total_bookings' => 0,
                    'pending_bookings' => 0,
                    'ticketed_bookings' => 0,
                    'unpaid_partial_bookings' => 0,
                    'gross_sales' => 0,
                    'markup_revenue' => 0,
                    'agent_sales' => 0,
                    'direct_customer_sales' => 0,
                    'cancellation_count' => 0,
                    'refund_amount_paid' => 0,
                    'pending_refund_count' => 0,
                ],
                'todayOperations' => $this->emptyOperations(),
                'revenueSnapshot' => [
                    'period_label' => 'No live bookings yet',
                    'direct_customer_sales' => 0,
                    'agent_sales' => 0,
                    'markup_revenue' => 0,
                ],
                'recentBookings' => collect(),
                'operationalKpis' => $this->emptyOperationalKpis(),
                'needsAttention' => $this->emptyNeedsAttention($user),
                'commandSummary' => $this->emptyCommandSummary($user),
                'taskActions' => $this->taskActionDefinitions(),
            ];
        }

        $pendingDeposits = $this->countPendingAgentDeposits($user);

        $stats = [
            'total_bookings' => (clone $baseQuery)->count(),
            'pending_bookings' => (clone $baseQuery)->where('status', BookingStatus::Pending)->count(),
            'ticketed_bookings' => (clone $baseQuery)->where('status', BookingStatus::Ticketed)->count(),
            'unpaid_partial_bookings' => (clone $baseQuery)->whereIn('payment_status', ['unpaid', 'partial'])->count(),
            'gross_sales' => $this->sumFareColumn($baseQuery, 'total'),
            'markup_revenue' => $this->sumFareColumn($baseQuery, 'markup'),
            'agent_sales' => $this->sumFareForChannel($baseQuery, true),
            'direct_customer_sales' => $this->sumFareForChannel($baseQuery, false),
            'cancellation_count' => (clone $baseQuery)->whereNotNull('cancellation_status')->count(),
            'refund_amount_paid' => $this->refundPaidAmount($user),
            'pending_refund_count' => $this->pendingRefundCount($user),
        ];

        $operationalCounts = [
            'needs_action' => $this->countNeedsAction($baseQuery),
            'payment_review' => $this->countPaymentReview($baseQuery),
            'supplier_pnr_pending' => $this->countSupplierPnrPending($baseQuery),
            'manual_review' => $this->countManualReview($baseQuery),
            'ticketing_pending' => $this->countTicketingPending($baseQuery),
            'unassigned' => (clone $baseQuery)->whereNull('assigned_staff_id')->count(),
            'refunds_pending' => $stats['pending_refund_count'],
            'cancellations_pending' => $this->countCancellationsPending($baseQuery),
            'today_departures' => (clone $baseQuery)->whereDate('travel_date', now()->toDateString())->count(),
            'failed_notifications' => $this->countFailedNotifications($user),
            'pending_deposits' => $pendingDeposits,
        ];

        $todayOperations = [
            [
                'title' => 'Pending bookings',
                'count' => (clone $baseQuery)->where('status', BookingStatus::Pending)->count(),
                'hint' => 'Needs follow-up',
                'route' => 'admin.bookings',
            ],
            [
                'title' => 'Fare review queue',
                'count' => (clone $baseQuery)->where('status', BookingStatus::FareReview)->count(),
                'hint' => 'Awaiting manual fare checks',
                'route' => 'admin.bookings',
            ],
            [
                'title' => 'Payment pending',
                'count' => (clone $baseQuery)->where('status', BookingStatus::PaymentPending)->count(),
                'hint' => 'Track payment completion',
                'route' => 'admin.bookings',
            ],
            [
                'title' => 'Ticketing pending',
                'count' => (clone $baseQuery)->where('status', BookingStatus::TicketingPending)->count(),
                'hint' => 'Ready for ticketing queue',
                'route' => 'admin.bookings',
            ],
            [
                'title' => 'Unassigned bookings',
                'count' => $operationalCounts['unassigned'],
                'hint' => 'Needs owner assignment',
                'route' => 'admin.bookings',
            ],
            [
                'title' => 'Internal notes today',
                'count' => $this->scopedNotesQuery($user)->whereDate('created_at', now()->toDateString())->count(),
                'hint' => 'Operational notes logged',
                'route' => 'admin.bookings',
            ],
            [
                'title' => 'Pending refunds',
                'count' => $stats['pending_refund_count'],
                'hint' => 'Awaiting approval/payout',
                'route' => 'admin.bookings',
            ],
        ];

        $recentBookings = (clone $baseQuery)
            ->with(['contact', 'fareBreakdown'])
            ->orderByDesc('bookings.created_at')
            ->limit(6)
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'ref' => $booking->booking_reference ?: 'Draft #'.$booking->id,
                'has_reference' => filled($booking->booking_reference),
                'preview_query' => $booking->booking_reference ?: (string) $booking->id,
                'customer' => $booking->contact?->email ?: 'Guest',
                'route' => $booking->route ?: '—',
                'airline' => $booking->airline ?: '—',
                'status' => str_replace('_', ' ', (string) $booking->status->value),
                'payment_status' => str_replace('_', ' ', (string) ($booking->payment_status ?? 'unpaid')),
                'amount_pkr' => (float) ($booking->fareBreakdown?->total ?? 0),
                'created_at' => $booking->created_at?->format('Y-m-d H:i') ?? '-',
            ]);

        return [
            'hasLiveData' => true,
            'stats' => $stats,
            'todayOperations' => $todayOperations,
            'revenueSnapshot' => [
                'period_label' => 'Live bookings to date',
                'direct_customer_sales' => $stats['direct_customer_sales'],
                'agent_sales' => $stats['agent_sales'],
                'markup_revenue' => $stats['markup_revenue'],
                'gross_sales' => $stats['gross_sales'],
            ],
            'recentBookings' => $recentBookings,
            'operationalKpis' => $this->buildOperationalKpis($operationalCounts),
            'needsAttention' => $this->buildNeedsAttention($operationalCounts),
            'commandSummary' => $this->buildCommandSummary($operationalCounts, $stats),
            'taskActions' => $this->taskActionDefinitions(),
        ];
    }

    /**
     * Agency-scoped operational queue counts (mirrors admin booking queues).
     *
     * @return array{
     *     needs_action: int,
     *     payment_review: int,
     *     supplier_pnr_pending: int,
     *     ticketing_pending: int,
     *     cancellations_pending: int,
     *     refunds_pending: int,
     *     manual_review: int
     * }
     */
    public function operationalCountsForAgency(User $user): array
    {
        $baseQuery = $this->scopedBookingsQuery($user);

        return [
            'needs_action' => $this->countNeedsAction($baseQuery),
            'payment_review' => $this->countPaymentReview($baseQuery),
            'supplier_pnr_pending' => $this->countSupplierPnrPending($baseQuery),
            'ticketing_pending' => $this->countTicketingPending($baseQuery),
            'cancellations_pending' => $this->countCancellationsPending($baseQuery),
            'refunds_pending' => $this->pendingRefundCount($user),
            'manual_review' => $this->countManualReview($baseQuery),
        ];
    }

    /**
     * Admin-only command-center panels (display aggregates; no supplier API calls).
     *
     * @return array<string, mixed>
     */
    public function buildAdminCommandCenter(User $user): array
    {
        $baseQuery = $this->scopedBookingsQuery($user);
        $hasLiveData = (clone $baseQuery)->exists();

        if (! $hasLiveData) {
            return [
                'pnrHealth' => $this->emptyPnrHealth(),
                'paymentCollection' => $this->emptyPaymentCollection(),
                'staffWorkload' => ['unassigned' => 0, 'assignments' => collect()],
                'agentPerformance' => [
                    'recent_bookings_30d' => 0,
                    'top_agents' => collect(),
                    'pending_applications' => (int) AgentApplication::query()->where('status', 'pending')->count(),
                ],
                'recentSupplierFailures' => collect(),
                'adminQuickActions' => $this->adminQuickActionDefinitions(),
            ];
        }

        return [
            'pnrHealth' => $this->buildPnrHealth($baseQuery, $user),
            'paymentCollection' => $this->buildPaymentCollection($baseQuery, $user),
            'staffWorkload' => $this->buildStaffWorkload($baseQuery, $user),
            'agentPerformance' => $this->buildAgentPerformance($baseQuery),
            'recentSupplierFailures' => $this->buildRecentSupplierFailures($user),
            'adminQuickActions' => $this->adminQuickActionDefinitions(),
        ];
    }

    protected function scopedBookingsQuery(User $user): Builder
    {
        $query = Booking::query();

        if (! $user->isPlatformAdmin()) {
            $query->where('bookings.agency_id', $user->current_agency_id);
        }

        return $query;
    }

    protected function scopedNotesQuery(User $user): Builder
    {
        $query = BookingNote::query();

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    protected function sumFareColumn(Builder $baseQuery, string $column): float
    {
        $query = clone $baseQuery;

        return (float) $query
            ->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'bookings.id')
            ->sum("fare.{$column}");
    }

    protected function sumFareForChannel(Builder $baseQuery, bool $agent): float
    {
        $query = clone $baseQuery;
        $query->leftJoin('booking_fare_breakdowns as fare', 'fare.booking_id', '=', 'bookings.id');

        if ($agent) {
            $query->whereNotNull('bookings.agent_id');
        } else {
            $query->whereNull('bookings.agent_id');
        }

        return (float) $query->sum('fare.total');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function emptyOperations(): Collection
    {
        return collect([
            ['title' => 'Pending bookings', 'count' => 0, 'hint' => 'No live bookings yet', 'route' => 'admin.bookings'],
            ['title' => 'Fare review queue', 'count' => 0, 'hint' => 'No live bookings yet', 'route' => 'admin.bookings'],
            ['title' => 'Payment pending', 'count' => 0, 'hint' => 'No live bookings yet', 'route' => 'admin.bookings'],
            ['title' => 'Ticketing pending', 'count' => 0, 'hint' => 'No live bookings yet', 'route' => 'admin.bookings'],
            ['title' => 'Unassigned bookings', 'count' => 0, 'hint' => 'No live bookings yet', 'route' => 'admin.bookings'],
            ['title' => 'Internal notes today', 'count' => 0, 'hint' => 'No live bookings yet', 'route' => 'admin.bookings'],
            ['title' => 'Pending refunds', 'count' => 0, 'hint' => 'No live bookings yet', 'route' => 'admin.bookings'],
        ]);
    }

    protected function refundPaidAmount(User $user): float
    {
        $query = BookingRefund::query()->where('status', 'paid');
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return (float) $query->sum('amount');
    }

    protected function pendingRefundCount(User $user): int
    {
        $query = BookingRefund::query()->whereIn('status', ['pending', 'approved']);
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return (int) $query->count();
    }

    /**
     * Mirror of BookingManagementController::applyQueueFilter('needs_action').
     */
    protected function countNeedsAction(Builder $baseQuery): int
    {
        return (int) (clone $baseQuery)->where(function (Builder $inner): void {
            $inner->whereIn('payment_status', ['unpaid', 'partial'])
                ->orWhereHas('payments', function (Builder $p): void {
                    $p->whereIn('status', ['submitted', 'pending']);
                })
                ->orWhereIn('supplier_booking_status', ['failed', 'manual_review'])
                ->orWhere(function (Builder $pnr): void {
                    $pnr->where('payment_status', 'paid')
                        ->where(function (Builder $missingPnr): void {
                            $missingPnr->whereNull('pnr')->orWhere('pnr', '');
                        });
                })
                ->orWhereIn('ticketing_status', ['pending', 'not_started', 'failed'])
                ->orWhereHas('cancellationRequests', function (Builder $c): void {
                    $c->whereIn('status', ['requested', 'approved']);
                })
                ->orWhereHas('refunds', function (Builder $r): void {
                    $r->whereIn('status', ['pending', 'approved']);
                });
        })->count();
    }

    protected function countPaymentReview(Builder $baseQuery): int
    {
        return (int) (clone $baseQuery)->whereIn('payment_status', ['unpaid', 'partial'])->count();
    }

    protected function countManualReview(Builder $baseQuery): int
    {
        return (int) (clone $baseQuery)->whereIn('supplier_booking_status', ['failed', 'manual_review'])->count();
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

    protected function countCancellationsPending(Builder $baseQuery): int
    {
        return (int) (clone $baseQuery)->whereHas('cancellationRequests', function (Builder $c): void {
            $c->whereIn('status', ['requested', 'approved']);
        })->count();
    }

    protected function countFailedNotifications(User $user): int
    {
        $query = CommunicationLog::query()->whereIn('status', ['failed', 'error']);
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return (int) $query->count();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    protected function buildOperationalKpis(array $counts): array
    {
        return [
            [
                'key' => 'needs_action',
                'label' => 'Needs action',
                'count' => $counts['needs_action'],
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'needs_action'],
                'tone' => 'warning',
                'icon' => 'ti-alert-triangle',
                'helper' => 'Items requiring an operator response.',
            ],
            [
                'key' => 'payment_review',
                'label' => 'Payment review',
                'count' => $counts['payment_review'],
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'payment_review'],
                'tone' => 'info',
                'icon' => 'ti-cash',
                'helper' => 'Unpaid or partial balances.',
            ],
            [
                'key' => 'supplier_pnr_pending',
                'label' => 'Supplier / PNR pending',
                'count' => $counts['supplier_pnr_pending'],
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'supplier_pnr'],
                'tone' => 'primary',
                'icon' => 'ti-plug-connected',
                'helper' => 'Paid bookings awaiting a PNR.',
            ],
            [
                'key' => 'manual_review',
                'label' => 'Manual review',
                'count' => $counts['manual_review'],
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'supplier_pnr'],
                'tone' => 'danger',
                'icon' => 'ti-alert-circle',
                'helper' => 'Supplier failures needing staff review.',
            ],
            [
                'key' => 'cancellations_pending',
                'label' => 'Cancellations pending',
                'count' => $counts['cancellations_pending'],
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'cancellations'],
                'tone' => 'warning',
                'icon' => 'ti-ban',
                'helper' => 'Cancellation requests in progress.',
            ],
            [
                'key' => 'refunds_pending',
                'label' => 'Refunds pending',
                'count' => $counts['refunds_pending'],
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'refunds'],
                'tone' => 'danger',
                'icon' => 'ti-receipt-refund',
                'helper' => 'Awaiting approval or payout.',
            ],
            [
                'key' => 'ticketing_pending',
                'label' => 'Ticketing pending',
                'count' => $counts['ticketing_pending'],
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'ticketing'],
                'tone' => 'success',
                'icon' => 'ti-ticket',
                'helper' => 'Ready for ticket issuance (queue only).',
            ],
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    protected function buildNeedsAttention(array $counts): array
    {
        return [
            [
                'key' => 'pending_deposits',
                'label' => 'Pending deposits',
                'count' => $counts['pending_deposits'] ?? 0,
                'helper' => 'Agency fund-load requests awaiting approval.',
                'route' => 'admin.agent-deposits.index',
                'route_params' => ['status' => AgentDepositRequestStatus::Submitted->value],
            ],
            [
                'key' => 'unassigned',
                'label' => 'Unassigned bookings',
                'count' => $counts['unassigned'],
                'helper' => 'Assign an owner to keep work flowing.',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'all'],
            ],
            [
                'key' => 'payment_review',
                'label' => 'Payment review',
                'count' => $counts['payment_review'],
                'helper' => 'Confirm payment proofs and balances.',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'payment_review'],
            ],
            [
                'key' => 'supplier_pnr_pending',
                'label' => 'Supplier PNR pending',
                'count' => $counts['supplier_pnr_pending'],
                'helper' => 'Create or attach a supplier PNR.',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'supplier_pnr'],
            ],
            [
                'key' => 'ticketing_pending',
                'label' => 'Ticketing pending',
                'count' => $counts['ticketing_pending'],
                'helper' => 'Issue tickets for ready bookings.',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'ticketing'],
            ],
            [
                'key' => 'cancellations_pending',
                'label' => 'Cancellation requests',
                'count' => $counts['cancellations_pending'],
                'helper' => 'Requests awaiting decision.',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'cancellations'],
            ],
            [
                'key' => 'refunds_pending',
                'label' => 'Refund requests',
                'count' => $counts['refunds_pending'],
                'helper' => 'Approve or pay out approved refunds.',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'refunds'],
            ],
            [
                'key' => 'failed_notifications',
                'label' => 'Failed notifications',
                'count' => $counts['failed_notifications'],
                'helper' => 'Communications that need a retry.',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'all'],
            ],
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    protected function buildCommandSummary(array $counts, array $stats): array
    {
        return [
            'needs_action' => $counts['needs_action'],
            'payment_review' => $counts['payment_review'],
            'ticketing_pending' => $counts['ticketing_pending'],
            'today_departures' => $counts['today_departures'],
            'pending_deposits' => $counts['pending_deposits'] ?? 0,
            'gross_sales' => (float) ($stats['gross_sales'] ?? 0),
        ];
    }

    protected function countPendingAgentDeposits(User $user): int
    {
        $query = AgentDepositRequest::query()
            ->where('status', AgentDepositRequestStatus::Submitted);

        if (! $user->isPlatformAdmin()) {
            if ($user->current_agency_id === null) {
                return 0;
            }

            $query->where('agency_id', $user->current_agency_id);
        }

        return (int) $query->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function taskActionDefinitions(): array
    {
        return [
            [
                'key' => 'review_new_bookings',
                'label' => 'Review new bookings',
                'helper' => 'Triage incoming work',
                'icon' => 'ti-inbox',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'needs_action'],
            ],
            [
                'key' => 'record_payment',
                'label' => 'Record payment',
                'helper' => 'Verify proofs & balances',
                'icon' => 'ti-cash',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'payment_review'],
            ],
            [
                'key' => 'create_supplier_pnr',
                'label' => 'Create supplier PNR',
                'helper' => 'Push paid bookings to suppliers',
                'icon' => 'ti-plug-connected',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'supplier_pnr'],
            ],
            [
                'key' => 'issue_tickets',
                'label' => 'Issue tickets',
                'helper' => 'Confirm tickets for ready bookings',
                'icon' => 'ti-ticket',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'ticketing'],
            ],
            [
                'key' => 'generate_invoices',
                'label' => 'Generate invoices',
                'helper' => 'Bookings missing invoices',
                'icon' => 'ti-file-invoice',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'invoices'],
            ],
            [
                'key' => 'handle_refunds',
                'label' => 'Handle refunds',
                'helper' => 'Approve or pay out refunds',
                'icon' => 'ti-receipt-refund',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'refunds'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function emptyOperationalKpis(): array
    {
        return $this->buildOperationalKpis([
            'needs_action' => 0,
            'payment_review' => 0,
            'supplier_pnr_pending' => 0,
            'manual_review' => 0,
            'cancellations_pending' => 0,
            'refunds_pending' => 0,
            'ticketing_pending' => 0,
            'unassigned' => 0,
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    protected function buildPnrHealth(Builder $baseQuery, User $user): array
    {
        $pnrCreated = (int) (clone $baseQuery)
            ->whereNotNull('pnr')
            ->where('pnr', '<>', '')
            ->count();

        $pnrMissing = (int) (clone $baseQuery)
            ->where('payment_status', 'paid')
            ->where(function (Builder $missingPnr): void {
                $missingPnr->whereNull('pnr')->orWhere('pnr', '');
            })
            ->count();

        $itinerarySynced = (int) (clone $baseQuery)
            ->where('meta->pnr_itinerary_sync->status', 'synced')
            ->count();

        $itinerarySyncBlocked = (int) (clone $baseQuery)
            ->whereIn('meta->pnr_itinerary_sync->status', ['blocked', 'failed'])
            ->count();

        $manualReview = $this->countManualReview($baseQuery);

        $recentFailedAttempts = (int) $this->scopedSupplierAttemptsQuery($user)
            ->where('status', 'failed')
            ->where('attempted_at', '>=', now()->subDays(7))
            ->count();

        return [
            'pnr_created' => $pnrCreated,
            'pnr_missing' => $pnrMissing,
            'itinerary_synced' => $itinerarySynced,
            'itinerary_sync_blocked' => $itinerarySyncBlocked,
            'manual_review' => $manualReview,
            'recent_supplier_failures_7d' => $recentFailedAttempts,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    protected function buildPaymentCollection(Builder $baseQuery, User $user): array
    {
        $unpaidBookings = (int) (clone $baseQuery)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->count();

        $pendingProof = (int) $this->scopedBookingPaymentsQuery($user)
            ->where('status', BookingPaymentStatus::Submitted)
            ->count();

        $verifiedPayments = (int) $this->scopedBookingPaymentsQuery($user)
            ->where('status', BookingPaymentStatus::Verified)
            ->count();

        $outstanding = (float) (clone $baseQuery)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->sum(DB::raw('COALESCE(balance_due, 0)'));

        return [
            'unpaid_bookings' => $unpaidBookings,
            'pending_proof' => $pendingProof,
            'verified_payments' => $verifiedPayments,
            'outstanding_balance' => $outstanding,
        ];
    }

    /**
     * @return array{unassigned: int, assignments: Collection<int, array<string, mixed>>}
     */
    protected function buildStaffWorkload(Builder $baseQuery, User $user): array
    {
        $unassigned = (int) (clone $baseQuery)->whereNull('assigned_staff_id')->count();

        $rows = (clone $baseQuery)
            ->whereNotNull('assigned_staff_id')
            ->selectRaw('assigned_staff_id, COUNT(*) as booking_count')
            ->groupBy('assigned_staff_id')
            ->orderByDesc('booking_count')
            ->limit(8)
            ->get();

        $staffIds = $rows->pluck('assigned_staff_id')->filter()->all();
        $staffNames = User::query()
            ->whereIn('id', $staffIds)
            ->where('account_type', AccountType::Staff)
            ->pluck('name', 'id');

        $assignments = $rows->map(function ($row) use ($staffNames): array {
            $staffId = (int) $row->assigned_staff_id;

            return [
                'staff_id' => $staffId,
                'staff_name' => (string) ($staffNames[$staffId] ?? 'Staff #'.$staffId),
                'booking_count' => (int) $row->booking_count,
            ];
        });

        return [
            'unassigned' => $unassigned,
            'assignments' => $assignments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAgentPerformance(Builder $baseQuery): array
    {
        $recentBookings30d = (int) (clone $baseQuery)
            ->whereNotNull('bookings.agent_id')
            ->where('bookings.created_at', '>=', now()->subDays(30))
            ->count();

        $topAgents = (clone $baseQuery)
            ->whereNotNull('bookings.agent_id')
            ->leftJoin('agents', 'agents.id', '=', 'bookings.agent_id')
            ->leftJoin('users as agent_users', 'agent_users.id', '=', 'agents.user_id')
            ->selectRaw('bookings.agent_id as agent_id')
            ->selectRaw('agents.code as agent_code')
            ->selectRaw('agent_users.name as agent_name')
            ->selectRaw('COUNT(bookings.id) as bookings')
            ->groupBy('bookings.agent_id', 'agents.code', 'agent_users.name')
            ->orderByDesc('bookings')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'agent_id' => (int) $row->agent_id,
                'agent_code' => $row->agent_code ? (string) $row->agent_code : 'AGENT-'.$row->agent_id,
                'agent_name' => $row->agent_name ? (string) $row->agent_name : 'Unknown agent',
                'bookings' => (int) $row->bookings,
            ]);

        return [
            'recent_bookings_30d' => $recentBookings30d,
            'top_agents' => $topAgents,
            'pending_applications' => (int) AgentApplication::query()->where('status', 'pending')->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildRecentSupplierFailures(User $user): Collection
    {
        return $this->scopedSupplierAttemptsQuery($user)
            ->with(['booking:id,booking_reference,agency_id'])
            ->where('status', 'failed')
            ->orderByDesc('attempted_at')
            ->limit(8)
            ->get()
            ->map(function (SupplierBookingAttempt $attempt): array {
                $booking = $attempt->booking;
                $ref = $booking?->booking_reference
                    ? (string) $booking->booking_reference
                    : ($booking ? 'Draft #'.$booking->id : '—');
                $preview = $booking?->booking_reference ?: ($booking ? (string) $booking->id : '');

                return [
                    'booking_id' => $booking?->id,
                    'booking_reference' => $ref,
                    'preview_query' => $preview,
                    'provider' => strtoupper((string) ($attempt->provider ?? '—')),
                    'error_code' => (string) ($attempt->error_code ?: '—'),
                    'reason' => $this->safeSupplierFailureReason($attempt),
                    'attempted_at' => $attempt->attempted_at?->format('Y-m-d H:i') ?? '—',
                ];
            });
    }

    protected function safeSupplierFailureReason(SupplierBookingAttempt $attempt): string
    {
        $message = trim((string) ($attempt->error_message ?? ''));
        if ($message !== '') {
            return Str::limit($message, 120);
        }

        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        foreach (['probable_issue', 'outcome_message', 'staff_review_summary'] as $key) {
            $candidate = trim((string) ($safe[$key] ?? ''));
            if ($candidate !== '') {
                return Str::limit($candidate, 120);
            }
        }

        return 'Supplier attempt failed';
    }

    /**
     * @return Builder<SupplierBookingAttempt>
     */
    protected function scopedSupplierAttemptsQuery(User $user): Builder
    {
        $query = SupplierBookingAttempt::query();
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    /**
     * @return Builder<BookingPayment>
     */
    protected function scopedBookingPaymentsQuery(User $user): Builder
    {
        return BookingPayment::query()->whereHas('booking', function (Builder $booking) use ($user): void {
            if (! $user->isPlatformAdmin()) {
                $booking->where('agency_id', $user->current_agency_id);
            }
        });
    }

    /**
     * @return array<string, int>
     */
    protected function emptyPnrHealth(): array
    {
        return [
            'pnr_created' => 0,
            'pnr_missing' => 0,
            'itinerary_synced' => 0,
            'itinerary_sync_blocked' => 0,
            'manual_review' => 0,
            'recent_supplier_failures_7d' => 0,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    protected function emptyPaymentCollection(): array
    {
        return [
            'unpaid_bookings' => 0,
            'pending_proof' => 0,
            'verified_payments' => 0,
            'outstanding_balance' => 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function adminQuickActionDefinitions(): array
    {
        return [
            [
                'key' => 'needs_action',
                'label' => 'Bookings needing action',
                'helper' => 'Triage unpaid, supplier, and ticketing work',
                'icon' => 'ti-alert-triangle',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'needs_action'],
            ],
            [
                'key' => 'payment_review',
                'label' => 'Payment review',
                'helper' => 'Unpaid balances and proof verification',
                'icon' => 'ti-cash',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'payment_review'],
            ],
            [
                'key' => 'supplier_pnr',
                'label' => 'Supplier / PNR queue',
                'helper' => 'Missing PNR and supplier follow-up',
                'icon' => 'ti-plug-connected',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'supplier_pnr'],
            ],
            [
                'key' => 'cancellations',
                'label' => 'Cancellations',
                'helper' => 'Open cancellation requests',
                'icon' => 'ti-ban',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'cancellations'],
            ],
            [
                'key' => 'refunds',
                'label' => 'Refunds',
                'helper' => 'Pending refund approvals and payouts',
                'icon' => 'ti-receipt-refund',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'refunds'],
            ],
            [
                'key' => 'agent_applications',
                'label' => 'Agent applications',
                'helper' => 'Review new agent sign-ups',
                'icon' => 'ti-user-plus',
                'route' => 'admin.agent-applications.index',
                'route_params' => [],
            ],
            [
                'key' => 'api_settings',
                'label' => 'API settings',
                'helper' => 'Supplier connections and credentials',
                'icon' => 'ti-api',
                'route' => 'admin.api-settings',
                'route_params' => [],
            ],
            [
                'key' => 'reports',
                'label' => 'Reports',
                'helper' => 'Sales, agents, and operational exports',
                'icon' => 'ti-chart-bar',
                'route' => 'admin.reports',
                'route_params' => [],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function emptyNeedsAttention(User $user): array
    {
        return $this->buildNeedsAttention([
            'pending_deposits' => $this->countPendingAgentDeposits($user),
            'unassigned' => 0,
            'payment_review' => 0,
            'supplier_pnr_pending' => 0,
            'ticketing_pending' => 0,
            'cancellations_pending' => 0,
            'refunds_pending' => 0,
            'failed_notifications' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyCommandSummary(User $user): array
    {
        return [
            'needs_action' => 0,
            'payment_review' => 0,
            'ticketing_pending' => 0,
            'today_departures' => 0,
            'pending_deposits' => $this->countPendingAgentDeposits($user),
            'gross_sales' => 0,
        ];
    }

    /**
     * Support ticket alert cards for admin/staff dashboards (S4).
     *
     * @return list<array{
     *     key: string,
     *     testid: string,
     *     label: string,
     *     count: int,
     *     helper: string,
     *     icon: string,
     *     tone: string,
     *     route: string,
     *     route_params: array<string, mixed>,
     *     cta: string
     * }>
     */
    public function buildSupportAlerts(User $user, string $portal): array
    {
        if (! PlatformModuleGate::visible('support_system')) {
            return [];
        }

        if (! Gate::forUser($user)->allows('viewAny', SupportTicket::class)) {
            return [];
        }

        if ($user->current_agency_id === null) {
            return [];
        }

        $routeName = $portal === 'staff' ? 'staff.support.tickets.index' : 'admin.support.tickets.index';

        $active = SupportTicket::query()->forAgency($user)->active();

        if ($portal === 'staff') {
            return [
                $this->supportAlertCard(
                    key: 'open',
                    testid: 'staff-support-alert-open',
                    label: 'Open support tickets',
                    count: (clone $active)->count(),
                    helper: 'Open or pending tickets in your agency.',
                    icon: 'ti-messages',
                    tone: 'blue',
                    route: $routeName,
                    routeParams: ['queue' => 'active'],
                ),
                $this->supportAlertCard(
                    key: 'assigned_to_me',
                    testid: 'staff-support-alert-assigned-to-me',
                    label: 'Assigned to me',
                    count: (clone $active)->assignedToUser($user)->count(),
                    helper: 'Active tickets assigned to you.',
                    icon: 'ti-user-check',
                    tone: 'emerald',
                    route: $routeName,
                    routeParams: ['queue' => 'active', 'assigned_to_me' => 1],
                ),
                $this->supportAlertCard(
                    key: 'unassigned',
                    testid: 'staff-support-alert-unassigned',
                    label: 'Unassigned tickets',
                    count: (clone $active)->unassigned()->count(),
                    helper: 'Active tickets with no assignee.',
                    icon: 'ti-user-question',
                    tone: 'amber',
                    route: $routeName,
                    routeParams: ['queue' => 'active', 'assigned' => 'unassigned'],
                ),
            ];
        }

        return [
            $this->supportAlertCard(
                key: 'open',
                testid: 'ota-support-alert-open',
                label: 'Open support tickets',
                count: (clone $active)->count(),
                helper: 'Open or pending agency tickets.',
                icon: 'ti-messages',
                tone: 'blue',
                route: $routeName,
                routeParams: ['queue' => 'active'],
            ),
            $this->supportAlertCard(
                key: 'unassigned',
                testid: 'ota-support-alert-unassigned',
                label: 'Unassigned tickets',
                count: (clone $active)->unassigned()->count(),
                helper: 'Active tickets with no assignee.',
                icon: 'ti-user-question',
                tone: 'amber',
                route: $routeName,
                routeParams: ['queue' => 'active', 'assigned' => 'unassigned'],
            ),
            $this->supportAlertCard(
                key: 'public',
                testid: 'ota-support-alert-public',
                label: 'Public / guest tickets',
                count: (clone $active)->publicGuest()->count(),
                helper: 'Active tickets from the public support form.',
                icon: 'ti-world',
                tone: 'violet',
                route: $routeName,
                routeParams: ['queue' => 'active', 'source' => 'public'],
            ),
            $this->supportAlertCard(
                key: 'recent',
                testid: 'ota-support-alert-recent',
                label: 'Recently created (7d)',
                count: (clone $active)->createdWithinDays(7)->count(),
                helper: 'Active tickets created in the last 7 days.',
                icon: 'ti-clock',
                tone: 'teal',
                route: $routeName,
                routeParams: ['recent' => 7],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $routeParams
     * @return array{
     *     key: string,
     *     testid: string,
     *     label: string,
     *     count: int,
     *     helper: string,
     *     icon: string,
     *     tone: string,
     *     route: string,
     *     route_params: array<string, mixed>,
     *     cta: string
     * }
     */
    protected function supportAlertCard(
        string $key,
        string $testid,
        string $label,
        int $count,
        string $helper,
        string $icon,
        string $tone,
        string $route,
        array $routeParams,
    ): array {
        return [
            'key' => $key,
            'testid' => $testid,
            'label' => $label,
            'count' => $count,
            'helper' => $helper,
            'icon' => $icon,
            'tone' => $tone,
            'route' => $route,
            'route_params' => $routeParams,
            'cta' => 'View',
        ];
    }
}
