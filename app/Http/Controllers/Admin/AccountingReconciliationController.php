<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\StreamsFinanceCsvExport;
use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Services\Finance\Ledger\LedgerReconciliationDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform admin — accounting ledger reconciliation dashboard (read-only).
 */
class AccountingReconciliationController extends Controller
{
    use StreamsFinanceCsvExport;

    public function __construct(
        protected LedgerReconciliationDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewReconciliation', LedgerTransaction::class);

        return view('dashboard.admin.accounting.reconciliation.index', [
            'dashboard' => $this->dashboard->buildPlatformDashboard(),
            'pageTitle' => 'Ledger Reconciliation',
            'pageSubtitle' => 'Compare source-of-truth wallets with double-entry ledger liability.',
            'routePrefix' => 'admin.accounting',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewReconciliation', LedgerTransaction::class);

        return $this->streamFinanceCsv(
            $this->dashboard->csvRows(),
            'reconciliation',
        );
    }
}
