<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Services\Finance\Ledger\LedgerReconciliationDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff portal — accounting ledger reconciliation dashboard (read-only).
 */
class AccountingReconciliationController extends Controller
{
    public function __construct(
        protected LedgerReconciliationDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewReconciliation', LedgerTransaction::class);

        return view('dashboard.staff.accounting.reconciliation.index', [
            'dashboard' => $this->dashboard->buildPlatformDashboard(),
            'pageTitle' => 'Ledger Reconciliation',
            'pageSubtitle' => 'Compare source-of-truth wallets with double-entry ledger liability.',
            'routePrefix' => 'staff.accounting',
        ]);
    }
}
