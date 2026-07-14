<?php

namespace App\Services\Finance\Ledger;

use App\Enums\AgentCommissionEntryStatus;
use App\Enums\AgentCommissionEntryType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletTransactionStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\LedgerTransactionType;
use App\Models\AgentCommissionEntry;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\LedgerTransaction;
use App\Support\Identity\ActorIdentifier;
use Database\Seeders\LedgerPostingRulesSeeder;

/**
 * Project and backfill ledger entries from existing finance records; reconcile wallets.
 */
class LedgerReconciliationService
{
    public function __construct(
        protected LedgerAccountService $accounts,
        protected LedgerBalanceService $balances,
        protected LedgerPostingService $posting,
        protected LedgerTransactionFactory $factory,
    ) {}

    /**
     * @return array{projections: list<array<string, mixed>>, skipped: list<string>, errors: list<string>}
     */
    public function projectExistingEvents(?int $agencyId = null): array
    {
        $this->accounts->ensureAccountsExist();

        $projections = [];
        $skipped = [];
        $errors = [];

        foreach ($this->collectDepositProjections($agencyId) as $item) {
            $projections[] = $item;
        }
        foreach ($this->collectPaymentProjections($agencyId) as $item) {
            $projections[] = $item;
        }
        foreach ($this->collectRefundProjections($agencyId) as $item) {
            $projections[] = $item;
        }
        foreach ($this->collectCommissionProjections($agencyId) as $item) {
            $projections[] = $item;
        }
        foreach ($this->collectMarkupProjections($agencyId) as $item) {
            $projections[] = $item;
        }
        foreach ($this->collectWalletProjections($agencyId, $skipped) as $item) {
            $projections[] = $item;
        }

        return compact('projections', 'skipped', 'errors');
    }

    /**
     * @return array{posted: int, skipped: int, errors: list<string>}
     */
    public function backfillExistingEvents(?int $agencyId = null, bool $dryRun = true): array
    {
        if ($dryRun) {
            $result = $this->projectExistingEvents($agencyId);

            return [
                'posted' => 0,
                'skipped' => count($result['skipped']),
                'errors' => $result['errors'],
                'projections' => $result['projections'],
            ];
        }

        $this->accounts->ensureAccountsExist();
        (new LedgerPostingRulesSeeder)->run();

        $posted = 0;
        $skipped = 0;
        $errors = [];

        $payload = $this->projectExistingEvents($agencyId);
        foreach ($payload['projections'] as $projection) {
            try {
                if ($this->isAlreadyPosted($projection)) {
                    $skipped++;

                    continue;
                }

                $this->posting->postFromRule(
                    $projection['event_type'],
                    (float) $projection['amount'],
                    $projection['context'],
                    persist: true,
                );
                $posted++;
            } catch (\Throwable $e) {
                $errors[] = ($projection['label'] ?? 'event').': '.$e->getMessage();
            }
        }

        return compact('posted', 'skipped', 'errors');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reconcileWalletTransactions(?int $agencyId = null): array
    {
        $results = [];
        $agencyIds = $agencyId !== null
            ? [$agencyId]
            : AgentWallet::query()->pluck('agency_id')->unique()->all();

        foreach ($agencyIds as $id) {
            $results[] = $this->compareWalletBalanceToLedger((int) $id);
        }

        return $results;
    }

    public function compareWalletBalanceToLedger(int $agencyId): array
    {
        return $this->balances->compareWalletToLedger($agencyId);
    }

    public function comparePaymentsToLedger(Booking $booking): array
    {
        $paymentTotal = (float) BookingPayment::query()
            ->where('booking_id', $booking->id)
            ->where('status', BookingPaymentStatus::Verified)
            ->sum('amount');

        $ledgerTotal = (float) LedgerTransaction::query()
            ->where('booking_id', $booking->id)
            ->where('transaction_type', LedgerTransactionType::BookingPaymentVerified)
            ->where('status', 'posted')
            ->sum('amount_total');

        return [
            'booking_id' => $booking->id,
            'payment_total' => $paymentTotal,
            'ledger_total' => $ledgerTotal,
            'difference' => round($paymentTotal - $ledgerTotal, 2),
            'matches' => abs($paymentTotal - $ledgerTotal) < 0.01,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findDuplicateSourcePosts(): array
    {
        return LedgerTransaction::query()
            ->selectRaw('source_type, source_id, transaction_type, COUNT(*) as duplicate_count')
            ->whereNotNull('source_type')
            ->whereNotNull('source_id')
            ->groupBy('source_type', 'source_id', 'transaction_type')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn ($row) => [
                'source_type' => $row->source_type,
                'source_id' => (int) $row->source_id,
                'transaction_type' => $row->transaction_type,
                'duplicate_count' => (int) $row->duplicate_count,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findOrphanWalletTransactions(?int $agencyId = null): array
    {
        $query = AgentWalletTransaction::query()
            ->where('status', AgentWalletTransactionStatus::Posted)
            ->whereIn('type', [
                AgentWalletTransactionType::DepositApproved,
                AgentWalletTransactionType::AdminCredit,
                AgentWalletTransactionType::AdminDebit,
                AgentWalletTransactionType::BookingHold,
                AgentWalletTransactionType::BookingRelease,
                AgentWalletTransactionType::Adjustment,
            ]);

        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $orphans = [];
        foreach ($query->get() as $tx) {
            $eventType = $this->walletEventType($tx);
            if ($eventType === null) {
                continue;
            }

            $type = LedgerTransactionType::tryFrom($eventType) ?? LedgerTransactionType::WalletAdjustment;
            if (! LedgerTransaction::query()
                ->where('source_type', $tx->getMorphClass())
                ->where('source_id', $tx->id)
                ->where('transaction_type', $type)
                ->exists()) {
                $orphans[] = [
                    'wallet_transaction_id' => $tx->id,
                    'agency_id' => $tx->agency_id,
                    'type' => $tx->type->value,
                    'amount' => (float) $tx->amount,
                    'reference' => $tx->reference,
                ];
            }
        }

        return $orphans;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectDepositProjections(?int $agencyId): array
    {
        $query = AgentDepositRequest::query()->where('status', AgentDepositRequestStatus::Approved);
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $items = [];
        foreach ($query->get() as $deposit) {
            $context = $this->depositContext($deposit);
            $projection = $this->posting->postFromRule(
                'agency_deposit_approved',
                (float) $deposit->amount,
                $context,
                persist: false,
            );
            $items[] = $this->wrapProjection('agency_deposit_approved', $deposit, $projection, $context);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectPaymentProjections(?int $agencyId): array
    {
        $query = BookingPayment::query()->where('status', BookingPaymentStatus::Verified);
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $items = [];
        foreach ($query->with('booking')->get() as $payment) {
            $booking = $payment->booking;
            if ($booking === null) {
                continue;
            }

            if ($this->bookingPaidViaWalletHold($booking)) {
                continue;
            }

            $context = $this->paymentContext($payment, $booking);
            $projection = $this->posting->postFromRule(
                'booking_payment_verified',
                (float) $payment->amount,
                $context,
                persist: false,
            );
            $items[] = $this->wrapProjection('booking_payment_verified', $payment, $projection, $context);
        }

        return $items;
    }

    protected function bookingPaidViaWalletHold(Booking $booking): bool
    {
        return AgentWalletTransaction::query()
            ->where('type', AgentWalletTransactionType::BookingHold)
            ->where('status', AgentWalletTransactionStatus::Posted)
            ->where('meta->booking_id', $booking->id)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectRefundProjections(?int $agencyId): array
    {
        $query = BookingRefund::query();
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $items = [];
        foreach ($query->with('booking')->get() as $refund) {
            $eventType = $refund->status === BookingRefundStatus::Paid
                ? 'booking_refund_paid'
                : 'booking_refund_approved';

            if (! in_array($refund->status, [BookingRefundStatus::Approved, BookingRefundStatus::Paid], true)) {
                continue;
            }

            $context = $this->refundContext($refund);
            $projection = $this->posting->postFromRule(
                $eventType,
                (float) $refund->amount,
                $context,
                persist: false,
            );
            $items[] = $this->wrapProjection($eventType, $refund, $projection, $context);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectCommissionProjections(?int $agencyId): array
    {
        $query = AgentCommissionEntry::query()
            ->where('type', AgentCommissionEntryType::Earned)
            ->where('status', AgentCommissionEntryStatus::Approved);
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $items = [];
        foreach ($query->get() as $entry) {
            $context = $this->commissionContext($entry);
            $projection = $this->posting->postFromRule(
                'agency_commission_earned',
                (float) $entry->commission_amount,
                $context,
                persist: false,
            );
            $items[] = $this->wrapProjection('agency_commission_earned', $entry, $projection, $context);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectMarkupProjections(?int $agencyId): array
    {
        $query = BookingFareBreakdown::query()->where('markup', '>', 0);
        if ($agencyId !== null) {
            $query->whereHas('booking', fn ($q) => $q->where('agency_id', $agencyId));
        }

        $items = [];
        foreach ($query->with('booking')->get() as $fare) {
            $booking = $fare->booking;
            if ($booking === null) {
                continue;
            }

            $context = $this->markupContext($booking, $fare);
            $projection = $this->posting->postFromRule(
                'markup_revenue_recognized',
                (float) $fare->markup,
                $context,
                persist: false,
            );
            $items[] = $this->wrapProjection('markup_revenue_recognized', $fare, $projection, $context);
        }

        return $items;
    }

    /**
     * @param  list<string>  $skipped
     * @return list<array<string, mixed>>
     */
    protected function collectWalletProjections(?int $agencyId, array &$skipped): array
    {
        $query = AgentWalletTransaction::query()
            ->where('status', AgentWalletTransactionStatus::Posted)
            ->whereIn('type', [
                AgentWalletTransactionType::AdminCredit,
                AgentWalletTransactionType::AdminDebit,
                AgentWalletTransactionType::BookingHold,
                AgentWalletTransactionType::BookingRelease,
                AgentWalletTransactionType::Adjustment,
                AgentWalletTransactionType::ManualCredit,
                AgentWalletTransactionType::ManualDebit,
            ]);

        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $items = [];
        foreach ($query->get() as $tx) {
            if ($tx->agent_deposit_request_id !== null) {
                $skipped[] = 'wallet_tx:'.$tx->id.' (covered by deposit)';

                continue;
            }

            $eventType = $this->walletEventType($tx);
            if ($eventType === null) {
                continue;
            }

            $context = $this->walletContext($tx);
            $projection = $this->posting->postFromRule($eventType, (float) $tx->amount, $context, persist: false);
            $items[] = $this->wrapProjection($eventType, $tx, $projection, $context);
        }

        return $items;
    }

    protected function walletEventType(AgentWalletTransaction $tx): ?string
    {
        $meta = is_array($tx->meta) ? $tx->meta : [];
        $isReversal = ! empty($meta['reversal_of_wallet_transaction_id']);

        return match ($tx->type) {
            AgentWalletTransactionType::DepositApproved => 'agency_deposit_approved',
            AgentWalletTransactionType::AdminCredit => 'wallet_admin_credit',
            AgentWalletTransactionType::AdminDebit => 'wallet_admin_debit',
            AgentWalletTransactionType::BookingHold => 'wallet_booking_hold',
            AgentWalletTransactionType::BookingRelease => 'wallet_booking_release',
            AgentWalletTransactionType::Adjustment => 'wallet_adjustment',
            AgentWalletTransactionType::ManualCredit => $isReversal ? 'manual_wallet_credit_reversal' : 'manual_wallet_credit',
            AgentWalletTransactionType::ManualDebit => $isReversal ? 'manual_wallet_debit_reversal' : 'manual_wallet_debit',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $projection
     * @return array<string, mixed>
     */
    protected function wrapProjection(string $eventType, object $source, array $projection, array $context): array
    {
        return [
            'label' => class_basename($source).':'.$source->getKey(),
            'event_type' => $eventType,
            'amount' => $projection['amount'],
            'debit_account' => $projection['debit_account'],
            'credit_account' => $projection['credit_account'],
            'lines' => $projection['lines'],
            'context' => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $projection
     */
    protected function isAlreadyPosted(array $projection): bool
    {
        $context = $projection['context'];
        if (! isset($context['source_type'], $context['source_id'], $context['transaction_type'])) {
            return false;
        }

        return $this->factory->sourceAlreadyPosted(
            (string) $context['source_type'],
            (int) $context['source_id'],
            $context['transaction_type'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function depositContext(AgentDepositRequest $deposit): array
    {
        $user = $deposit->user;

        return [
            'source_type' => $deposit->getMorphClass(),
            'source_id' => $deposit->id,
            'agency_id' => $deposit->agency_id,
            'actor_user_id' => $deposit->reviewed_by ?? $user?->id,
            'actor_identifier' => ActorIdentifier::forUserId($deposit->reviewed_by ?? $user?->id),
            'transaction_type' => LedgerTransactionType::AgencyDepositApproved,
            'description' => 'Approved agency deposit '.$deposit->reference,
            'occurred_at' => $deposit->reviewed_at ?? $deposit->created_at,
            'currency' => $deposit->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentContext(BookingPayment $payment, Booking $booking): array
    {
        $guestKey = null;
        $actorIdentifier = ActorIdentifier::forUserId($payment->payer_user_id);

        if ($booking->customer_id === null) {
            $contact = BookingContact::query()->where('booking_id', $booking->id)->first();
            if ($contact !== null && is_array($contact->meta)) {
                $guestId = (int) ($contact->meta['guest_id'] ?? 0);
                if ($guestId > 0) {
                    $guestKey = 'guest:'.$guestId;
                    $actorIdentifier = ActorIdentifier::forGuest($contact->meta);
                }
            }
        }

        return [
            'source_type' => $payment->getMorphClass(),
            'source_id' => $payment->id,
            'agency_id' => $payment->agency_id,
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'guest_key' => $guestKey,
            'actor_user_id' => $payment->payer_user_id,
            'actor_identifier' => $actorIdentifier,
            'transaction_type' => LedgerTransactionType::BookingPaymentVerified,
            'description' => 'Verified booking payment for '.$booking->booking_reference,
            'occurred_at' => $payment->verified_at ?? $payment->created_at,
            'currency' => $payment->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function refundContext(BookingRefund $refund): array
    {
        $type = $refund->status === BookingRefundStatus::Paid
            ? LedgerTransactionType::BookingRefundPaid
            : LedgerTransactionType::BookingRefundApproved;

        return [
            'source_type' => $refund->getMorphClass(),
            'source_id' => $refund->id,
            'agency_id' => $refund->agency_id,
            'booking_id' => $refund->booking_id,
            'transaction_type' => $type,
            'description' => 'Booking refund '.$refund->reference,
            'occurred_at' => $refund->paid_at ?? $refund->updated_at ?? $refund->created_at,
            'currency' => $refund->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function commissionContext(AgentCommissionEntry $entry): array
    {
        return [
            'source_type' => $entry->getMorphClass(),
            'source_id' => $entry->id,
            'agency_id' => $entry->agency_id,
            'booking_id' => $entry->booking_id,
            'transaction_type' => LedgerTransactionType::AgencyCommissionEarned,
            'description' => $entry->description ?? 'Agency commission earned',
            'occurred_at' => $entry->created_at,
            'currency' => $entry->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function markupContext(Booking $booking, BookingFareBreakdown $fare): array
    {
        return [
            'source_type' => $fare->getMorphClass(),
            'source_id' => $fare->id,
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'transaction_type' => LedgerTransactionType::MarkupRevenueRecognized,
            'description' => 'Markup revenue for '.$booking->booking_reference,
            'occurred_at' => $booking->confirmed_at ?? $booking->created_at,
            'currency' => $fare->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function walletContext(AgentWalletTransaction $tx): array
    {
        $eventType = $this->walletEventType($tx);
        $bookingId = is_array($tx->meta) ? (int) ($tx->meta['booking_id'] ?? 0) : 0;

        return [
            'source_type' => $tx->getMorphClass(),
            'source_id' => $tx->id,
            'agency_id' => $tx->agency_id,
            'booking_id' => $bookingId > 0 ? $bookingId : null,
            'actor_user_id' => $tx->created_by,
            'actor_identifier' => ActorIdentifier::forUserId($tx->created_by),
            'transaction_type' => LedgerTransactionType::tryFrom((string) $eventType) ?? LedgerTransactionType::WalletAdjustment,
            'description' => $tx->description ?? $tx->reference,
            'occurred_at' => $tx->created_at,
            'currency' => 'PKR',
        ];
    }
}
