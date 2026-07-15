<?php

namespace App\Services\Finance\Adjustments;

use App\Enums\AgentWalletTransactionStatus;
use App\Enums\AgentWalletTransactionType;
use App\Models\Agency;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\AuditLog;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Ledger\LedgerEventRecorder;
use App\Support\Identity\ActorIdentifier;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Platform-admin manual wallet corrections: wallet balance, wallet transaction, and ledger post atomically.
 * Idempotent apply via reference/meta idempotency_key; compensating reversals (never mutate originals).
 */
class ManualWalletAdjustmentService
{
    /** @var list<string> */
    public const REASON_CATEGORIES = [
        'bank_correction',
        'duplicate_payment_correction',
        'refund_correction',
        'commission_correction',
        'opening_balance_correction',
        'other',
    ];

    public function __construct(
        protected LedgerEventRecorder $ledgerRecorder,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    /**
     * @deprecated Lookup uses meta.idempotency_key; kept for backward-compatible tests only.
     */
    public static function idempotencyReference(string $idempotencyKey): string
    {
        return 'ADJ-'.$idempotencyKey;
    }

    public static function generateIdempotencyKey(): string
    {
        return (string) Str::uuid();
    }

    public function findByIdempotencyKey(string $idempotencyKey, int $agencyId, int $walletId): ?AgentWalletTransaction
    {
        return AgentWalletTransaction::query()
            ->where('meta->idempotency_key', $idempotencyKey)
            ->where('agency_id', $agencyId)
            ->where('agent_wallet_id', $walletId)
            ->whereIn('type', [
                AgentWalletTransactionType::ManualCredit->value,
                AgentWalletTransactionType::ManualDebit->value,
            ])
            ->first();
    }

    public function isReversalTransaction(AgentWalletTransaction $transaction): bool
    {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];

        return ! empty($meta['reversal_of_wallet_transaction_id']);
    }

    public function findReversalFor(AgentWalletTransaction $original): ?AgentWalletTransaction
    {
        return AgentWalletTransaction::query()
            ->where('agent_wallet_id', $original->agent_wallet_id)
            ->whereIn('type', [
                AgentWalletTransactionType::ManualCredit->value,
                AgentWalletTransactionType::ManualDebit->value,
            ])
            ->where('status', AgentWalletTransactionStatus::Posted)
            ->orderByDesc('id')
            ->get()
            ->first(fn (AgentWalletTransaction $tx): bool => (int) (is_array($tx->meta) ? ($tx->meta['reversal_of_wallet_transaction_id'] ?? 0) : 0) === (int) $original->id);
    }

    public function canReverse(AgentWalletTransaction $original): bool
    {
        if (! in_array($original->type, [AgentWalletTransactionType::ManualCredit, AgentWalletTransactionType::ManualDebit], true)) {
            return false;
        }

        if ($original->status !== AgentWalletTransactionStatus::Posted) {
            return false;
        }

        if ($this->isReversalTransaction($original)) {
            return false;
        }

        return $this->findReversalFor($original) === null;
    }

    /**
     * @return array{wallet_transaction: AgentWalletTransaction, ledger_transaction: LedgerTransaction, idempotent_replay: bool}
     */
    public function apply(
        Agency $agency,
        AgentWallet $wallet,
        string $adjustmentType,
        float $amount,
        string $reason,
        ?string $note,
        User $actor,
        string $idempotencyKey,
        ?Request $request = null,
    ): array {
        $this->platformModuleEnforcer->ensureAgentWalletEnabled();

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Adjustment amount must be greater than zero.');
        }

        if (! in_array($adjustmentType, ['manual_credit', 'manual_debit'], true)) {
            throw new InvalidArgumentException('Invalid adjustment type.');
        }

        if (! in_array($reason, self::REASON_CATEGORIES, true)) {
            throw new InvalidArgumentException('Invalid adjustment reason.');
        }

        if ((int) $wallet->agency_id !== (int) $agency->id) {
            throw new InvalidArgumentException('Wallet does not belong to the selected agency.');
        }

        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $existing = $this->findByIdempotencyKey($idempotencyKey, $agency->id, $wallet->id);
        if ($existing !== null) {
            $ledger = LedgerTransaction::query()
                ->where('source_type', $existing->getMorphClass())
                ->where('source_id', $existing->id)
                ->first();

            if ($ledger === null) {
                throw new InvalidArgumentException('Existing adjustment is missing a ledger post.');
            }

            return [
                'wallet_transaction' => $existing->load(['wallet', 'creator', 'approver', 'agency']),
                'ledger_transaction' => $ledger,
                'idempotent_replay' => true,
            ];
        }

        return DB::transaction(function () use (
            $agency,
            $wallet,
            $adjustmentType,
            $amount,
            $reason,
            $note,
            $actor,
            $idempotencyKey,
            $request,
        ): array {
            $wallet = AgentWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ((int) $wallet->agency_id !== (int) $agency->id) {
                throw new InvalidArgumentException('Wallet does not belong to the selected agency.');
            }

            $existing = $this->findByIdempotencyKey($idempotencyKey, $agency->id, $wallet->id);
            if ($existing !== null) {
                $ledger = LedgerTransaction::query()
                    ->where('source_type', $existing->getMorphClass())
                    ->where('source_id', $existing->id)
                    ->firstOrFail();

                return [
                    'wallet_transaction' => $existing->load(['wallet', 'creator', 'approver', 'agency']),
                    'ledger_transaction' => $ledger,
                    'idempotent_replay' => true,
                ];
            }

            $balanceBefore = round((float) $wallet->balance, 2);
            $isCredit = $adjustmentType === 'manual_credit';
            $balanceAfter = $isCredit
                ? round($balanceBefore + $amount, 2)
                : round($balanceBefore - $amount, 2);

            if (! $isCredit) {
                $this->assertDebitAllowed($wallet, $balanceAfter);
            }

            $txType = $isCredit
                ? AgentWalletTransactionType::ManualCredit
                : AgentWalletTransactionType::ManualDebit;

            $reference = $this->referenceGenerator->generateUnique('agent_wallet_transactions', 'reference', 9, 'W');

            $meta = [
                'idempotency_key' => $idempotencyKey,
                'adjustment_reason' => $reason,
                'adjustment_note' => $note,
                'correction_category' => $reason,
                'performed_by' => $actor->id,
                'performed_by_identifier' => ActorIdentifier::forUser($actor),
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ];

            $walletTransaction = AgentWalletTransaction::query()->create([
                'agency_id' => $wallet->agency_id,
                'agent_id' => $wallet->agent_id,
                'user_id' => $wallet->user_id,
                'agent_wallet_id' => $wallet->id,
                'type' => $txType,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => $reference,
                'description' => $isCredit ? 'Manual wallet credit' : 'Manual wallet debit',
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'meta' => array_filter($meta, fn ($value) => $value !== null && $value !== ''),
            ]);

            $wallet->update(['balance' => $balanceAfter]);

            $ledgerTransaction = $isCredit
                ? $this->ledgerRecorder->recordManualWalletCredit($walletTransaction, $actor)
                : $this->ledgerRecorder->recordManualWalletDebit($walletTransaction, $actor);

            $this->writeAudit($agency, $walletTransaction, $actor, $request, 'finance.manual_wallet_adjustment');

            return [
                'wallet_transaction' => $walletTransaction->fresh(['wallet', 'creator', 'approver', 'agency']),
                'ledger_transaction' => $ledgerTransaction,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * @return array{wallet_transaction: AgentWalletTransaction, ledger_transaction: LedgerTransaction, original: AgentWalletTransaction}
     */
    public function reverse(
        AgentWalletTransaction $original,
        string $reversalReason,
        User $actor,
        ?Request $request = null,
    ): array {
        $this->platformModuleEnforcer->ensureAgentWalletEnabled();

        $reversalReason = trim($reversalReason);
        if ($reversalReason === '') {
            throw new InvalidArgumentException('Reversal reason is required.');
        }

        return DB::transaction(function () use ($original, $reversalReason, $actor, $request): array {
            $original = AgentWalletTransaction::query()->whereKey($original->id)->lockForUpdate()->firstOrFail();

            if (! $this->canReverse($original)) {
                throw new InvalidArgumentException('This transaction cannot be reversed.');
            }

            $wallet = AgentWallet::query()->whereKey($original->agent_wallet_id)->lockForUpdate()->firstOrFail();

            if ((int) $wallet->agency_id !== (int) $original->agency_id) {
                throw new InvalidArgumentException('Wallet does not belong to the adjustment agency.');
            }

            $amount = round((float) $original->amount, 2);
            $balanceBefore = round((float) $wallet->balance, 2);
            $originalMeta = is_array($original->meta) ? $original->meta : [];

            $reversingCredit = $original->type === AgentWalletTransactionType::ManualDebit;
            $balanceAfter = $reversingCredit
                ? round($balanceBefore + $amount, 2)
                : round($balanceBefore - $amount, 2);

            if (! $reversingCredit) {
                $this->assertDebitAllowed($wallet, $balanceAfter);
            }

            $txType = $reversingCredit
                ? AgentWalletTransactionType::ManualCredit
                : AgentWalletTransactionType::ManualDebit;

            $reference = $this->referenceGenerator->generateUnique('agent_wallet_transactions', 'reference', 9, 'W');

            $meta = [
                'is_reversal' => true,
                'reversal_of_wallet_transaction_id' => $original->id,
                'reversal_reason' => $reversalReason,
                'reversed_by' => $actor->id,
                'reversed_by_identifier' => ActorIdentifier::forUser($actor),
                'original_type' => $original->type->value,
                'original_amount' => $amount,
                'original_reference' => (string) ($original->reference ?? ''),
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ];

            $description = $original->type === AgentWalletTransactionType::ManualCredit
                ? 'Manual wallet credit reversal'
                : 'Manual wallet debit reversal';

            $reversalTransaction = AgentWalletTransaction::query()->create([
                'agency_id' => $original->agency_id,
                'agent_id' => $original->agent_id,
                'user_id' => $original->user_id,
                'agent_wallet_id' => $wallet->id,
                'type' => $txType,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => $reference,
                'description' => $description,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'meta' => array_filter($meta, fn ($value) => $value !== null && $value !== ''),
            ]);

            $wallet->update(['balance' => $balanceAfter]);

            $ledgerTransaction = match ($original->type) {
                AgentWalletTransactionType::ManualCredit => $this->ledgerRecorder->recordManualWalletDebitReversal($reversalTransaction, $actor),
                AgentWalletTransactionType::ManualDebit => $this->ledgerRecorder->recordManualWalletCreditReversal($reversalTransaction, $actor),
            };

            $original->loadMissing('agency');
            $agency = $original->agency ?? Agency::query()->findOrFail($original->agency_id);

            $this->writeAudit(
                $agency,
                $reversalTransaction,
                $actor,
                $request,
                'finance.manual_wallet_adjustment_reversal',
                [
                    'reversal_of_wallet_transaction_id' => $original->id,
                    'original_type' => $original->type->value,
                    'original_amount' => $amount,
                    'original_reference' => $original->reference,
                    'original_adjustment_reason' => $originalMeta['adjustment_reason'] ?? null,
                ],
            );

            return [
                'wallet_transaction' => $reversalTransaction->fresh(['wallet', 'creator', 'approver', 'agency']),
                'ledger_transaction' => $ledgerTransaction,
                'original' => $original->fresh(['wallet', 'creator', 'approver', 'agency']),
            ];
        });
    }

    /**
     * @return list<AgentWallet>
     */
    public function walletsForAgency(int $agencyId): array
    {
        return AgentWallet::query()
            ->where('agency_id', $agencyId)
            ->with(['agent.user'])
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @deprecated Use {@see AgentWalletService::agencyHasHistoricalDuplicateWallets()} for UI warnings only.
     */
    public function requiresWalletSelection(int $agencyId): bool
    {
        return app(AgentWalletService::class)->agencyHasHistoricalDuplicateWallets($agencyId);
    }

    public function canonicalWalletForAgency(Agency $agency): AgentWallet
    {
        return app(AgentWalletService::class)->getOrCreateCanonicalWalletForAgency($agency);
    }

    protected function assertDebitAllowed(AgentWallet $wallet, float $balanceAfter): void
    {
        $creditLimit = $wallet->credit_limit !== null ? round((float) $wallet->credit_limit, 2) : null;
        $floor = ($creditLimit !== null && $creditLimit > 0) ? -$creditLimit : 0.0;

        if ($balanceAfter < $floor - 0.001) {
            throw new InvalidArgumentException(
                $floor < 0
                    ? 'Debit would exceed wallet balance and available credit limit.'
                    : 'Debit would make wallet balance negative.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function writeAudit(
        Agency $agency,
        AgentWalletTransaction $walletTransaction,
        User $actor,
        ?Request $request,
        string $action,
        array $extra = [],
    ): void {
        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => AgentWalletTransaction::class,
            'auditable_id' => $walletTransaction->id,
            'properties' => array_merge([
                'wallet_transaction_id' => $walletTransaction->id,
                'type' => $walletTransaction->type->value,
                'amount' => (float) $walletTransaction->amount,
                'balance_before' => (float) $walletTransaction->balance_before,
                'balance_after' => (float) $walletTransaction->balance_after,
                'meta' => $walletTransaction->meta,
            ], $extra),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
