<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerTransactionStatus;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Support\Carbon;

/**
 * Agency monthly and platform period statements from posted ledger entries.
 */
class LedgerStatementService
{
    public function __construct(
        protected LedgerBalanceService $balances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildMonthlyStatement(int $agencyId, int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();

        $entries = LedgerEntry::query()
            ->with(['transaction', 'account'])
            ->where('agency_id', $agencyId)
            ->whereHas('transaction', function ($q) use ($from, $to) {
                $q->where('status', LedgerTransactionStatus::Posted)
                    ->whereBetween('occurred_at', [$from, $to]);
            })
            ->orderBy('id')
            ->get();

        $opening = $this->balances->getAgencyWalletBalance($agencyId);
        $periodDebits = round((float) $entries->sum('debit'), 2);
        $periodCredits = round((float) $entries->sum('credit'), 2);
        $closing = $this->balances->getAgencyWalletBalance($agencyId);

        return [
            'agency_id' => $agencyId,
            'period' => ['year' => $year, 'month' => $month, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'opening_balance' => $opening,
            'period_debits' => $periodDebits,
            'period_credits' => $periodCredits,
            'closing_balance' => $closing,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPlatformStatement(Carbon $from, Carbon $to): array
    {
        $transactions = LedgerTransaction::query()
            ->with('entries.account')
            ->where('status', LedgerTransactionStatus::Posted)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get();

        $exposure = $this->balances->getPlatformExposure();
        $totals = $this->balances->getPostedTotals();

        return [
            'period' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()],
            'transaction_count' => $transactions->count(),
            'platform_exposure' => $exposure,
            'posted_totals' => $totals,
            'transactions' => $transactions,
        ];
    }
}
