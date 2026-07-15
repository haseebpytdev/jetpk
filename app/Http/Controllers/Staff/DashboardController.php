<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Dashboard\AgencyDashboardService;
use App\Support\Bookings\BookingListPresenter;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected AgencyDashboardService $agencyDashboardService,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Booking::class);

        $user = auth()->user();
        $agencyId = $user->current_agency_id;
        $baseQuery = Booking::query()->where('agency_id', $agencyId);
        $assignedQuery = (clone $baseQuery)->where('assigned_staff_id', $user->id);

        $operational = $this->agencyDashboardService->operationalCountsForAgency($user);

        $staffKpis = [
            'assigned_to_me' => (clone $assignedQuery)->count(),
            'payment_review' => $operational['payment_review'],
            'manual_review' => $operational['manual_review'],
            'cancellation_refund_pending' => $operational['cancellations_pending'] + $operational['refunds_pending'],
            'pnr_ticketing_pending' => $operational['supplier_pnr_pending'] + $operational['ticketing_pending'],
        ];

        $today = now()->toDateString();

        $todayActivity = [
            'bookings_updated' => (clone $baseQuery)->whereDate('updated_at', $today)->count(),
            'payment_proofs_pending' => (clone $baseQuery)->whereHas('payments', function ($q): void {
                $q->whereIn('status', ['submitted', 'pending']);
            })->count(),
            'cancellations_pending' => $operational['cancellations_pending'],
            'manual_review' => $operational['manual_review'],
        ];

        $recentAssigned = (clone $assignedQuery)
            ->with(['passengers', 'contact', 'agent.user', 'fareBreakdown'])
            ->orderByDesc('assigned_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking): array => array_merge(
                BookingListPresenter::toListRow($booking),
                [
                    'party_label' => $booking->agent?->user?->name
                        ? 'Agent: '.$booking->agent->user->name
                        : ($booking->contact?->email ?? 'Guest'),
                    'assigned_at' => $booking->assigned_at?->format('Y-m-d H:i') ?? '—',
                ],
            ));

        return view(client_view('index', 'staff'), [
            'role' => 'Staff',
            'agencyName' => $user->currentAgency?->name ?? 'Staff portal',
            'staffKpis' => $staffKpis,
            'operational' => $operational,
            'todayActivity' => $todayActivity,
            'recentAssigned' => $recentAssigned,
            'supportAlerts' => $this->agencyDashboardService->buildSupportAlerts($user, 'staff'),
        ]);
    }
}
