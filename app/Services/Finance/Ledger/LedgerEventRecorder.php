<?php

namespace App\Services\Finance\Ledger;

use App\Enums\AgentCommissionEntryStatus;
use App\Enums\AgentCommissionEntryType;
use App\Enums\BookingPaymentMethod;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\AgentCommissionEntry;
use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Support\Identity\ActorIdentifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Go-forward parallel ledger posting for live finance events (Phase 4).
 * Idempotent; failures are logged and never block source-of-truth updates.
 */
class LedgerEventRecorder
{
    public function __construct(
        protected LedgerPostingService $posting,
        protected LedgerTransactionFactory $factory,
        protected LedgerAccountService $accounts,
    ) {}

    public function recordAgencyDepositApproved(AgentDepositRequest $deposit, ?User $actor = null): ?LedgerTransaction
    {
        return $this->safeRecord(function () use ($deposit, $actor): ?LedgerTransaction {
            $type = LedgerTransactionType::AgencyDepositApproved;
            $existing = $this->findExistingPost($deposit, $type);
            if ($existing !== null) {
                return $existing;
            }

            $deposit->loadMissing('agency');
            $amount = (float) $deposit->amount;
            $context = [
                'source_type' => $deposit->getMorphClass(),
                'source_id' => $deposit->id,
                'agency_id' => $deposit->agency_id,
                'actor_user_id' => $actor?->id ?? $deposit->reviewed_by,
                'actor_identifier' => ActorIdentifier::forUser($actor ?? User::query()->find($deposit->reviewed_by)),
                'transaction_type' => $type,
                'description' => 'Approved agency deposit '.$deposit->reference,
                'occurred_at' => $deposit->reviewed_at ?? now(),
                'currency' => $deposit->currency ?? 'PKR',
                'properties' => $this->buildProperties(
                    model: $deposit,
                    method: 'recordAgencyDepositApproved',
                    extra: [
                        'status' => $deposit->status->value ?? (string) $deposit->status,
                        'reference' => $deposit->reference,
                        'original_amount' => $amount,
                        'agency_id' => $deposit->agency_id,
                    ],
                ),
            ];

            return $this->posting->postFromRule('agency_deposit_approved', $amount, $context);
        }, 'recordAgencyDepositApproved', $deposit);
    }

    public function recordBookingPaymentVerified(BookingPayment $payment, ?User $actor = null): ?LedgerTransaction
    {
        return $this->safeRecord(function () use ($payment, $actor): ?LedgerTransaction {
            $type = LedgerTransactionType::BookingPaymentVerified;
            $existing = $this->findExistingPost($payment, $type);
            if ($existing !== null) {
                return $existing;
            }

            $payment->loadMissing(['booking.contact', 'booking.customer']);
            $booking = $payment->booking;
            if ($booking === null) {
                return null;
            }

            $amount = (float) $payment->amount;
            $context = $this->paymentContext($payment, $booking, $actor);

            $transaction = $this->factory->createDraftTransaction(array_merge($context, [
                'amount_total' => $amount,
                'properties' => $this->buildProperties(
                    model: $payment,
                    method: 'recordBookingPaymentVerified',
                    extra: [
                        'method' => $payment->method->value,
                        'status' => $payment->status->value,
                        'reference' => $payment->payment_reference,
                        'original_amount' => $amount,
                        'currency' => $payment->currency,
                        'booking_reference' => $booking->booking_reference,
                        'agency_id' => $payment->agency_id,
                        'debit_account' => $this->resolvePaymentDebitAccount($payment->method),
                    ],
                ),
            ]));

            $debitAccount = $this->resolvePaymentDebitAccount($payment->method);
            $bookingId = $booking->id;

            return $this->posting->begin($transaction)
                ->addDebit($debitAccount, $amount, null, $bookingId, $context['description'] ?? null)
                ->addCredit('CUSTOMER_BOOKING_LIABILITY', $amount, $payment->agency_id, $bookingId, $context['description'] ?? null)
                ->post();
        }, 'recordBookingPaymentVerified', $payment);
    }

    public function recordBookingRefundApproved(BookingRefund $refund, ?User $actor = null): ?LedgerTransaction
    {
        return $this->safeRecord(function () use ($refund, $actor): ?LedgerTransaction {
            $type = LedgerTransactionType::BookingRefundApproved;
            $existing = $this->findExistingPost($refund, $type);
            if ($existing !== null) {
                return $existing;
            }

            $refund->loadMissing('booking');
            $amount = (float) $refund->amount;
            $context = $this->refundContext($refund, $actor, $type);

            return $this->posting->postFromRule('booking_refund_approved', $amount, $context);
        }, 'recordBookingRefundApproved', $refund);
    }

    public function recordBookingRefundPaid(BookingRefund $refund, ?User $actor = null): ?LedgerTransaction
    {
        return $this->safeRecord(function () use ($refund, $actor): ?LedgerTransaction {
            $type = LedgerTransactionType::BookingRefundPaid;
            $existing = $this->findExistingPost($refund, $type);
            if ($existing !== null) {
                return $existing;
            }

            $refund->loadMissing('booking');
            $amount = (float) $refund->amount;
            $context = $this->refundContext($refund, $actor, $type);

            return $this->posting->postFromRule('booking_refund_paid', $amount, $context);
        }, 'recordBookingRefundPaid', $refund);
    }

    public function recordAgencyCommissionEarned(AgentCommissionEntry $entry, ?User $actor = null): ?LedgerTransaction
    {
        return $this->safeRecord(function () use ($entry, $actor): ?LedgerTransaction {
            if ($entry->type !== AgentCommissionEntryType::Earned) {
                return null;
            }

            if ($entry->status !== AgentCommissionEntryStatus::Approved) {
                return null;
            }

            $type = LedgerTransactionType::AgencyCommissionEarned;
            $existing = $this->findExistingPost($entry, $type);
            if ($existing !== null) {
                return $existing;
            }

            $entry->loadMissing('booking');
            $amount = (float) $entry->commission_amount;
            if ($amount <= 0) {
                return null;
            }

            $context = [
                'source_type' => $entry->getMorphClass(),
                'source_id' => $entry->id,
                'agency_id' => $entry->agency_id,
                'booking_id' => $entry->booking_id,
                'actor_user_id' => $actor?->id ?? $entry->approved_by,
                'actor_identifier' => ActorIdentifier::forUser($actor ?? User::query()->find($entry->approved_by)),
                'transaction_type' => $type,
                'description' => $entry->description ?? 'Agency commission earned',
                'occurred_at' => $entry->approved_at ?? $entry->created_at ?? now(),
                'currency' => $entry->currency ?? 'PKR',
                'properties' => $this->buildProperties(
                    model: $entry,
                    method: 'recordAgencyCommissionEarned',
                    extra: [
                        'status' => $entry->status->value,
                        'original_amount' => $amount,
                        'booking_reference' => $entry->booking?->booking_reference,
                        'agency_id' => $entry->agency_id,
                    ],
                ),
            ];

            return $this->posting->postFromRule('agency_commission_earned', $amount, $context);
        }, 'recordAgencyCommissionEarned', $entry);
    }

    public function recordMarkupRevenueRecognized(BookingFareBreakdown $fareBreakdown, ?User $actor = null): ?LedgerTransaction
    {
        return $this->safeRecord(function () use ($fareBreakdown, $actor): ?LedgerTransaction {
            if ((float) $fareBreakdown->markup <= 0) {
                return null;
            }

            $type = LedgerTransactionType::MarkupRevenueRecognized;
            $existing = $this->findExistingPost($fareBreakdown, $type);
            if ($existing !== null) {
                return $existing;
            }

            $fareBreakdown->loadMissing('booking');
            $booking = $fareBreakdown->booking;
            if ($booking === null) {
                return null;
            }

            $amount = (float) $fareBreakdown->markup;
            $context = [
                'source_type' => $fareBreakdown->getMorphClass(),
                'source_id' => $fareBreakdown->id,
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'actor_user_id' => $actor?->id,
                'actor_identifier' => ActorIdentifier::forUser($actor),
                'transaction_type' => $type,
                'description' => 'Markup revenue for '.$booking->booking_reference,
                'occurred_at' => $booking->confirmed_at ?? $booking->ticketed_at ?? now(),
                'currency' => $fareBreakdown->currency ?? 'PKR',
                'properties' => $this->buildProperties(
                    model: $fareBreakdown,
                    method: 'recordMarkupRevenueRecognized',
                    extra: [
                        'original_amount' => $amount,
                        'booking_reference' => $booking->booking_reference,
                        'agency_id' => $booking->agency_id,
                    ],
                ),
            ];

            return $this->posting->postFromRule('markup_revenue_recognized', $amount, $context);
        }, 'recordMarkupRevenueRecognized', $fareBreakdown);
    }

    public function recordMarkupRevenueForBooking(Booking $booking, ?User $actor = null): ?LedgerTransaction
    {
        $booking->loadMissing('fareBreakdown');
        $fare = $booking->fareBreakdown;
        if ($fare === null) {
            return null;
        }

        return $this->recordMarkupRevenueRecognized($fare, $actor);
    }

    /**
     * Manual wallet credit — throws on failure (used inside wallet adjustment DB transaction).
     */
    public function recordManualWalletCredit(AgentWalletTransaction $walletTransaction, ?User $actor = null): LedgerTransaction
    {
        return $this->recordManualWalletAdjustment($walletTransaction, LedgerTransactionType::ManualWalletCredit, 'manual_wallet_credit', $actor);
    }

    /**
     * Manual wallet debit — throws on failure (used inside wallet adjustment DB transaction).
     */
    public function recordManualWalletDebit(AgentWalletTransaction $walletTransaction, ?User $actor = null): LedgerTransaction
    {
        return $this->recordManualWalletAdjustment($walletTransaction, LedgerTransactionType::ManualWalletDebit, 'manual_wallet_debit', $actor);
    }

    public function recordManualWalletCreditReversal(AgentWalletTransaction $walletTransaction, ?User $actor = null): LedgerTransaction
    {
        return $this->recordManualWalletAdjustment($walletTransaction, LedgerTransactionType::ManualWalletCreditReversal, 'manual_wallet_credit_reversal', $actor);
    }

    public function recordManualWalletDebitReversal(AgentWalletTransaction $walletTransaction, ?User $actor = null): LedgerTransaction
    {
        return $this->recordManualWalletAdjustment($walletTransaction, LedgerTransactionType::ManualWalletDebitReversal, 'manual_wallet_debit_reversal', $actor);
    }

    protected function recordManualWalletAdjustment(
        AgentWalletTransaction $walletTransaction,
        LedgerTransactionType $type,
        string $ruleEventType,
        ?User $actor = null,
    ): LedgerTransaction {
        $this->accounts->ensureAccountsExist();

        $existing = $this->findExistingPost($walletTransaction, $type);
        if ($existing !== null) {
            return $existing;
        }

        $walletTransaction->loadMissing('agency');
        $amount = (float) $walletTransaction->amount;
        $reason = is_array($walletTransaction->meta) ? ($walletTransaction->meta['adjustment_reason'] ?? null) : null;

        $context = [
            'source_type' => $walletTransaction->getMorphClass(),
            'source_id' => $walletTransaction->id,
            'agency_id' => $walletTransaction->agency_id,
            'actor_user_id' => $actor?->id ?? $walletTransaction->approved_by,
            'actor_identifier' => ActorIdentifier::forUser($actor ?? User::query()->find($walletTransaction->approved_by)),
            'transaction_type' => $type,
            'description' => $walletTransaction->description ?? 'Manual wallet adjustment',
            'occurred_at' => $walletTransaction->created_at ?? now(),
            'currency' => $walletTransaction->wallet?->currency ?? 'PKR',
            'properties' => $this->buildProperties(
                model: $walletTransaction,
                method: $type->value,
                extra: [
                    'original_amount' => $amount,
                    'agency_id' => $walletTransaction->agency_id,
                    'adjustment_reason' => $reason,
                    'wallet_transaction_type' => $walletTransaction->type->value,
                ],
            ),
        ];

        return $this->posting->postFromRule($ruleEventType, $amount, $context);
    }

    protected function resolvePaymentDebitAccount(BookingPaymentMethod $method): string
    {
        return match ($method) {
            BookingPaymentMethod::Cash, BookingPaymentMethod::BankTransfer => 'PLATFORM_CASH',
            default => 'PAYMENT_GATEWAY_CLEARING',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentContext(BookingPayment $payment, Booking $booking, ?User $actor): array
    {
        $guestKey = null;

        if ($booking->customer_id === null) {
            $contact = $booking->relationLoaded('contact') ? $booking->contact : BookingContact::query()->where('booking_id', $booking->id)->first();
            if ($contact !== null && is_array($contact->meta)) {
                $guestId = (int) ($contact->meta['guest_id'] ?? 0);
                if ($guestId > 0) {
                    $guestKey = 'guest:'.$guestId;
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
            'actor_user_id' => $actor?->id,
            'actor_identifier' => ActorIdentifier::forUser($actor),
            'transaction_type' => LedgerTransactionType::BookingPaymentVerified,
            'description' => 'Verified booking payment for '.$booking->booking_reference,
            'occurred_at' => $payment->verified_at ?? now(),
            'currency' => $payment->currency ?? 'PKR',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function refundContext(BookingRefund $refund, ?User $actor, LedgerTransactionType $type): array
    {
        return [
            'source_type' => $refund->getMorphClass(),
            'source_id' => $refund->id,
            'agency_id' => $refund->agency_id,
            'booking_id' => $refund->booking_id,
            'actor_user_id' => $actor?->id,
            'actor_identifier' => ActorIdentifier::forUser($actor),
            'transaction_type' => $type,
            'description' => 'Booking refund '.$refund->reference,
            'occurred_at' => $refund->paid_at ?? $refund->approved_at ?? now(),
            'currency' => $refund->currency ?? 'PKR',
            'properties' => $this->buildProperties(
                model: $refund,
                method: $type === LedgerTransactionType::BookingRefundPaid
                    ? 'recordBookingRefundPaid'
                    : 'recordBookingRefundApproved',
                extra: [
                    'status' => $refund->status->value,
                    'reference' => $refund->reference,
                    'original_amount' => (float) $refund->amount,
                    'booking_reference' => $refund->booking?->booking_reference,
                    'agency_id' => $refund->agency_id,
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function buildProperties(Model $model, string $method, array $extra = []): array
    {
        return array_merge([
            'source_model' => class_basename($model),
            'source_id' => $model->getKey(),
            'created_by_service' => self::class,
            'recorder_method' => $method,
        ], $extra);
    }

    protected function findExistingPost(Model $source, LedgerTransactionType $type): ?LedgerTransaction
    {
        if (! $this->factory->sourceAlreadyPosted($source->getMorphClass(), (int) $source->getKey(), $type)) {
            return null;
        }

        return LedgerTransaction::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('transaction_type', $type)
            ->whereIn('status', [
                LedgerTransactionStatus::Posted,
                LedgerTransactionStatus::Pending,
                LedgerTransactionStatus::Draft,
            ])
            ->first();
    }

    protected function safeRecord(callable $callback, string $method, Model $source): ?LedgerTransaction
    {
        try {
            $this->accounts->ensureAccountsExist();

            return $callback();
        } catch (\Throwable $e) {
            Log::error('Ledger live posting failed', [
                'recorder_method' => $method,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
