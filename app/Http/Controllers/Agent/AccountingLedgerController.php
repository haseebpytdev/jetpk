<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Services\Finance\Ledger\LedgerQueryService;
use App\Services\Finance\Ledger\LedgerReconciliationDashboardService;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Agent portal — agency-scoped double-entry accounting ledger (read-only).
 */
class AccountingLedgerController extends Controller
{
    public function __construct(
        protected LedgerQueryService $queryService,
        protected LedgerReconciliationDashboardService $dashboard,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LedgerTransaction::class);

        $agent = $request->user()?->agent();
        abort_if($agent === null, 403);

        $payload = $this->queryService->buildIndexPayload($request, (int) $agent->agency_id);
        $summary = $this->dashboard->buildAgencySummary((int) $agent->agency_id);

        $viewData = array_merge($payload, [
            'summary' => $summary,
            'pageTitle' => 'Accounting Ledger',
            'routePrefix' => 'agent.accounting.ledger',
        ]);

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.accounting.ledger.index', $viewData);
        }

        return view(client_view('accounting.ledger.index', 'agent'), $viewData);
    }

    public function show(Request $request, LedgerTransaction $ledgerTransaction): View
    {
        Gate::authorize('view', $ledgerTransaction);

        $transaction = $this->queryService->findForShow($ledgerTransaction);
        $totals = $this->queryService->entryTotals($transaction);

        $viewData = [
            'transaction' => $transaction,
            'totals' => $totals,
            'pageTitle' => 'Accounting Ledger',
            'routePrefix' => 'agent.accounting.ledger',
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.accounting.ledger.show', $viewData);
        }

        return view(client_view('accounting.ledger.show', 'agent'), $viewData);
    }
}
