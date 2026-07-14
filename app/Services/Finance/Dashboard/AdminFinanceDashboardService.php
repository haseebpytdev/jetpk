<?php

namespace App\Services\Finance\Dashboard;

use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Models\LedgerTransaction;
use App\Services\Finance\Adjustments\ManualWalletAdjustmentService;
use App\Services\Finance\Ledger\LedgerBalanceService;
use App\Services\Finance\Ledger\LedgerReconciliationDashboardService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read-only admin finance dashboard: wallet vs ledger summary, MTD metrics, recent activity.
 */
class AdminFinanceDashboardService
{
    public function __construct(
        protected LedgerBalanceService $balances,
        protected LedgerReconciliationDashboardService $reconciliationDashboard,
        protected ManualWalletAdjustmentService $adjustments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $platform = $this->reconciliationDashboard->buildPlatformDashboard();
        $agencyBreakdown = $platform['agency_breakdown'] ?? [];
        $walletTotal = (float) ($platform['wallet_balance_total'] ?? 0);
        $ledgerTotal = (float) ($platform['agency_wallet_liability_total'] ?? 0);
        $difference = round($walletTotal - $ledgerTotal, 2);

        $statusCounts = $this->reconciliationStatusCounts($agencyBreakdown);

        return [
            'currency' => 'PKR',
            'summary' => [
                'wallet_balance_total' => $walletTotal,
                'ledger_liability_total' => $ledgerTotal,
                'difference' => $difference,
                'reconciliation_status' => $this->platformReconciliationStatus($difference, $statusCounts),
                'posted_transactions' => (int) ($platform['posted_transactions'] ?? 0),
                'unbalanced_transactions' => (int) ($platform['unbalanced_count'] ?? 0),
                'manual_adjustments_mtd' => $this->manualAdjustmentsMtdCount(),
                'deposits_mtd' => $this->depositsApprovedMtdCount(),
            ],
            'mtd' => $this->buildMtd(),
            'reconciliation' => [
                'matched_count' => $statusCounts['matched'],
                'mismatch_count' => $statusCounts['mismatch'],
                'no_ledger_data_count' => $statusCounts['no_ledger_data'],
                'top_mismatches' => $this->topMismatchedAgencies($agencyBreakdown),
            ],
            'recent_ledger' => $this->recentLedgerTransactions(),
            'recent_adjustments' => $this->recentManualAdjustments(),
            'recent_deposits' => $this->recentDeposits(),
            'agency_exposure' => $this->buildAgencyExposure($agencyBreakdown),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMtd(): array
    {
        $start = $this->mtdStart();

        return [
            'deposits_approved' => $this->depositsApprovedMtdAmount(),
            'manual_credits' => $this->manualWalletMtdAmount(AgentWalletTransactionType::ManualCredit, false),
            'manual_debits' => $this->manualWalletMtdAmount(AgentWalletTransactionType::ManualDebit, false),
            'reversals' => $this->manualReversalsMtdAmount(),
            'booking_payments' => $this->ledgerMtdAmount([LedgerTransactionType::BookingPaymentVerified], $start),
            'refunds' => $this->ledgerMtdAmount([
                LedgerTransactionType::BookingRefundApproved,
                LedgerTransactionType::BookingRefundPaid,
            ], $start),
            'commission' => $this->ledgerMtdAmount([LedgerTransactionType::AgencyCommissionEarned], $start),
            'markup_revenue' => $this->ledgerMtdAmount([LedgerTransactionType::MarkupRevenueRecognized], $start),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $breakdown
     * @return array{matched: int, mismatch: int, no_ledger_data: int}
     */
    protected function reconciliationStatusCounts(array $breakdown): array
    {
        $counts = ['matched' => 0, 'mismatch' => 0, 'no_ledger_data' => 0];
        foreach ($breakdown as $row) {
            $status = (string) ($row['status'] ?? 'no-ledger-data');
            if ($status === 'matched') {
                $counts['matched']++;
            } elseif ($status === 'mismatch') {
                $counts['mismatch']++;
            } else {
                $counts['no_ledger_data']++;
            }
        }

        return $counts;
    }

    /**
     * @param  array{matched: int, mismatch: int, no_ledger_data: int}  $statusCounts
     */
    protected function platformReconciliationStatus(float $difference, array $statusCounts): string
    {
        if ($statusCounts['mismatch'] > 0 || abs($difference) >= 0.01) {
            return 'mismatch';
        }

        if ($statusCounts['matched'] === 0 && $statusCounts['no_ledger_data'] > 0) {
            return 'no_ledger_data';
        }

        return 'matched';
    }

    protected function manualAdjustmentsMtdCount(): int
    {
        return (int) $this->manualWalletMtdQuery(null, null)
            ->count();
    }

    protected function depositsApprovedMtdCount(): int
    {
        return (int) AgentDepositRequest::query()
            ->where('status', AgentDepositRequestStatus::Approved)
            ->where('reviewed_at', '>=', $this->mtdStart())
            ->count();
    }

    protected function depositsApprovedMtdAmount(): float
    {
        return round((float) AgentDepositRequest::query()
            ->where('status', AgentDepositRequestStatus::Approved)
            ->where('reviewed_at', '>=', $this->mtdStart())
            ->sum('amount'), 2);
    }

    protected function manualWalletMtdAmount(AgentWalletTransactionType $type, bool $reversalsOnly): float
    {
        $query = $this->manualWalletMtdQuery($type, $reversalsOnly);

        return round((float) $query->sum('amount'), 2);
    }

    protected function manualReversalsMtdAmount(): float
    {
        return round((float) $this->manualWalletMtdQuery(null, true)->sum('amount'), 2);
    }

    /**
     * @param  list<LedgerTransactionType>  $types
     */
    protected function ledgerMtdAmount(array $types, CarbonInterface $start): float
    {
        $values = array_map(fn (LedgerTransactionType $t) => $t->value, $types);

        $sum = LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->whereIn('transaction_type', $values)
            ->where('posted_at', '>=', $start)
            ->sum('amount_total');

        return round((float) $sum, 2);
    }

    /**
     * @return Builder<AgentWalletTransaction>
     */
    protected function manualWalletMtdQuery(?AgentWalletTransactionType $type, ?bool $reversalsOnly)
    {
        $query = AgentWalletTransaction::query()
            ->whereIn('type', [
                AgentWalletTransactionType::ManualCredit->value,
                AgentWalletTransactionType::ManualDebit->value,
            ])
            ->where('created_at', '>=', $this->mtdStart());

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        if ($reversalsOnly === true) {
            $query->whereNotNull('meta->reversal_of_wallet_transaction_id');
        } elseif ($reversalsOnly === false) {
            $query->whereNull('meta->reversal_of_wallet_transaction_id');
        }

        return $query;
    }

    /**
     * @param  list<array<string, mixed>>  $breakdown
     * @return list<array<string, mixed>>
     */
    protected function topMismatchedAgencies(array $breakdown): array
    {
        $mismatches = array_values(array_filter(
            $breakdown,
            fn (array $row): bool => ($row['status'] ?? '') === 'mismatch',
        ));

        usort($mismatches, fn (array $a, array $b): int => abs((float) ($b['difference'] ?? 0)) <=> abs((float) ($a['difference'] ?? 0)));

        return array_slice($mismatches, 0, 5);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentLedgerTransactions(): array
    {
        $transactions = LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->with(['agency:id,name', 'entries'])
            ->orderByDesc('posted_at')
            ->limit(10)
            ->get(['id', 'transaction_ref', 'transaction_type', 'agency_id', 'amount_total', 'status', 'posted_at']);

        $rows = [];
        foreach ($transactions as $tx) {
            $debit = round((float) $tx->entries->sum('debit'), 2);
            $credit = round((float) $tx->entries->sum('credit'), 2);
            $rows[] = [
                'id' => $tx->id,
                'transaction_ref' => $tx->transaction_ref,
                'transaction_type' => $tx->transaction_type->value,
                'agency_name' => $tx->agency?->name,
                'amount' => (float) $tx->amount_total,
                'status' => $tx->status->value,
                'posted_at' => $tx->posted_at,
                'is_balanced' => abs($debit - $credit) < 0.01,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentManualAdjustments(): array
    {
        $transactions = AgentWalletTransaction::query()
            ->whereIn('type', [
                AgentWalletTransactionType::ManualCredit->value,
                AgentWalletTransactionType::ManualDebit->value,
            ])
            ->with(['agency:id,name', 'creator:id,name'])
            ->latest('id')
            ->limit(10)
            ->get();

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

        $rows = [];
        foreach ($transactions as $tx) {
            $isReversal = $this->adjustments->isReversalTransaction($tx);
            $rows[] = [
                'id' => $tx->id,
                'reference' => $tx->reference,
                'agency_name' => $tx->agency?->name,
                'type' => $tx->type->value,
                'amount' => (float) $tx->amount,
                'balance_before' => (float) $tx->balance_before,
                'balance_after' => (float) $tx->balance_after,
                'created_by' => $tx->creator?->name,
                'created_at' => $tx->created_at,
                'is_reversal' => $isReversal,
                'is_reversed' => isset($reversedOriginalIds[$tx->id]),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentDeposits(): array
    {
        $deposits = AgentDepositRequest::query()
            ->with(['agency:id,name'])
            ->latest('id')
            ->limit(10)
            ->get();

        $rows = [];
        foreach ($deposits as $deposit) {
            $walletTx = AgentWalletTransaction::query()
                ->where('agent_deposit_request_id', $deposit->id)
                ->where('type', AgentWalletTransactionType::DepositApproved->value)
                ->first(['id', 'reference']);

            $rows[] = [
                'id' => $deposit->id,
                'reference' => $deposit->reference,
                'agency_name' => $deposit->agency?->name,
                'amount' => (float) $deposit->amount,
                'status' => $deposit->status->value,
                'reviewed_at' => $deposit->reviewed_at,
                'wallet_transaction_id' => $walletTx?->id,
                'wallet_transaction_reference' => $walletTx?->reference,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $breakdown
     * @return list<array<string, mixed>>
     */
    protected function buildAgencyExposure(array $breakdown): array
    {
        $walletLast = AgentWalletTransaction::query()
            ->selectRaw('agency_id, MAX(created_at) as last_at')
            ->groupBy('agency_id')
            ->pluck('last_at', 'agency_id');

        $ledgerLast = LedgerTransaction::query()
            ->where('status', LedgerTransactionStatus::Posted)
            ->whereNotNull('agency_id')
            ->selectRaw('agency_id, MAX(posted_at) as last_at')
            ->groupBy('agency_id')
            ->pluck('last_at', 'agency_id');

        $rows = [];
        foreach ($breakdown as $row) {
            $agencyId = (int) ($row['agency_id'] ?? 0);
            $rows[] = array_merge($row, [
                'last_wallet_movement_at' => $walletLast->get($agencyId),
                'last_ledger_movement_at' => $ledgerLast->get($agencyId),
            ]);
        }

        usort($rows, fn (array $a, array $b): int => abs((float) ($b['difference'] ?? 0)) <=> abs((float) ($a['difference'] ?? 0)));

        return $rows;
    }

    protected function mtdStart(): CarbonInterface
    {
        return Carbon::now()->startOfMonth();
    }

    /**
     * Multi-section CSV rows for finance dashboard audit export (read-only).
     *
     * @return list<list<string|int|float|null>>
     */
    public function csvRows(): array
    {
        $dashboard = $this->build();
        $summary = $dashboard['summary'] ?? [];
        $mtd = $dashboard['mtd'] ?? [];
        $recon = $dashboard['reconciliation'] ?? [];
        $currency = (string) ($dashboard['currency'] ?? 'PKR');

        $rows = [
            ['section', 'field', 'value'],
            ['meta', 'generated_at', now()->toIso8601String()],
            ['meta', 'currency', $currency],
            ['summary', 'wallet_balance_total', $summary['wallet_balance_total'] ?? 0],
            ['summary', 'ledger_liability_total', $summary['ledger_liability_total'] ?? 0],
            ['summary', 'difference', $summary['difference'] ?? 0],
            ['summary', 'reconciliation_status', $summary['reconciliation_status'] ?? ''],
            ['summary', 'posted_transactions', $summary['posted_transactions'] ?? 0],
            ['summary', 'unbalanced_transactions', $summary['unbalanced_transactions'] ?? 0],
            ['summary', 'manual_adjustments_mtd', $summary['manual_adjustments_mtd'] ?? 0],
            ['summary', 'deposits_mtd', $summary['deposits_mtd'] ?? 0],
            ['mtd', 'deposits_approved', $mtd['deposits_approved'] ?? 0],
            ['mtd', 'manual_credits', $mtd['manual_credits'] ?? 0],
            ['mtd', 'manual_debits', $mtd['manual_debits'] ?? 0],
            ['mtd', 'reversals', $mtd['reversals'] ?? 0],
            ['mtd', 'booking_payments', $mtd['booking_payments'] ?? 0],
            ['mtd', 'refunds', $mtd['refunds'] ?? 0],
            ['mtd', 'commission', $mtd['commission'] ?? 0],
            ['mtd', 'markup_revenue', $mtd['markup_revenue'] ?? 0],
            ['reconciliation', 'matched_count', $recon['matched_count'] ?? 0],
            ['reconciliation', 'mismatch_count', $recon['mismatch_count'] ?? 0],
            ['reconciliation', 'no_ledger_data_count', $recon['no_ledger_data_count'] ?? 0],
            [],
            ['agency_exposure', 'agency_id', 'agency_name', 'wallet_balance', 'ledger_liability', 'difference', 'status', 'last_wallet_movement_at', 'last_ledger_movement_at'],
        ];

        foreach ($dashboard['agency_exposure'] ?? [] as $row) {
            $rows[] = [
                'agency_exposure',
                $row['agency_id'] ?? '',
                $row['agency_name'] ?? '',
                $row['wallet_balance'] ?? 0,
                $row['ledger_liability'] ?? 0,
                $row['difference'] ?? 0,
                $row['status'] ?? '',
                $row['last_wallet_movement_at'] ?? '',
                $row['last_ledger_movement_at'] ?? '',
            ];
        }

        $rows[] = [];
        $rows[] = ['recent_ledger', 'transaction_ref', 'transaction_type', 'agency_name', 'amount', 'status', 'posted_at', 'is_balanced'];

        foreach ($dashboard['recent_ledger'] ?? [] as $row) {
            $rows[] = [
                'recent_ledger',
                $row['transaction_ref'] ?? '',
                $row['transaction_type'] ?? '',
                $row['agency_name'] ?? '',
                $row['amount'] ?? 0,
                $row['status'] ?? '',
                $row['posted_at'] ?? '',
                ($row['is_balanced'] ?? false) ? 'yes' : 'no',
            ];
        }

        $rows[] = [];
        $rows[] = ['recent_adjustments', 'id', 'reference', 'agency_name', 'type', 'amount', 'balance_before', 'balance_after', 'created_by', 'created_at', 'is_reversal', 'is_reversed'];

        foreach ($dashboard['recent_adjustments'] ?? [] as $row) {
            $rows[] = [
                'recent_adjustments',
                $row['id'] ?? '',
                $row['reference'] ?? '',
                $row['agency_name'] ?? '',
                $row['type'] ?? '',
                $row['amount'] ?? 0,
                $row['balance_before'] ?? 0,
                $row['balance_after'] ?? 0,
                $row['created_by'] ?? '',
                $row['created_at'] ?? '',
                ($row['is_reversal'] ?? false) ? 'yes' : 'no',
                ($row['is_reversed'] ?? false) ? 'yes' : 'no',
            ];
        }

        $rows[] = [];
        $rows[] = ['recent_deposits', 'id', 'reference', 'agency_name', 'amount', 'status', 'reviewed_at', 'wallet_transaction_id', 'wallet_transaction_reference'];

        foreach ($dashboard['recent_deposits'] ?? [] as $row) {
            $rows[] = [
                'recent_deposits',
                $row['id'] ?? '',
                $row['reference'] ?? '',
                $row['agency_name'] ?? '',
                $row['amount'] ?? 0,
                $row['status'] ?? '',
                $row['reviewed_at'] ?? '',
                $row['wallet_transaction_id'] ?? '',
                $row['wallet_transaction_reference'] ?? '',
            ];
        }

        return $rows;
    }
}
