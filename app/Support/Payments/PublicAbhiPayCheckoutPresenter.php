<?php

namespace App\Support\Payments;

use App\Enums\BookingPaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentTransactionService;

/**
 * Public booking review/confirmation AbhiPay presentation (R12J).
 */
final class PublicAbhiPayCheckoutPresenter
{
    public function __construct(
        private readonly PaymentTransactionService $paymentTransactionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBooking(Booking $booking, bool $afterSubmission = false): array
    {
        $booking->loadMissing(['fareBreakdown', 'payments']);
        $gatewayAvailable = $this->paymentTransactionService->isAbhiPayAvailableForBooking($booking);
        $payableAmount = $this->paymentTransactionService->payableAmountForBooking($booking);
        $blockedMessage = $this->paymentTransactionService->abhiPayStartBlockedMessage($booking);
        $canStart = $gatewayAvailable && $blockedMessage === null && $this->paymentTransactionService->canStartAbhiPayForBooking($booking);
        $latestTransaction = $this->latestAbhiPayTransaction($booking);
        $verifiedPaid = (float) $booking->payments()
            ->where('status', BookingPaymentStatus::Verified)
            ->sum('amount') > 0
            || (string) ($booking->payment_status ?? '') === 'paid';

        $transactionStatus = $latestTransaction?->status?->value;
        $statusLabel = match (true) {
            $verifiedPaid => 'Paid',
            $transactionStatus === PaymentTransactionStatus::Paid->value => 'Paid',
            in_array($transactionStatus, [
                PaymentTransactionStatus::Initiated->value,
                PaymentTransactionStatus::Created->value,
                PaymentTransactionStatus::Pending->value,
            ], true) => 'Payment pending',
            in_array($transactionStatus, [
                PaymentTransactionStatus::Failed->value,
                PaymentTransactionStatus::Declined->value,
                PaymentTransactionStatus::VerificationFailed->value,
            ], true) => 'Payment failed',
            default => 'Unpaid',
        };

        $showPayButton = $afterSubmission
            && $gatewayAvailable
            && $canStart
            && $payableAmount > 0
            && ! $verifiedPaid
            && ! in_array($transactionStatus, [PaymentTransactionStatus::Paid->value], true);

        return [
            'gateway_available' => $gatewayAvailable,
            'can_start' => $canStart,
            'blocked_message' => $blockedMessage,
            'payable_amount' => $payableAmount,
            'currency' => (string) ($booking->currency ?? 'PKR'),
            'payment_status_label' => $statusLabel,
            'show_pay_button' => $showPayButton,
            'show_review_option' => ! $afterSubmission && $gatewayAvailable && $payableAmount > 0,
            'ticketing_note' => 'Ticketing will happen after payment verification.',
            'latest_transaction_reference' => $latestTransaction?->client_transaction_id,
        ];
    }

    private function latestAbhiPayTransaction(Booking $booking): ?PaymentTransaction
    {
        return PaymentTransaction::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::CODE_ABHIPAY)
            ->latest('id')
            ->first();
    }
}
