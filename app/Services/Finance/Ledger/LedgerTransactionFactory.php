<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Support\Identity\ActorIdentifier;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Draft ledger transaction builder with compact ref generation and actor assignment.
 */
class LedgerTransactionFactory
{
    public function __construct(
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    public function createDraftTransaction(array $context): LedgerTransaction
    {
        $transaction = new LedgerTransaction([
            'transaction_ref' => $context['transaction_ref'] ?? $this->generateRef(),
            'source_type' => $context['source_type'] ?? null,
            'source_id' => $context['source_id'] ?? null,
            'agency_id' => $context['agency_id'] ?? null,
            'booking_id' => $context['booking_id'] ?? null,
            'customer_id' => $context['customer_id'] ?? null,
            'guest_key' => $context['guest_key'] ?? null,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'actor_identifier' => $context['actor_identifier'] ?? null,
            'transaction_type' => $context['transaction_type'],
            'status' => $context['status'] ?? LedgerTransactionStatus::Draft,
            'currency' => $context['currency'] ?? 'PKR',
            'amount_total' => $context['amount_total'] ?? 0,
            'description' => $context['description'] ?? null,
            'occurred_at' => $context['occurred_at'] ?? now(),
            'properties' => $context['properties'] ?? null,
        ]);

        $transaction->save();

        return $transaction;
    }

    public function generateRef(?Carbon $at = null): string
    {
        return $this->referenceGenerator->generateUnique('ledger_transactions', 'transaction_ref', 10, 'L');
    }

    public function assignSource(LedgerTransaction $transaction, Model $source): LedgerTransaction
    {
        $transaction->forceFill([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
        ])->save();

        return $transaction->fresh();
    }

    public function assignActor(LedgerTransaction $transaction, ?User $user): LedgerTransaction
    {
        $transaction->forceFill([
            'actor_user_id' => $user?->id,
            'actor_identifier' => ActorIdentifier::forUser($user),
        ])->save();

        return $transaction->fresh();
    }

    /**
     * @param  array<string, mixed>|object  $guest
     */
    public function assignGuest(LedgerTransaction $transaction, array|object $guest): LedgerTransaction
    {
        $data = is_array($guest) ? $guest : (array) $guest;
        $guestId = (int) ($data['guest_id'] ?? $data['id'] ?? 0);

        $transaction->forceFill([
            'guest_key' => $guestId > 0 ? 'guest:'.$guestId : null,
            'actor_identifier' => ActorIdentifier::forGuest($data),
        ])->save();

        return $transaction->fresh();
    }

    public function sourceAlreadyPosted(string $sourceType, int $sourceId, LedgerTransactionType $type): bool
    {
        return LedgerTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('transaction_type', $type)
            ->whereIn('status', [
                LedgerTransactionStatus::Posted,
                LedgerTransactionStatus::Pending,
                LedgerTransactionStatus::Draft,
            ])
            ->exists();
    }
}
