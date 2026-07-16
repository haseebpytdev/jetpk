<?php

namespace App\Http\Controllers\Agent;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Reports\BookingReportService;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Agency-scoped reports (own agency bookings and financial summary only).
 */
class AgentReportsController extends Controller
{
    public function __construct(
        protected BookingReportService $bookingReportService,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAgencyReports', Booking::class);

        $report = $this->bookingReportService->build($request->user(), $request);

        $tab = $request->string('tab')->toString();
        $allowedTabs = ['overview', 'sales', 'payments', 'bookings', 'routes', 'refunds'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        $viewData = array_merge($report, [
            'activeTab' => $tab,
            'allowedTabs' => $allowedTabs,
            'bookingStatusOptions' => array_map(fn ($status): string => $status->value, BookingStatus::cases()),
            'reportsTitle' => 'Agency Reports',
            'reportsScope' => 'agency',
        ]);

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.reports.index', $viewData);
        }

        return view(client_view('reports.index', 'agent'), $viewData);
    }
}
