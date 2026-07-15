<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\StreamsFinanceCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualWalletAdjustmentRequest;
use App\Http\Requests\Admin\StoreReverseManualWalletAdjustmentRequest;
use App\Models\Agency;
use App\Models\AgentWalletTransaction;
use App\Models\LedgerTransaction;
use App\Policies\FinanceAdjustmentPolicy;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Adjustments\ManualWalletAdjustmentService;
use App\Services\Finance\Export\ManualWalletAdjustmentExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceAdjustmentController extends Controller
{
    use StreamsFinanceCsvExport;

    public function __construct(
        protected ManualWalletAdjustmentService $adjustments,
        protected FinanceAdjustmentPolicy $policy,
        protected AgentWalletService $walletService,
        protected ManualWalletAdjustmentExportService $exportService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->policy->viewAny($request->user()), 403);

        $transactions = AgentWalletTransaction::query()
            ->whereIn('type', ['manual_credit', 'manual_debit'])
            ->with(['agency', 'wallet.agent.user', 'creator'])
            ->latest('id')
            ->paginate(25);

        $reversalOfIds = [];
        foreach ($transactions as $tx) {
            $meta = is_array($tx->meta) ? $tx->meta : [];
            $reversalOf = (int) ($meta['reversal_of_wallet_transaction_id'] ?? 0);
            if ($reversalOf > 0) {
                $reversalOfIds[$tx->id] = $reversalOf;
            }
        }

        $reversedOriginalIds = $reversalOfIds !== []
            ? array_fill_keys(array_values($reversalOfIds), true)
            : [];

        return view('dashboard.admin.finance.adjustments.index', [
            'transactions' => $transactions,
            'reversalOfIds' => $reversalOfIds,
            'reversedOriginalIds' => $reversedOriginalIds,
            'adjustments' => $this->adjustments,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->policy->viewAny($request->user()), 403);

        return $this->streamFinanceCsv(
            $this->exportService->csvRows($request),
            'manual-adjustments',
        );
    }

    public function create(Request $request): View
    {
        abort_unless($this->policy->create($request->user()), 403);

        $agencies = Agency::query()->orderBy('name')->get(['id', 'name']);
        $selectedAgencyId = (int) $request->query('agency_id', 0);
        $canonicalSummary = $selectedAgencyId > 0
            ? $this->walletService->canonicalWalletSummary($selectedAgencyId)
            : null;

        return view('dashboard.admin.finance.adjustments.create', [
            'agencies' => $agencies,
            'selectedAgencyId' => $selectedAgencyId,
            'canonicalSummary' => $canonicalSummary,
            'reasonCategories' => ManualWalletAdjustmentService::REASON_CATEGORIES,
            'idempotencyKey' => old('idempotency_key', ManualWalletAdjustmentService::generateIdempotencyKey()),
        ]);
    }

    public function store(StoreManualWalletAdjustmentRequest $request): RedirectResponse
    {
        abort_unless($this->policy->create($request->user()), 403);

        try {
            $result = $this->adjustments->apply(
                agency: $request->agency(),
                wallet: $request->resolvedWallet(),
                adjustmentType: (string) $request->input('adjustment_type'),
                amount: (float) $request->input('amount'),
                reason: (string) $request->input('adjustment_reason'),
                note: $request->filled('adjustment_note') ? (string) $request->input('adjustment_note') : null,
                actor: $request->user(),
                idempotencyKey: (string) $request->input('idempotency_key'),
                request: $request,
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['adjustment' => $exception->getMessage()]);
        }

        $status = ($result['idempotent_replay'] ?? false) ? 'adjustment-existing' : 'adjustment-created';

        return redirect()
            ->route('admin.finance.adjustments.show', $result['wallet_transaction'])
            ->with('status', $status);
    }

    public function show(Request $request, AgentWalletTransaction $walletTransaction): View
    {
        abort_unless($this->policy->view($request->user(), $walletTransaction), 403);

        $walletTransaction->load(['agency', 'wallet.agent.user', 'creator', 'approver']);

        $ledgerTransaction = LedgerTransaction::query()
            ->where('source_type', $walletTransaction->getMorphClass())
            ->where('source_id', $walletTransaction->id)
            ->first();

        $reversalTransaction = $this->adjustments->findReversalFor($walletTransaction);
        $reversalLedgerTransaction = null;
        if ($reversalTransaction !== null) {
            $reversalLedgerTransaction = LedgerTransaction::query()
                ->where('source_type', $reversalTransaction->getMorphClass())
                ->where('source_id', $reversalTransaction->id)
                ->first();
        }

        $originalTransaction = null;
        $originalLedgerTransaction = null;
        if ($this->adjustments->isReversalTransaction($walletTransaction)) {
            $meta = is_array($walletTransaction->meta) ? $walletTransaction->meta : [];
            $originalId = (int) ($meta['reversal_of_wallet_transaction_id'] ?? 0);
            if ($originalId > 0) {
                $originalTransaction = AgentWalletTransaction::query()->find($originalId);
                if ($originalTransaction !== null) {
                    $originalLedgerTransaction = LedgerTransaction::query()
                        ->where('source_type', $originalTransaction->getMorphClass())
                        ->where('source_id', $originalTransaction->id)
                        ->first();
                }
            }
        }

        return view('dashboard.admin.finance.adjustments.show', [
            'transaction' => $walletTransaction,
            'ledgerTransaction' => $ledgerTransaction,
            'reversalTransaction' => $reversalTransaction,
            'reversalLedgerTransaction' => $reversalLedgerTransaction,
            'originalTransaction' => $originalTransaction,
            'originalLedgerTransaction' => $originalLedgerTransaction,
            'canReverse' => $this->policy->reverse($request->user(), $walletTransaction),
        ]);
    }

    public function reverseConfirm(Request $request, AgentWalletTransaction $walletTransaction): View
    {
        abort_unless($this->policy->reverse($request->user(), $walletTransaction), 403);

        $walletTransaction->load(['agency', 'wallet.agent.user', 'creator']);

        return view('dashboard.admin.finance.adjustments.reverse', [
            'transaction' => $walletTransaction,
        ]);
    }

    public function reverse(StoreReverseManualWalletAdjustmentRequest $request, AgentWalletTransaction $walletTransaction): RedirectResponse
    {
        abort_unless($this->policy->reverse($request->user(), $walletTransaction), 403);

        try {
            $result = $this->adjustments->reverse(
                original: $walletTransaction,
                reversalReason: (string) $request->input('reversal_reason'),
                actor: $request->user(),
                request: $request,
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['reversal' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.finance.adjustments.show', $result['original'])
            ->with('status', 'adjustment-reversed');
    }
}
