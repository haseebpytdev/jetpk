<?php

namespace App\Support\AgentPortal;

use App\Models\LedgerTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Agent accounting ledger JSON for Next.js dashboard (read-only).
 */
class AgentPortalAccountingLedgerPresenter
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    public function presentIndex(LengthAwarePaginator $transactions, array $summary, array $filters): array
    {
        return [
            'ok' => true,
            'summary' => [
                'wallet_balance' => (float) ($summary['wallet_balance'] ?? 0),
                'ledger_liability' => (float) ($summary['ledger_liability'] ?? 0),
                'difference' => (float) ($summary['difference'] ?? 0),
                'reconciliation_status' => (string) ($summary['reconciliation_status'] ?? ''),
                'currency' => (string) ($summary['currency'] ?? 'PKR'),
            ],
            'filters' => $filters,
            'transactions' => collect($transactions->items())
                ->map(fn (LedgerTransaction $transaction) => $this->presentTransaction($transaction))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
            'blade_fallback_url' => '/laravel/agent/accounting/ledger',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentTransaction(LedgerTransaction $transaction): array
    {
        $transaction->loadMissing(['booking:id,booking_reference']);

        return [
            'id' => $transaction->id,
            'transaction_ref' => (string) $transaction->transaction_ref,
            'transaction_type' => (string) ($transaction->transaction_type?->value ?? $transaction->transaction_type),
            'status' => (string) ($transaction->status?->value ?? $transaction->status),
            'currency' => (string) ($transaction->currency ?? 'PKR'),
            'amount_total' => (float) $transaction->amount_total,
            'debit_total' => (float) ($transaction->debit_total ?? 0),
            'credit_total' => (float) ($transaction->credit_total ?? 0),
            'description' => (string) ($transaction->description ?? ''),
            'occurred_at' => $transaction->occurred_at?->toIso8601String(),
            'posted_at' => $transaction->posted_at?->toIso8601String(),
            'booking_reference' => $transaction->booking?->booking_reference,
            'detail_url' => '/laravel/agent/accounting/ledger/'.$transaction->id,
        ];
    }
}
