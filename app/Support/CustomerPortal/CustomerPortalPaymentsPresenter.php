<?php

namespace App\Support\CustomerPortal;

use App\Models\BookingPayment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Customer payment and invoice history JSON presenters.
 */
class CustomerPortalPaymentsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(User $user, LengthAwarePaginator $items, string $filter): array
    {
        return [
            'ok' => true,
            'filter' => $filter,
            'allowed_filters' => ['all', 'pending', 'paid', 'failed'],
            'payments' => collect($items->items())
                ->map(fn (array $row) => $row)
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ];
    }

    /**
     * @return Builder<BookingPayment>
     */
    public function paymentProofQuery(User $user): Builder
    {
        return BookingPayment::query()
            ->whereHas('booking', fn (Builder $q) => $q->where('customer_id', $user->id))
            ->with(['booking'])
            ->orderByDesc('created_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPaymentProof(BookingPayment $payment): array
    {
        $booking = $payment->booking;
        $reference = $booking?->booking_reference;

        return [
            'reference' => filled($payment->payment_reference) ? (string) $payment->payment_reference : 'PAY-'.$payment->id,
            'booking_reference' => $booking?->display_reference,
            'date' => $payment->created_at?->toIso8601String(),
            'payment_method' => (string) ($payment->method?->value ?? $payment->method ?? 'manual'),
            'payment_method_label' => ucfirst(str_replace('_', ' ', (string) ($payment->method?->value ?? 'manual'))),
            'amount' => (float) $payment->amount,
            'currency' => (string) ($payment->currency ?? $booking?->currency ?? 'PKR'),
            'payment_status' => [
                'code' => (string) ($payment->status?->value ?? $payment->status ?? 'pending'),
                'label' => ucfirst(str_replace('_', ' ', (string) ($payment->status?->value ?? 'pending'))),
            ],
            'booking_status' => $booking ? CustomerPortalStatusPresenter::bookingStatus($booking) : null,
            'detail_url' => $reference ? '/customer/bookings/'.$reference : null,
            'retry_available' => false,
            'receipt_available' => false,
            'source' => 'payment_proof',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentGatewayTransaction(PaymentTransaction $transaction): array
    {
        $booking = $transaction->booking;
        $reference = $booking?->booking_reference;
        $status = (string) ($transaction->status?->value ?? $transaction->status ?? 'pending');

        return [
            'reference' => (string) ($transaction->client_transaction_id ?? $transaction->uuid),
            'booking_reference' => $booking?->display_reference,
            'date' => ($transaction->paid_at ?? $transaction->created_at)?->toIso8601String(),
            'payment_method' => 'card',
            'payment_method_label' => 'Card payment',
            'amount' => (float) $transaction->amount,
            'currency' => (string) ($transaction->currency ?? 'PKR'),
            'payment_status' => [
                'code' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
            ],
            'booking_status' => $booking ? CustomerPortalStatusPresenter::bookingStatus($booking) : null,
            'detail_url' => $reference ? '/customer/bookings/'.$reference : null,
            'retry_available' => in_array($status, ['failed', 'cancelled', 'declined', 'expired'], true)
                && $booking !== null
                && in_array((string) ($booking->payment_status ?? ''), ['unpaid', 'partial'], true),
            'receipt_available' => $status === 'paid',
            'source' => 'gateway',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function collectPaymentRows(User $user, string $filter): array
    {
        $proofRows = $this->paymentProofQuery($user)
            ->get()
            ->map(fn (BookingPayment $payment) => $this->presentPaymentProof($payment));

        $gatewayRows = PaymentTransaction::query()
            ->whereHas('booking', fn (Builder $q) => $q->where('customer_id', $user->id))
            ->with(['booking'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PaymentTransaction $tx) => $this->presentGatewayTransaction($tx));

        $merged = $proofRows->concat($gatewayRows)->sortByDesc('date')->values();

        if ($filter === 'pending') {
            $merged = $merged->filter(fn (array $row) => in_array($row['payment_status']['code'], ['pending', 'submitted', 'processing'], true));
        } elseif ($filter === 'paid') {
            $merged = $merged->filter(fn (array $row) => in_array($row['payment_status']['code'], ['paid', 'verified', 'succeeded'], true));
        } elseif ($filter === 'failed') {
            $merged = $merged->filter(fn (array $row) => in_array($row['payment_status']['code'], ['failed', 'rejected', 'cancelled', 'declined'], true));
        }

        return $merged->values()->all();
    }
}
