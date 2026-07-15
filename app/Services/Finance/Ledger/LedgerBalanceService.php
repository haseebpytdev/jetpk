<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerNormalBalance;
use App\Enums\LedgerTransactionStatus;
use App\Models\AgentWallet;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Account and agency wallet balance queries from posted ledger entries.
 * Agency wallet vs ledger compare uses SUM(agent_wallets.balance) per agency_id.
 */
class LedgerBalanceService
{
    public function __construct(
        protected LedgerAccountService $accounts,
    ) {}

    public function getAccountBalance(string $accountCode, ?int $agencyId = null, ?string $currency = 'PKR'): float
    {
        $account = $this->accounts->findByCode($accountCode, $agencyId);
        if ($account === null) {
            return 0.0;
        }

        $query = LedgerEntry::query()
            ->where('ledger_account_id', $account->id)
            ->whereHas('transaction', fn ($q) => $q->where('status', LedgerTransactionStatus::Posted));

        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        if ($currency !== null) {
            $query->where('currency', $currency);
        }

        $totals = $query
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        return $this->signedBalance(
            $account,
            (float) ($totals->total_debit ?? 0),
            (float) ($totals->total_credit ?? 0),
        );
    }

    public function getAgencyWalletBalance(int $agencyId, ?string $currency = 'PKR'): float
    {
        return $this->getAccountBalance('AGENCY_WALLET_LIABILITY', $agencyId, $currency);
    }

    public function getPlatformExposure(?string $currency = 'PKR'): float
    {
        $account = $this->accounts->findByCode('AGENCY_WALLET_LIABILITY');
        if ($account === null) {
            return 0.0;
        }

        $totals = LedgerEntry::query()
            ->where('ledger_account_id', $account->id)
            ->whereNotNull('agency_id')
            ->whereHas('transaction', fn ($q) => $q->where('status', LedgerTransactionStatus::Posted))
            ->when($currency !== null, fn ($q) => $q->where('currency', $currency))
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        return $this->signedBalance(
            $account,
            (float) ($totals->total_debit ?? 0),
            (float) ($totals->total_credit ?? 0),
        );
    }

    public function getAgencyBalance(int $agencyId, ?string $currency = 'PKR'): float
    {
        return $this->getAgencyWalletBalance($agencyId, $currency);
    }

    /**
     * Sum of all agent wallet balances for an agency (matches AgentWalletService::agencyBalanceSummary).
     */
    public function getAgencyAgentWalletBalance(int $agencyId, ?string $currency = 'PKR'): float
    {
        $query = AgentWallet::query()->where('agency_id', $agencyId);

        if ($currency !== null) {
            $query->where('currency', $currency);
        }

        return round((float) $query->sum('balance'), 2);
    }

    public function compareWalletToLedger(int $agencyId, ?string $currency = 'PKR'): array
    {
        $walletBalance = $this->getAgencyAgentWalletBalance($agencyId, $currency);
        $ledgerBalance = $this->getAgencyWalletBalance($agencyId, $currency);

        return [
            'agency_id' => $agencyId,
            'wallet_balance' => $walletBalance,
            'ledger_balance' => $ledgerBalance,
            'difference' => round($walletBalance - $ledgerBalance, 2),
            'matches' => abs($walletBalance - $ledgerBalance) < 0.01,
        ];
    }

    protected function signedBalance(LedgerAccount $account, float $debit, float $credit): float
    {
        if ($account->normal_balance === LedgerNormalBalance::Debit
            || in_array($account->account_type, [LedgerAccountType::Asset, LedgerAccountType::Expense, LedgerAccountType::Clearing], true)) {
            return round($debit - $credit, 2);
        }

        return round($credit - $debit, 2);
    }

    /**
     * @return array{total_debit: float, total_credit: float}
     */
    public function getPostedTotals(): array
    {
        $row = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
            ->where('ledger_transactions.status', LedgerTransactionStatus::Posted->value)
            ->selectRaw('COALESCE(SUM(ledger_entries.debit), 0) as total_debit, COALESCE(SUM(ledger_entries.credit), 0) as total_credit')
            ->first();

        return [
            'total_debit' => round((float) ($row->total_debit ?? 0), 2),
            'total_credit' => round((float) ($row->total_credit ?? 0), 2),
        ];
    }
}
