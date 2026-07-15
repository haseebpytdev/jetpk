<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\LedgerEntry;
use App\Models\LedgerPostingRule;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Double-entry posting: draft lines, balance validation, and rule-based posting.
 */
class LedgerPostingService
{
    /** @var list<array<string, mixed>> */
    protected array $pendingLines = [];

    public function __construct(
        protected LedgerAccountService $accounts,
        protected LedgerTransactionFactory $factory,
    ) {}

    public function begin(LedgerTransaction $transaction): self
    {
        $this->pendingLines = [];
        $this->transaction = $transaction;

        return $this;
    }

    protected ?LedgerTransaction $transaction = null;

    public function addDebit(
        string $accountCode,
        float $amount,
        ?int $agencyId = null,
        ?int $bookingId = null,
        ?string $description = null,
    ): self {
        $this->pendingLines[] = [
            'account_code' => $accountCode,
            'debit' => round($amount, 2),
            'credit' => 0.0,
            'agency_id' => $agencyId,
            'booking_id' => $bookingId,
            'description' => $description,
        ];

        return $this;
    }

    public function addCredit(
        string $accountCode,
        float $amount,
        ?int $agencyId = null,
        ?int $bookingId = null,
        ?string $description = null,
    ): self {
        $this->pendingLines[] = [
            'account_code' => $accountCode,
            'debit' => 0.0,
            'credit' => round($amount, 2),
            'agency_id' => $agencyId,
            'booking_id' => $bookingId,
            'description' => $description,
        ];

        return $this;
    }

    /**
     * @return array{valid: bool, total_debit: float, total_credit: float, difference: float}
     */
    public function validateBalanced(): array
    {
        $totalDebit = round(array_sum(array_column($this->pendingLines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($this->pendingLines, 'credit')), 2);

        return [
            'valid' => abs($totalDebit - $totalCredit) < 0.01 && $totalDebit > 0,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => round($totalDebit - $totalCredit, 2),
        ];
    }

    public function post(?LedgerTransaction $transaction = null): LedgerTransaction
    {
        $transaction ??= $this->transaction;
        if ($transaction === null) {
            throw new RuntimeException('No ledger transaction in context.');
        }

        if ($transaction->isPosted()) {
            throw new RuntimeException('Transaction is already posted.');
        }

        $balance = $this->validateBalanced();
        if (! $balance['valid']) {
            throw new RuntimeException(sprintf(
                'Unbalanced transaction: debits=%.2f credits=%.2f diff=%.2f',
                $balance['total_debit'],
                $balance['total_credit'],
                $balance['difference'],
            ));
        }

        return DB::transaction(function () use ($transaction, $balance) {
            $transaction->forceFill([
                'amount_total' => $balance['total_debit'],
                'status' => LedgerTransactionStatus::Posted,
                'posted_at' => now(),
            ])->save();

            foreach ($this->pendingLines as $line) {
                $account = $this->accounts->findByCode($line['account_code'], $line['agency_id']);
                if ($account === null) {
                    throw new RuntimeException('Account not found: '.$line['account_code']);
                }

                LedgerEntry::query()->create([
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $account->id,
                    'agency_id' => $line['agency_id'],
                    'booking_id' => $line['booking_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'currency' => $transaction->currency,
                    'description' => $line['description'],
                ]);
            }

            $this->pendingLines = [];

            return $transaction->fresh(['entries.account']);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function postFromRule(string $eventType, float $amount, array $context = [], bool $persist = true): LedgerTransaction|array
    {
        $rule = LedgerPostingRule::query()->where('event_type', $eventType)->where('enabled', true)->first();
        if ($rule === null) {
            throw new RuntimeException('Posting rule not found: '.$eventType);
        }

        $agencyId = $context['agency_id'] ?? null;
        $bookingId = $context['booking_id'] ?? null;

        $this->pendingLines = [];
        $this->addDebit($rule->debit_account_code, $amount, $agencyId, $bookingId, $context['description'] ?? null);
        $this->addCredit($rule->credit_account_code, $amount, $agencyId, $bookingId, $context['description'] ?? null);

        $projection = [
            'event_type' => $eventType,
            'debit_account' => $rule->debit_account_code,
            'credit_account' => $rule->credit_account_code,
            'amount' => round($amount, 2),
            'agency_id' => $agencyId,
            'booking_id' => $bookingId,
            'lines' => $this->pendingLines,
            'balance' => $this->validateBalanced(),
        ];

        if (! $persist) {
            return $projection;
        }

        $transactionType = LedgerTransactionType::tryFrom($eventType)
            ?? LedgerTransactionType::WalletAdjustment;

        if (
            isset($context['source_type'], $context['source_id'])
            && $this->factory->sourceAlreadyPosted(
                (string) $context['source_type'],
                (int) $context['source_id'],
                $transactionType,
            )
        ) {
            throw new RuntimeException('Duplicate source posting blocked.');
        }

        $transaction = $this->factory->createDraftTransaction(array_merge($context, [
            'transaction_type' => $transactionType,
            'amount_total' => $amount,
            'currency' => $context['currency'] ?? 'PKR',
            'occurred_at' => $context['occurred_at'] ?? now(),
        ]));

        $this->transaction = $transaction;

        return $this->post($transaction);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPendingLines(): array
    {
        return $this->pendingLines;
    }
}
