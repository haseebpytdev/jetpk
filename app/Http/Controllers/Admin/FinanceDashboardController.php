<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\StreamsFinanceCsvExport;
use App\Http\Controllers\Controller;
use App\Policies\FinanceDashboardPolicy;
use App\Services\Finance\Dashboard\AdminFinanceDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform-admin read-only finance operations dashboard.
 */
class FinanceDashboardController extends Controller
{
    use StreamsFinanceCsvExport;

    public function __construct(
        protected AdminFinanceDashboardService $dashboard,
        protected FinanceDashboardPolicy $policy,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->policy->view($request->user()), 403);

        return view('dashboard.admin.finance.dashboard', [
            'dashboard' => $this->dashboard->build(),
            'pageTitle' => 'Finance Dashboard',
            'pageSubtitle' => 'Month-to-date wallet, ledger, and operational finance monitoring (read-only).',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->policy->view($request->user()), 403);

        return $this->streamFinanceCsv(
            $this->dashboard->csvRows(),
            'finance-dashboard',
        );
    }
}
