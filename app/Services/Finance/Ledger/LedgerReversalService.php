<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Support\Identity\ActorIdentifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posted transaction reversal with equal opposite entries.
 */
class LedgerReversalService
{
    public function __construct(
        protected LedgerTransactionFactory $factory,
        protected LedgerPostingService $posting,
    ) {}

    public function reverse(LedgerTransaction $transaction, User $actor, string $reason): LedgerTransaction
    {
        if ($transaction->status !== LedgerTransactionStatus::Posted) {
            throw new RuntimeException('Only posted transactions can be reversed.');
        }

        if ($transaction->reversals()->exists()) {
            throw new RuntimeException('Transaction already has a reversal.');
        }

        return DB::transaction(function () use ($transaction, $actor, $reason) {
            $reversal = $this->factory->createDraftTransaction([
                'transaction_ref' => $this->factory->generateRef(),
                'agency_id' => $transaction->agency_id,
                'booking_id' => $transaction->booking_id,
                'customer_id' => $transaction->customer_id,
                'guest_key' => $transaction->guest_key,
                'actor_user_id' => $actor->id,
                'actor_identifier' => $actor->id ? ActorIdentifier::forUser($actor) : 'System',
                'transaction_type' => LedgerTransactionType::Reversal,
                'status' => LedgerTransactionStatus::Draft,
                'currency' => $transaction->currency,
                'amount_total' => $transaction->amount_total,
                'description' => 'Reversal: '.$reason,
                'occurred_at' => now(),
                'reversal_of_id' => $transaction->id,
                'properties' => ['reason' => $reason],
            ]);

            $transaction->load('entries.account');
            $this->posting->begin($reversal);

            foreach ($transaction->entries as $entry) {
                if ((float) $entry->debit > 0) {
                    $this->posting->addCredit(
                        $entry->account->code,
                        (float) $entry->debit,
                        $entry->agency_id,
                        $entry->booking_id,
                        'Reversal of '.$transaction->transaction_ref,
                    );
                } else {
                    $this->posting->addDebit(
                        $entry->account->code,
                        (float) $entry->credit,
                        $entry->agency_id,
                        $entry->booking_id,
                        'Reversal of '.$transaction->transaction_ref,
                    );
                }
            }

            $posted = $this->posting->post($reversal);

            $transaction->forceFill([
                'status' => LedgerTransactionStatus::Reversed,
                'reversed_at' => now(),
            ])->save();

            return $posted->fresh(['entries.account', 'reversalOf']);
        });
    }
}
