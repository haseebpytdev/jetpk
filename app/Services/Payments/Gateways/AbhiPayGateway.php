<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTO\PaymentGatewayCallbackResult;
use App\Services\Payments\DTO\PaymentGatewayCreateResult;
use App\Services\Payments\DTO\PaymentGatewayVerifyResult;
use App\Support\Payments\PaymentGatewayPayloadRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AbhiPay v3 gateway driver — order create, callback lookup, and server-side verification.
 */
class AbhiPayGateway implements PaymentGatewayInterface
{
    public const SUCCESS_RESULT_CODE = '00000';

    /** @var list<string> */
    protected const PAID_STATUSES = [
        'paid',
        'success',
        'successful',
        'approved',
        'completed',
        'captured',
    ];

    /** @var list<string> */
    protected const FAILED_STATUSES = [
        'failed',
        'declined',
        'cancelled',
        'canceled',
        'expired',
        'rejected',
    ];

    public function createPayment(PaymentTransaction $transaction): PaymentGatewayCreateResult
    {
        $settings = $this->resolveSettings($transaction);
        if ($settings === null) {
            return new PaymentGatewayCreateResult(
                success: false,
                errorMessage: 'AbhiPay gateway is not configured.',
            );
        }

        $booking = $transaction->booking;
        $description = $booking !== null
            ? 'OTA Booking '.($booking->booking_reference ?? $booking->id)
            : 'OTA Payment '.$transaction->client_transaction_id;

        $payload = [
            'amount' => $this->toMinorUnits((float) $transaction->amount),
            'language' => 'EN',
            'currency' => strtoupper((string) $transaction->currency),
            'description' => $description,
            'clientTransactionId' => $transaction->client_transaction_id,
            'callbackUrl' => $settings->callback_url ?: route('payments.abhipay.callback'),
            'cardSave' => false,
            'operation' => 'PURCHASE',
        ];

        try {
            $response = $this->client($settings)->post('/orders', $payload);
        } catch (ConnectionException $e) {
            Log::warning('abhipay_create_order_connection_failed', [
                'payment_transaction_id' => $transaction->id,
                'client_transaction_id' => $transaction->client_transaction_id,
            ]);

            return new PaymentGatewayCreateResult(
                success: false,
                errorMessage: 'Unable to reach AbhiPay. Please try again shortly.',
            );
        }

        $body = $response->json();
        $safeResponse = [
            'request' => PaymentGatewayPayloadRedactor::redact($payload),
            'response' => PaymentGatewayPayloadRedactor::redact(is_array($body) ? $body : ['raw' => $response->body()]),
        ];

        if (! $response->successful()) {
            return new PaymentGatewayCreateResult(
                success: false,
                gatewayCode: (string) data_get($body, 'resultCode', ''),
                gatewayMessage: (string) data_get($body, 'resultMessage', 'AbhiPay order creation failed.'),
                safeResponse: $safeResponse,
                errorMessage: 'AbhiPay could not create the payment order.',
            );
        }

        $resultCode = (string) data_get($body, 'resultCode', '');
        $orderId = (string) data_get($body, 'payload.orderId', data_get($body, 'orderId', ''));
        $paymentUrl = (string) data_get($body, 'payload.paymentUrl', data_get($body, 'paymentUrl', ''));

        if ($resultCode !== self::SUCCESS_RESULT_CODE || ! filled($paymentUrl)) {
            return new PaymentGatewayCreateResult(
                success: false,
                gatewayOrderId: filled($orderId) ? $orderId : null,
                gatewayCode: $resultCode,
                gatewayMessage: (string) data_get($body, 'resultMessage', 'Unexpected AbhiPay response.'),
                safeResponse: $safeResponse,
                errorMessage: 'AbhiPay did not return a payment URL.',
            );
        }

        return new PaymentGatewayCreateResult(
            success: true,
            redirectUrl: $paymentUrl,
            gatewayOrderId: $orderId,
            gatewayCode: $resultCode,
            gatewayMessage: (string) data_get($body, 'resultMessage', ''),
            safeResponse: $safeResponse,
        );
    }

    public function verifyPayment(PaymentTransaction $transaction): PaymentGatewayVerifyResult
    {
        $settings = $this->resolveSettings($transaction);
        if ($settings === null) {
            return new PaymentGatewayVerifyResult(
                verified: false,
                status: PaymentTransactionStatus::VerificationFailed,
                failureReason: 'AbhiPay gateway is not configured.',
            );
        }

        $body = $this->fetchVerificationPayload($settings, $transaction);
        if ($body === null) {
            return new PaymentGatewayVerifyResult(
                verified: false,
                status: PaymentTransactionStatus::VerificationFailed,
                failureReason: 'Unable to verify payment with AbhiPay.',
            );
        }

        return $this->interpretVerificationPayload($transaction, $body);
    }

    public function handleCallback(Request $request): PaymentGatewayCallbackResult
    {
        $payload = $request->all();
        $safePayload = PaymentGatewayPayloadRedactor::redact(is_array($payload) ? $payload : []);

        $clientTransactionId = (string) (
            data_get($payload, 'clientTransactionId')
            ?? data_get($payload, 'client_transaction_id')
            ?? data_get($payload, 'rrn')
            ?? ''
        );
        $orderId = (string) (
            data_get($payload, 'orderId')
            ?? data_get($payload, 'order_id')
            ?? data_get($payload, 'payload.orderId')
            ?? ''
        );

        $transaction = null;
        if (filled($clientTransactionId)) {
            $transaction = PaymentTransaction::query()
                ->where('client_transaction_id', $clientTransactionId)
                ->first();
        }

        if ($transaction === null && filled($orderId)) {
            $transaction = PaymentTransaction::query()
                ->where('gateway_order_id', $orderId)
                ->first();
        }

        return new PaymentGatewayCallbackResult(
            transaction: $transaction,
            clientTransactionId: filled($clientTransactionId) ? $clientTransactionId : null,
            gatewayOrderId: filled($orderId) ? $orderId : null,
            safeCallbackPayload: $safePayload,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchVerificationPayload(PaymentGateway $settings, PaymentTransaction $transaction): ?array
    {
        try {
            if (filled($transaction->gateway_order_id)) {
                $response = $this->client($settings)->get('/orders/'.$transaction->gateway_order_id);
                if ($response->successful()) {
                    $body = $response->json();
                    if (is_array($body)) {
                        return $body;
                    }
                }
            }

            $response = $this->client($settings)->get('/orders/by-rrn/'.$transaction->client_transaction_id);
            if ($response->successful()) {
                $body = $response->json();
                if (is_array($body)) {
                    return $body;
                }
            }
        } catch (ConnectionException $e) {
            Log::warning('abhipay_verify_connection_failed', [
                'payment_transaction_id' => $transaction->id,
                'client_transaction_id' => $transaction->client_transaction_id,
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function interpretVerificationPayload(PaymentTransaction $transaction, array $body): PaymentGatewayVerifyResult
    {
        $resultCode = (string) data_get($body, 'resultCode', '');
        $payload = data_get($body, 'payload', $body);
        if (! is_array($payload)) {
            $payload = [];
        }

        $gatewayOrderId = (string) data_get($payload, 'orderId', $transaction->gateway_order_id);
        $gatewayStatus = strtolower((string) data_get(
            $payload,
            'paymentStatus',
            data_get($payload, 'status', data_get($payload, 'orderStatus', ''))
        ));
        $gatewayCode = $resultCode !== '' ? $resultCode : (string) data_get($payload, 'resultCode', '');
        $gatewayMessage = (string) data_get($body, 'resultMessage', data_get($payload, 'message', ''));

        $remoteClientId = (string) data_get(
            $payload,
            'clientTransactionId',
            data_get($payload, 'rrn', '')
        );
        if (filled($remoteClientId) && $remoteClientId !== $transaction->client_transaction_id) {
            return new PaymentGatewayVerifyResult(
                verified: false,
                status: PaymentTransactionStatus::VerificationFailed,
                gatewayOrderId: filled($gatewayOrderId) ? $gatewayOrderId : null,
                gatewayStatus: $gatewayStatus,
                gatewayCode: $gatewayCode,
                gatewayMessage: $gatewayMessage,
                safeResponse: PaymentGatewayPayloadRedactor::redact($body),
                failureReason: 'Client transaction id mismatch.',
            );
        }

        $remoteCurrency = strtoupper((string) data_get($payload, 'currency', $transaction->currency));
        if ($remoteCurrency !== '' && $remoteCurrency !== strtoupper((string) $transaction->currency)) {
            return new PaymentGatewayVerifyResult(
                verified: false,
                status: PaymentTransactionStatus::VerificationFailed,
                gatewayOrderId: filled($gatewayOrderId) ? $gatewayOrderId : null,
                gatewayStatus: $gatewayStatus,
                gatewayCode: $gatewayCode,
                gatewayMessage: $gatewayMessage,
                safeResponse: PaymentGatewayPayloadRedactor::redact($body),
                failureReason: 'Currency mismatch.',
            );
        }

        $remoteAmount = data_get($payload, 'amount');
        if ($remoteAmount !== null && ! $this->amountMatches((float) $transaction->amount, $remoteAmount)) {
            return new PaymentGatewayVerifyResult(
                verified: false,
                status: PaymentTransactionStatus::VerificationFailed,
                gatewayOrderId: filled($gatewayOrderId) ? $gatewayOrderId : null,
                gatewayStatus: $gatewayStatus,
                gatewayCode: $gatewayCode,
                gatewayMessage: $gatewayMessage,
                safeResponse: PaymentGatewayPayloadRedactor::redact($body),
                failureReason: 'Amount mismatch.',
            );
        }

        if ($this->isPaidStatus($gatewayStatus) && $gatewayCode === self::SUCCESS_RESULT_CODE) {
            return new PaymentGatewayVerifyResult(
                verified: true,
                status: PaymentTransactionStatus::Paid,
                gatewayOrderId: filled($gatewayOrderId) ? $gatewayOrderId : null,
                gatewayStatus: $gatewayStatus,
                gatewayCode: $gatewayCode,
                gatewayMessage: $gatewayMessage,
                safeResponse: PaymentGatewayPayloadRedactor::redact($body),
                maskedCard: $this->extractMaskedCard($payload),
            );
        }

        if ($this->isFailedStatus($gatewayStatus)) {
            $status = match (true) {
                in_array($gatewayStatus, ['declined', 'rejected'], true) => PaymentTransactionStatus::Declined,
                in_array($gatewayStatus, ['cancelled', 'canceled'], true) => PaymentTransactionStatus::Cancelled,
                $gatewayStatus === 'expired' => PaymentTransactionStatus::Expired,
                default => PaymentTransactionStatus::Failed,
            };

            return new PaymentGatewayVerifyResult(
                verified: true,
                status: $status,
                gatewayOrderId: filled($gatewayOrderId) ? $gatewayOrderId : null,
                gatewayStatus: $gatewayStatus,
                gatewayCode: $gatewayCode,
                gatewayMessage: $gatewayMessage,
                safeResponse: PaymentGatewayPayloadRedactor::redact($body),
            );
        }

        return new PaymentGatewayVerifyResult(
            verified: false,
            status: PaymentTransactionStatus::Pending,
            gatewayOrderId: filled($gatewayOrderId) ? $gatewayOrderId : null,
            gatewayStatus: $gatewayStatus,
            gatewayCode: $gatewayCode,
            gatewayMessage: $gatewayMessage,
            safeResponse: PaymentGatewayPayloadRedactor::redact($body),
            failureReason: 'Payment not confirmed by AbhiPay yet.',
        );
    }

    protected function resolveSettings(PaymentTransaction $transaction): ?PaymentGateway
    {
        $agencyId = $transaction->booking?->agency_id;
        $query = PaymentGateway::query()
            ->where('code', PaymentGateway::CODE_ABHIPAY)
            ->where('is_active', true);

        if ($agencyId !== null) {
            $query->where(function ($builder) use ($agencyId): void {
                $builder->where('agency_id', $agencyId)->orWhereNull('agency_id');
            })->orderByRaw('CASE WHEN agency_id = ? THEN 0 ELSE 1 END', [$agencyId]);
        }

        $gateway = $query->first();

        return $gateway !== null && $gateway->isConfigured() ? $gateway : null;
    }

    protected function client(PaymentGateway $settings): PendingRequest
    {
        $baseUrl = rtrim((string) ($settings->base_url ?: PaymentGateway::DEFAULT_BASE_URL), '/');

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => (string) $settings->merchant_secret_key,
            ])
            ->timeout(20);
    }

    protected function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function amountMatches(float $localAmount, mixed $remoteAmount): bool
    {
        if (is_numeric($remoteAmount)) {
            $remote = (float) $remoteAmount;
            if ($remote > $localAmount * 10) {
                $remote = $remote / 100;
            }

            return abs($localAmount - $remote) < 0.01;
        }

        return false;
    }

    protected function isPaidStatus(string $status): bool
    {
        return in_array(strtolower($status), self::PAID_STATUSES, true);
    }

    protected function isFailedStatus(string $status): bool
    {
        return in_array(strtolower($status), self::FAILED_STATUSES, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function extractMaskedCard(array $payload): ?array
    {
        $pan = data_get($payload, 'maskedPan', data_get($payload, 'card.maskedPan', data_get($payload, 'cardPan')));
        if (! filled($pan)) {
            return null;
        }

        return [
            'masked_pan' => PaymentGatewayPayloadRedactor::redact(['pan' => $pan])['pan'] ?? '****',
            'brand' => data_get($payload, 'cardBrand', data_get($payload, 'card.brand')),
        ];
    }
}
