<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AgentWalletTransaction;
use App\Services\Finance\MasterLedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff portal Master Ledger (same read-only dataset as platform admin).
 */
class LedgerController extends Controller
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
            'pageSubtitle' => 'Platform-wide agency wallet transactions (read-only).',
            'routePrefix' => 'staff.ledger',
        ]));
    }

    public function show(Request $request, AgentWalletTransaction $transaction): View
    {
        Gate::authorize('view', $transaction);
        $transaction = $this->ledgerService->findForShow($request->user(), $transaction);

        return view('dashboard.admin.ledger.show', [
            'transaction' => $transaction,
            'agencyName' => $transaction->agency?->name ?? '—',
            'pageTitle' => 'Master Ledger',
            'routePrefix' => 'staff.ledger',
        ]);
    }
}
