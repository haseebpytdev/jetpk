<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\StreamsFinanceCsvExport;
use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Services\Finance\Ledger\LedgerQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform admin — double-entry accounting ledger (read-only, parallel layer).
 */
class AccountingLedgerController extends Controller
{
    use StreamsFinanceCsvExport;

    public function __construct(
        protected LedgerQueryService $queryService,
    ) {}

    public function index(Request $request): View|StreamedResponse
    {
        Gate::authorize('viewAny', LedgerTransaction::class);

        if ($request->string('export')->toString() === 'csv') {
            return $this->exportCsv($request);
        }

        $payload = $this->queryService->buildIndexPayload($request);

        return view('dashboard.admin.accounting.ledger.index', array_merge($payload, [
            'pageTitle' => 'Accounting Ledger',
            'pageSubtitle' => 'Double-entry ledger transactions (parallel accounting layer).',
            'routePrefix' => 'admin.accounting.ledger',
        ]));
    }

    public function show(Request $request, LedgerTransaction $ledgerTransaction): View
    {
        Gate::authorize('view', $ledgerTransaction);

        $transaction = $this->queryService->findForShow($ledgerTransaction);
        $totals = $this->queryService->entryTotals($transaction);

        return view('dashboard.admin.accounting.ledger.show', [
            'transaction' => $transaction,
            'totals' => $totals,
            'pageTitle' => 'Accounting Ledger',
            'routePrefix' => 'admin.accounting.ledger',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', LedgerTransaction::class);

        return $this->exportCsv($request);
    }

    protected function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', LedgerTransaction::class);

        return $this->streamFinanceCsv(
            $this->queryService->csvRows($request),
            'accounting-ledger',
        );
    }
}
