<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Services\Finance\Ledger\LedgerQueryService;
use App\Services\Finance\Ledger\LedgerReconciliationDashboardService;
use App\Support\AgentPortal\AgentPortalAccountingLedgerPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Agent portal — agency-scoped double-entry accounting ledger (read-only).
 */
class AccountingLedgerController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected LedgerQueryService $queryService,
        protected LedgerReconciliationDashboardService $dashboard,
        protected AgentPortalAccountingLedgerPresenter $ledgerPresenter,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', LedgerTransaction::class);

        $agent = $request->user()?->agent();
        abort_if($agent === null, 403);

        $payload = $this->queryService->buildIndexPayload($request, (int) $agent->agency_id);
        $summary = $this->dashboard->buildAgencySummary((int) $agent->agency_id);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson(
                $this->ledgerPresenter->presentIndex(
                    $payload['transactions'],
                    $summary,
                    $payload['filters'],
                ),
            );
        }

        $viewData = array_merge($payload, [
            'summary' => $summary,
            'pageTitle' => 'Accounting Ledger',
            'routePrefix' => 'agent.accounting.ledger',
        ]);

        return view(client_view('accounting.ledger.index', 'agent'), $viewData);
    }

    public function show(Request $request, LedgerTransaction $ledgerTransaction): View|JsonResponse
    {
        Gate::authorize('view', $ledgerTransaction);

        $transaction = $this->queryService->findForShow($ledgerTransaction);
        $totals = $this->queryService->entryTotals($transaction);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson([
                'ok' => true,
                'transaction' => $this->ledgerPresenter->presentTransaction($transaction),
                'totals' => $totals,
                'blade_fallback_url' => '/laravel/agent/accounting/ledger/'.$ledgerTransaction->id,
            ]);
        }

        $viewData = [
            'transaction' => $transaction,
            'totals' => $totals,
            'pageTitle' => 'Accounting Ledger',
            'routePrefix' => 'agent.accounting.ledger',
        ];

        return view(client_view('accounting.ledger.show', 'agent'), $viewData);
    }
}
