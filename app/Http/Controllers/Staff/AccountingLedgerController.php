<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Services\Finance\Ledger\LedgerQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff portal — double-entry accounting ledger (read-only).
 */
class AccountingLedgerController extends Controller
{
    public function __construct(
        protected LedgerQueryService $queryService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LedgerTransaction::class);

        $payload = $this->queryService->buildIndexPayload($request);

        return view('dashboard.staff.accounting.ledger.index', array_merge($payload, [
            'pageTitle' => 'Accounting Ledger',
            'pageSubtitle' => 'Double-entry ledger transactions (read-only).',
            'routePrefix' => 'staff.accounting.ledger',
        ]));
    }

    public function show(Request $request, LedgerTransaction $ledgerTransaction): View
    {
        Gate::authorize('view', $ledgerTransaction);

        $transaction = $this->queryService->findForShow($ledgerTransaction);
        $totals = $this->queryService->entryTotals($transaction);

        return view('dashboard.staff.accounting.ledger.show', [
            'transaction' => $transaction,
            'totals' => $totals,
            'pageTitle' => 'Accounting Ledger',
            'routePrefix' => 'staff.accounting.ledger',
        ]);
    }
}
