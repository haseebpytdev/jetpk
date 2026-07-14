<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerTransactionStatus;
use App\Models\AgentWallet;
use App\Models\LedgerTransaction;

/**
 * Ledger integrity checks: balance, orphans, duplicates, invalid states.
 */
class LedgerIntegrityService
{
    public function __construct(
        protected LedgerReconciliationService $reconciliation,
        protected LedgerBalanceService $balances,
    ) {}

    /**
     * @return array{passed: bool, issues: list<array<string, mixed>>}
     */
    public function checkIntegrity(): array
    {
        $issues = [];

        foreach ($this->findUnbalancedTransactions() as $issue) {
            $issues[] = $issue;
        }
        foreach ($this->findMissingEntries() as $issue) {
            $issues[] = $issue;
        }
        foreach ($this->reconciliation->findDuplicateSourcePosts() as $dup) {
            $issues[] = array_merge($dup, ['type' => 'duplicate_source']);
        }
        foreach ($this->findInvalidPostedStates() as $issue) {
            $issues[] = $issue;
        }

        return [
            'passed' => $issues === [],
            'issues' => $issues,
        ];
    }

    /**
     * @return array{passed: bool, mismatches: list<array<string, mixed>>}
     */
    public function verifyBalances(?int $agencyId = null): array
    {
        $mismatches = [];

        if ($agencyId !== null) {
            $compare = $this->reconciliation->compareWalletBalanceToLedger($agencyId);
            if (! $compare['matches']) {
                $mismatches[] = $compare;
            }
        } else {
            foreach ($this->reconciliation->reconcileWalletTransactions() as $compare) {
                if (! $compare['matches']) {
                    $mismatches[] = $compare;
                }
            }
        }

        $exposure = $this->balances->getPlatformExposure();
        $agencySum = 0.0;
        $agencyIds = AgentWallet::query()->pluck('agency_id')->unique();
        foreach ($agencyIds as $id) {
            $agencySum += $this->balances->getAgencyWalletBalance((int) $id);
        }
        $agencySum = round($agencySum, 2);

        if (abs($exposure - $agencySum) > 0.01) {
            $mismatches[] = [
                'type' => 'platform_exposure_mismatch',
                'platform_exposure' => $exposure,
                'agency_liability_sum' => $agencySum,
                'difference' => round($exposure - $agencySum, 2),
            ];
        }

        $totals = $this->balances->getPostedTotals();
        if (abs($totals['total_debit'] - $totals['total_credit']) > 0.01) {
            $mismatches[] = array_merge(['type' => 'global_imbalance'], $totals);
        }

        return [
            'passed' => $mismatches === [],
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function findUnbalancedTransactions(): array
    {
        $issues = [];
        $transactions = LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->with('entries')
            ->get();

        foreach ($transactions as $transaction) {
            $debit = round((float) $transaction->entries->sum('debit'), 2);
            $credit = round((float) $transaction->entries->sum('credit'), 2);
            if (abs($debit - $credit) > 0.01) {
                $issues[] = [
                    'type' => 'unbalanced_transaction',
                    'transaction_id' => $transaction->id,
                    'transaction_ref' => $transaction->transaction_ref,
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                ];
            }
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function findMissingEntries(): array
    {
        return LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->doesntHave('entries')
            ->get()
            ->map(fn ($tx) => [
                'type' => 'missing_entries',
                'transaction_id' => $tx->id,
                'transaction_ref' => $tx->transaction_ref,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function findInvalidPostedStates(): array
    {
        return LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->whereNull('posted_at')
            ->get()
            ->map(fn ($tx) => [
                'type' => 'invalid_posted_state',
                'transaction_id' => $tx->id,
                'transaction_ref' => $tx->transaction_ref,
            ])
            ->all();
    }
}
