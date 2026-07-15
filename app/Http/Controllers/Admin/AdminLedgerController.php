<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentWalletTransaction;
use App\Services\Finance\MasterLedgerService;
use App\Support\Agencies\AgencyScopeResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Platform Master Ledger — read-only cross-agency wallet transaction history.
 */
class AdminLedgerController extends Controller
{
    public function __construct(
        protected MasterLedgerService $ledgerService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AgentWalletTransaction::class);

        $payload = $this->ledgerService->buildIndex($request->user(), $request);

        return view('dashboard.admin.ledger.index', array_merge($payload, [
            'pageTitle' => 'Master Ledger',
            'pageSubtitle' => 'Platform-wide agency wallet transactions, deposits, and adjustments.',
            'routePrefix' => 'admin.ledger',
        ]));
    }

    public function show(Request $request, AgentWalletTransaction $transaction): View
    {
        Gate::authorize('view', $transaction);
        $transaction = $this->ledgerService->findForShow($request->user(), $transaction);

        return view('dashboard.admin.ledger.show', [
            'transaction' => $transaction,
            'agencyName' => $transaction->agency !== null
                ? AgencyScopeResolver::displayName($transaction->agency)
                : '—',
            'pageTitle' => 'Master Ledger',
        ]);
    }
}
