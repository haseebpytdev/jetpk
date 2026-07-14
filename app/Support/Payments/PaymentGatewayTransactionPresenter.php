<?php

namespace App\Support\Payments;

use App\Models\Booking;
use App\Models\PaymentTransaction;

/**
 * Safe gateway transaction summary for operator booking views.
 */
class PaymentGatewayTransactionPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function latestForBooking(Booking $booking): ?array
    {
        $transaction = $booking->relationLoaded('latestPaymentTransaction')
            ? $booking->latestPaymentTransaction
            : $booking->paymentTransactions()->latest('id')->first();

        if ($transaction === null) {
            return null;
        }

        return self::present($transaction);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(PaymentTransaction $transaction): array
    {
        $meta = is_array(data_get($transaction->response_payload_json, 'payload'))
            ? data_get($transaction->response_payload_json, 'payload')
            : [];

        return [
            'gateway' => (string) $transaction->gateway,
            'environment' => (string) $transaction->environment,
            'amount' => (float) $transaction->amount,
            'currency' => (string) $transaction->currency,
            'status' => (string) $transaction->status->value,
            'client_transaction_id' => (string) $transaction->client_transaction_id,
            'gateway_order_id' => $transaction->gateway_order_id,
            'gateway_status' => $transaction->gateway_status,
            'paid_at' => $transaction->paid_at?->format('j M Y, g:i A'),
            'verified_at' => $transaction->verified_at?->format('j M Y, g:i A'),
            'masked_card' => data_get($meta, 'maskedPan') ?? data_get($transaction->response_payload_json, 'masked_card.masked_pan'),
        ];
    }
}
