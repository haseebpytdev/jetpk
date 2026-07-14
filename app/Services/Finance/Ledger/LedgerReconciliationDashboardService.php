<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerTransactionStatus;
use App\Models\Agency;
use App\Models\AgentWallet;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Support\Agencies\AgencyPrefixService;

/**
 * Read-only reconciliation dashboard metrics for accounting ledger UI.
 */
class LedgerReconciliationDashboardService
{
    public function __construct(
        protected LedgerBalanceService $balances,
        protected LedgerReconciliationService $reconciliation,
        protected LedgerIntegrityService $integrity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPlatformDashboard(): array
    {
        $statusCounts = LedgerTransaction::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $postedCount = (int) ($statusCounts[LedgerTransactionStatus::Posted->value] ?? 0);
        $totalTransactions = (int) LedgerTransaction::query()->count();

        $unbalancedCount = count($this->countUnbalancedPosted());

        $walletTotal = round((float) AgentWallet::query()->sum('balance'), 2);
        $ledgerLiabilityTotal = round($this->sumAgencyLedgerLiabilities(), 2);
        $platformExposure = $this->balances->getPlatformExposure();

        $duplicates = $this->reconciliation->findDuplicateSourcePosts();
        $orphans = $this->reconciliation->findOrphanWalletTransactions();

        $lastPosted = LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->orderByDesc('posted_at')
            ->first(['id', 'transaction_ref', 'posted_at', 'transaction_type']);

        $integrity = $this->integrity->checkIntegrity();

        return [
            'total_transactions' => $totalTransactions,
            'posted_transactions' => $postedCount,
            'failed_count' => (int) ($statusCounts[LedgerTransactionStatus::Failed->value] ?? 0),
            'draft_count' => (int) ($statusCounts[LedgerTransactionStatus::Draft->value] ?? 0),
            'reversed_count' => (int) ($statusCounts[LedgerTransactionStatus::Reversed->value] ?? 0),
            'unbalanced_count' => $unbalancedCount,
            'total_entries' => (int) LedgerEntry::query()->count(),
            'platform_exposure' => $platformExposure,
            'agency_wallet_liability_total' => $ledgerLiabilityTotal,
            'wallet_balance_total' => $walletTotal,
            'wallet_ledger_difference' => round($walletTotal - $ledgerLiabilityTotal, 2),
            'duplicate_source_count' => count($duplicates),
            'orphan_wallet_count' => count($orphans),
            'last_posted_transaction' => $lastPosted,
            'integrity_passed' => $integrity['passed'],
            'integrity_issue_count' => count($integrity['issues']),
            'agency_breakdown' => $this->buildAgencyBreakdown(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAgencySummary(int $agencyId): array
    {
        $compare = $this->balances->compareWalletToLedger($agencyId);

        $postedCount = (int) LedgerTransaction::query()
            ->where('agency_id', $agencyId)
            ->where('status', LedgerTransactionStatus::Posted)
            ->count();

        $lastPosted = LedgerTransaction::query()
            ->where('agency_id', $agencyId)
            ->where('status', LedgerTransactionStatus::Posted)
            ->orderByDesc('posted_at')
            ->first(['id', 'transaction_ref', 'posted_at']);

        return [
            'agency_id' => $agencyId,
            'wallet_balance' => $compare['wallet_balance'],
            'ledger_liability' => $compare['ledger_balance'],
            'difference' => $compare['difference'],
            'matches' => $compare['matches'],
            'posted_transaction_count' => $postedCount,
            'last_posted_at' => $lastPosted?->posted_at,
            'last_posted_ref' => $lastPosted?->transaction_ref,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildAgencyBreakdown(): array
    {
        $rows = [];
        $agencyIds = AgentWallet::query()->pluck('agency_id')->unique()->merge(
            LedgerTransaction::query()->whereNotNull('agency_id')->distinct()->pluck('agency_id')
        )->unique()->sort()->values();

        $agencies = Agency::query()->whereIn('id', $agencyIds)->get()->keyBy('id');

        foreach ($agencyIds as $agencyId) {
            $id = (int) $agencyId;
            $compare = $this->balances->compareWalletToLedger($id);
            $postedCount = (int) LedgerTransaction::query()
                ->where('agency_id', $id)
                ->where('status', LedgerTransactionStatus::Posted)
                ->count();

            $lastPosted = LedgerTransaction::query()
                ->where('agency_id', $id)
                ->where('status', LedgerTransactionStatus::Posted)
                ->orderByDesc('posted_at')
                ->value('posted_at');

            $agency = $agencies->get($id);
            $hasLedgerData = $postedCount > 0;

            $status = ! $hasLedgerData
                ? 'no-ledger-data'
                : ($compare['matches'] ? 'matched' : 'mismatch');

            $rows[] = [
                'agency_id' => $id,
                'agency_name' => $agency?->name ?? 'Agency #'.$id,
                'agency_code' => $agency !== null ? AgencyPrefixService::resolvePrefix($agency) : null,
                'wallet_balance' => $compare['wallet_balance'],
                'ledger_liability' => $compare['ledger_balance'],
                'difference' => $compare['difference'],
                'posted_transaction_count' => $postedCount,
                'last_posted_at' => $lastPosted,
                'status' => $status,
            ];
        }

        return $rows;
    }

    protected function sumAgencyLedgerLiabilities(): float
    {
        $sum = 0.0;
        $agencyIds = AgentWallet::query()->pluck('agency_id')->unique();
        foreach ($agencyIds as $id) {
            $sum += $this->balances->getAgencyWalletBalance((int) $id);
        }

        return round($sum, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function countUnbalancedPosted(): array
    {
        $issues = [];
        $transactions = LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->with('entries')
            ->get();

        foreach ($transactions as $transaction) {
            $debit = round((float) $transaction->entries->sum('debit'), 2);
            $credit = round((float) $transaction->entries->sum('credit'), 2);
            if (abs($debit - $credit) >= 0.01) {
                $issues[] = ['transaction_id' => $transaction->id];
            }
        }

        return $issues;
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function csvRows(): array
    {
        $platform = $this->buildPlatformDashboard();
        $breakdown = $platform['agency_breakdown'] ?? [];

        $rows = [[
            'agency_id', 'agency_name', 'wallet_balance', 'ledger_liability', 'difference',
            'status', 'posted_transaction_count', 'last_posted_at',
        ]];

        foreach ($breakdown as $row) {
            $rows[] = [
                $row['agency_id'] ?? '',
                $row['agency_name'] ?? '',
                $row['wallet_balance'] ?? 0,
                $row['ledger_liability'] ?? 0,
                $row['difference'] ?? 0,
                $row['status'] ?? '',
                $row['posted_transaction_count'] ?? 0,
                $row['last_posted_at'] ?? '',
            ];
        }

        return $rows;
    }
}
