<?php

namespace App\Services\Finance\Statements;

use App\Enums\AgentWalletTransactionStatus;
use App\Enums\LedgerTransactionStatus;
use App\Models\Agency;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\LedgerTransaction;
use App\Services\Finance\Ledger\LedgerBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Read-only agency wallet statements with double-entry ledger comparison (wallet = source of truth).
 */
class AgentStatementService
{
    public function __construct(
        protected LedgerBalanceService $ledgerBalances,
    ) {}

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolvePeriodFromRequest(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->string('date_from')->toString())->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = $request->filled('date_to')
            ? Carbon::parse($request->string('date_to')->toString())->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            throw new InvalidArgumentException('date_from must be on or before date_to.');
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildAgencyIndexRows(): array
    {
        $agencies = Agency::query()->orderBy('name')->get(['id', 'name']);
        $rows = [];

        foreach ($agencies as $agency) {
            $walletBalance = $this->ledgerBalances->getAgencyAgentWalletBalance($agency->id);
            $ledgerBalance = $this->ledgerBalances->getAgencyWalletBalance($agency->id);
            $diff = round($walletBalance - $ledgerBalance, 2);
            $lastMovement = AgentWalletTransaction::query()
                ->where('agency_id', $agency->id)
                ->latest('created_at')
                ->value('created_at');

            $rows[] = [
                'agency' => $agency,
                'wallet_balance' => $walletBalance,
                'ledger_liability' => $ledgerBalance,
                'difference' => $diff,
                'last_movement_at' => $lastMovement,
                'reconciliation_status' => $this->reconciliationStatusLabel($walletBalance, $ledgerBalance, $agency->id),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatement(Agency $agency, Carbon $from, Carbon $to): array
    {
        $currency = 'PKR';
        $openingBalance = $this->openingBalanceBefore($agency->id, $from);

        $transactions = AgentWalletTransaction::query()
            ->where('agency_id', $agency->id)
            ->whereBetween('created_at', [$from, $to])
            ->with(['creator', 'approver', 'depositRequest', 'agent.user'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $bookingIds = $transactions
            ->map(fn (AgentWalletTransaction $tx): int => (int) (is_array($tx->meta) ? ($tx->meta['booking_id'] ?? 0) : 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $bookingsById = $bookingIds->isEmpty()
            ? collect()
            : Booking::query()->whereIn('id', $bookingIds)->get(['id', 'booking_reference'])->keyBy('id');

        $running = $openingBalance;
        $totalCredits = 0.0;
        $totalDebits = 0.0;
        $movements = [];

        foreach ($transactions as $transaction) {
            [$debit, $credit] = $this->debitCreditFor($transaction);
            $totalDebits += $debit;
            $totalCredits += $credit;
            $running = round($running + $credit - $debit, 2);

            $bookingId = (int) (is_array($transaction->meta) ? ($transaction->meta['booking_id'] ?? 0) : 0);
            $booking = $bookingId > 0 ? $bookingsById->get($bookingId) : null;

            $movements[] = [
                'date' => $transaction->created_at?->toDateTimeString(),
                'type' => $transaction->type->value,
                'description' => (string) $transaction->description,
                'reference' => (string) ($transaction->reference ?? ''),
                'booking_reference' => $booking?->booking_reference,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $running,
                'source_type' => AgentWalletTransaction::class,
                'source_id' => $transaction->id,
                'status' => $transaction->status->value,
                'created_by' => $transaction->creator?->name,
                'approved_by' => $transaction->approver?->name,
            ];
        }

        $closingBalance = $running;
        $reconciliation = $this->ledgerBalances->compareWalletToLedger($agency->id, $currency);
        $walletBalance = (float) $reconciliation['wallet_balance'];
        $ledgerLiability = (float) $reconciliation['ledger_balance'];

        return [
            'agency' => $agency,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'currency' => $currency,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'total_debits' => round($totalDebits, 2),
            'total_credits' => round($totalCredits, 2),
            'movements' => $movements,
            'ledger_summary' => $this->buildLedgerSummary($agency->id, $from, $to),
            'reconciliation' => [
                'wallet_balance' => $walletBalance,
                'ledger_liability' => $ledgerLiability,
                'difference' => (float) $reconciliation['difference'],
                'status' => $this->reconciliationStatusLabel($walletBalance, $ledgerLiability, $agency->id),
                'matches' => (bool) $reconciliation['matches'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return list<list<string|float|null>>
     */
    public function csvRows(array $statement): array
    {
        $agency = $statement['agency'];
        $agencyName = $agency instanceof Agency ? $agency->name : (string) ($statement['agency_name'] ?? '');
        $period = $statement['period'] ?? [];
        $currency = (string) ($statement['currency'] ?? 'PKR');

        $rows = [
            ['Agency', $agencyName],
            ['Period from', $period['from'] ?? ''],
            ['Period to', $period['to'] ?? ''],
            ['Currency', $currency],
            ['Opening balance', $statement['opening_balance'] ?? 0],
            [],
            ['Date', 'Type', 'Description', 'Reference', 'Booking ref', 'Debit', 'Credit', 'Running balance', 'Status', 'Created by', 'Approved by'],
        ];

        foreach ($statement['movements'] ?? [] as $movement) {
            $rows[] = [
                $movement['date'] ?? '',
                $movement['type'] ?? '',
                $movement['description'] ?? '',
                $movement['reference'] ?? '',
                $movement['booking_reference'] ?? '',
                $movement['debit'] ?? 0,
                $movement['credit'] ?? 0,
                $movement['running_balance'] ?? 0,
                $movement['status'] ?? '',
                $movement['created_by'] ?? '',
                $movement['approved_by'] ?? '',
            ];
        }

        $rows[] = [];
        $rows[] = ['Total debits', $statement['total_debits'] ?? 0];
        $rows[] = ['Total credits', $statement['total_credits'] ?? 0];
        $rows[] = ['Closing balance', $statement['closing_balance'] ?? 0];
        $rows[] = [];
        $recon = $statement['reconciliation'] ?? [];
        $rows[] = ['Wallet balance (source of truth)', $recon['wallet_balance'] ?? 0];
        $rows[] = ['Ledger liability', $recon['ledger_liability'] ?? 0];
        $rows[] = ['Difference', $recon['difference'] ?? 0];
        $rows[] = ['Reconciliation status', $recon['status'] ?? ''];

        return $rows;
    }

    public function openingBalanceBefore(int $agencyId, Carbon $before): float
    {
        $last = AgentWalletTransaction::query()
            ->where('agency_id', $agencyId)
            ->where('created_at', '<', $before)
            ->whereIn('status', [
                AgentWalletTransactionStatus::Posted,
                AgentWalletTransactionStatus::Approved,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['balance_after']);

        if ($last !== null) {
            return round((float) $last->balance_after, 2);
        }

        return 0.0;
    }

    /**
     * @return array{transaction_count: int, period_debit: float, period_credit: float, transactions: Collection<int, LedgerTransaction>}
     */
    protected function buildLedgerSummary(int $agencyId, Carbon $from, Carbon $to): array
    {
        $transactions = LedgerTransaction::query()
            ->where('agency_id', $agencyId)
            ->where('status', LedgerTransactionStatus::Posted)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'transaction_ref', 'transaction_type', 'amount_total', 'currency', 'description', 'occurred_at']);

        return [
            'transaction_count' => $transactions->count(),
            'period_total' => round((float) $transactions->sum('amount_total'), 2),
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array{0: float, 1: float} debit, credit
     */
    protected function debitCreditFor(AgentWalletTransaction $transaction): array
    {
        $amount = round((float) $transaction->amount, 2);
        $before = round((float) $transaction->balance_before, 2);
        $after = round((float) $transaction->balance_after, 2);

        if ($after > $before) {
            return [0.0, $amount];
        }

        if ($after < $before) {
            return [$amount, 0.0];
        }

        return [0.0, 0.0];
    }

    protected function reconciliationStatusLabel(float $walletBalance, float $ledgerBalance, int $agencyId): string
    {
        $hasLedger = LedgerTransaction::query()
            ->where('agency_id', $agencyId)
            ->where('status', LedgerTransactionStatus::Posted)
            ->exists();

        if (! $hasLedger && abs($ledgerBalance) < 0.01) {
            return 'no_ledger_data';
        }

        if (abs($walletBalance - $ledgerBalance) < 0.01) {
            return 'matched';
        }

        return 'mismatch';
    }
}
