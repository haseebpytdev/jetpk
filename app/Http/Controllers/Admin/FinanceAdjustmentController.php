<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceAdjustmentController extends Controller
{
    use RespondsWithBackOfficeJson;
    use StreamsFinanceCsvExport;

    public function __construct(
        protected ManualWalletAdjustmentService $adjustments,
        protected FinanceAdjustmentPolicy $policy,
        protected AgentWalletService $walletService,
        protected ManualWalletAdjustmentExportService $exportService,
    ) {}

    public function index(Request $request): View|JsonResponse
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

        if ($this->wantsBackOfficeJson($request)) {
            $items = [];
            foreach ($transactions as $tx) {
                $items[] = $this->presentWalletTransactionForDashboard($request, $tx);
            }

            return $this->backOfficeJson([
                'ok' => true,
                'transactions' => $items,
                'reason_categories' => ManualWalletAdjustmentService::REASON_CATEGORIES,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'total' => $transactions->total(),
                ],
            ]);
        }

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

    public function create(Request $request): View|JsonResponse
    {
        abort_unless($this->policy->create($request->user()), 403);

        $agencies = Agency::query()->orderBy('name')->get(['id', 'name']);
        $selectedAgencyId = (int) $request->query('agency_id', 0);
        $canonicalSummary = $selectedAgencyId > 0
            ? $this->walletService->canonicalWalletSummary($selectedAgencyId)
            : null;
        $idempotencyKey = old('idempotency_key', ManualWalletAdjustmentService::generateIdempotencyKey());

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'agencies' => $agencies->map(static fn (Agency $agency): array => [
                    'id' => (string) $agency->id,
                    'name' => (string) $agency->name,
                ])->values()->all(),
                'selected_agency_id' => $selectedAgencyId > 0 ? (string) $selectedAgencyId : null,
                'canonical_summary' => $canonicalSummary,
                'reason_categories' => ManualWalletAdjustmentService::REASON_CATEGORIES,
                'idempotency_key' => $idempotencyKey,
            ]);
        }

        return view('dashboard.admin.finance.adjustments.create', [
            'agencies' => $agencies,
            'selectedAgencyId' => $selectedAgencyId,
            'canonicalSummary' => $canonicalSummary,
            'reasonCategories' => ManualWalletAdjustmentService::REASON_CATEGORIES,
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    public function store(StoreManualWalletAdjustmentRequest $request): RedirectResponse|JsonResponse
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
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($exception->getMessage(), 409, 'adjustment_blocked');
            }

            return back()
                ->withInput()
                ->withErrors(['adjustment' => $exception->getMessage()]);
        }

        $walletTransaction = $result['wallet_transaction'];
        $status = ($result['idempotent_replay'] ?? false) ? 'adjustment-existing' : 'adjustment-created';

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'idempotent_replay' => (bool) ($result['idempotent_replay'] ?? false),
                'wallet_transaction' => $this->presentWalletTransaction($walletTransaction),
            ]);
        }

        return redirect()
            ->route('admin.finance.adjustments.show', $walletTransaction)
            ->with('status', $status);
    }

    public function show(Request $request, AgentWalletTransaction $walletTransaction): View|JsonResponse
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

        $canReverse = $this->policy->reverse($request->user(), $walletTransaction);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'wallet_transaction' => $this->presentWalletTransactionForDashboard($request, $walletTransaction),
                'ledger_transaction_id' => $ledgerTransaction?->id !== null ? (string) $ledgerTransaction->id : null,
                'reversal' => $reversalTransaction !== null
                    ? $this->presentWalletTransactionForDashboard($request, $reversalTransaction)
                    : null,
                'original' => $originalTransaction !== null
                    ? $this->presentWalletTransactionForDashboard($request, $originalTransaction)
                    : null,
                'can_reverse' => $canReverse,
            ]);
        }

        return view('dashboard.admin.finance.adjustments.show', [
            'transaction' => $walletTransaction,
            'ledgerTransaction' => $ledgerTransaction,
            'reversalTransaction' => $reversalTransaction,
            'reversalLedgerTransaction' => $reversalLedgerTransaction,
            'originalTransaction' => $originalTransaction,
            'originalLedgerTransaction' => $originalLedgerTransaction,
            'canReverse' => $canReverse,
        ]);
    }

    public function reverseConfirm(Request $request, AgentWalletTransaction $walletTransaction): View|JsonResponse
    {
        abort_unless($this->policy->reverse($request->user(), $walletTransaction), 403);

        $walletTransaction->load(['agency', 'wallet.agent.user', 'creator']);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'wallet_transaction' => $this->presentWalletTransactionForDashboard($request, $walletTransaction),
                'can_reverse' => true,
            ]);
        }

        return view('dashboard.admin.finance.adjustments.reverse', [
            'transaction' => $walletTransaction,
        ]);
    }

    public function reverse(StoreReverseManualWalletAdjustmentRequest $request, AgentWalletTransaction $walletTransaction): RedirectResponse|JsonResponse
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
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($exception->getMessage(), 409, 'reversal_blocked');
            }

            return back()
                ->withInput()
                ->withErrors(['reversal' => $exception->getMessage()]);
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'original' => $this->presentWalletTransaction($result['original']),
                'reversal' => $this->presentWalletTransaction($result['wallet_transaction']),
            ]);
        }

        return redirect()
            ->route('admin.finance.adjustments.show', $result['original'])
            ->with('status', 'adjustment-reversed');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWalletTransaction(AgentWalletTransaction $transaction): array
    {
        return [
            'id' => (string) $transaction->id,
            'agency_id' => (string) $transaction->agency_id,
            'type' => $transaction->type->value,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWalletTransactionForDashboard(Request $request, AgentWalletTransaction $transaction): array
    {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $transaction->loadMissing(['agency', 'creator']);

        return array_merge($this->presentWalletTransaction($transaction), [
            'agency_name' => $transaction->agency?->name,
            'status' => $transaction->status?->value ?? (string) $transaction->status,
            'reference' => $transaction->reference,
            'note' => $transaction->description,
            'adjustment_reason' => $meta['adjustment_reason'] ?? null,
            'is_reversal' => $this->adjustments->isReversalTransaction($transaction),
            'reversal_of_wallet_transaction_id' => isset($meta['reversal_of_wallet_transaction_id'])
                ? (string) $meta['reversal_of_wallet_transaction_id']
                : null,
            'can_reverse' => $this->policy->reverse($request->user(), $transaction),
            'created_at' => $transaction->created_at?->toIso8601String(),
            'created_by' => $transaction->creator?->name,
        ]);
    }
}
