<?php

namespace App\Services\Finance\Export;

use App\Models\AgentWalletTransaction;
use App\Services\Finance\Adjustments\ManualWalletAdjustmentService;
use Illuminate\Http\Request;

/**
 * Read-only CSV rows for manual wallet adjustment audit export.
 */
class ManualWalletAdjustmentExportService
{
    public function __construct(
        protected ManualWalletAdjustmentService $adjustments,
    ) {}

    /**
     * @return list<list<string|int|float|null>>
     */
    public function csvRows(Request $request, int $limit = 5000): array
    {
        $query = AgentWalletTransaction::query()
            ->whereIn('type', ['manual_credit', 'manual_debit'])
            ->with(['agency:id,name', 'wallet.agent.user', 'creator:id,name', 'approver:id,name'])
            ->latest('id');

        if ($request->filled('agency_id')) {
            $query->where('agency_id', (int) $request->input('agency_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $transactions = $query->limit($limit)->get();

        $header = [
            'id', 'agency', 'wallet_id', 'type', 'amount', 'balance_before', 'balance_after',
            'reference', 'status', 'created_by', 'approved_by', 'reason', 'note',
            'is_reversal', 'reversal_of', 'created_at',
        ];

        $rows = [$header];

        foreach ($transactions as $tx) {
            $meta = is_array($tx->meta) ? $tx->meta : [];
            $isReversal = $this->adjustments->isReversalTransaction($tx);
            $reversalOf = (int) ($meta['reversal_of_wallet_transaction_id'] ?? 0);

            $rows[] = [
                $tx->id,
                $tx->agency?->name,
                $tx->agent_wallet_id,
                $tx->type->value,
                (float) $tx->amount,
                (float) $tx->balance_before,
                (float) $tx->balance_after,
                $tx->reference,
                $tx->status->value ?? (string) $tx->status,
                $tx->creator?->name,
                $tx->approver?->name,
                $meta['adjustment_reason'] ?? $meta['reason'] ?? null,
                $meta['adjustment_note'] ?? $meta['note'] ?? null,
                $isReversal ? 'yes' : 'no',
                $reversalOf > 0 ? $reversalOf : null,
                $tx->created_at?->toIso8601String(),
            ];
        }

        return $rows;
    }
}
