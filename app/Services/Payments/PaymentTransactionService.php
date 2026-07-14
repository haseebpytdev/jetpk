<?php

namespace App\Services\Payments;

use App\Enums\AccountType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\DTO\PaymentGatewayVerifyResult;
use App\Services\Promos\PromoCodeService;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingStatusRefreshService;
use App\Support\Payments\BookingPayableResolver;
use App\Support\Payments\PaymentGatewayPayloadRedactor;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Orchestrates gateway payment transactions and verified booking settlement.
 */
class PaymentTransactionService
{
    public function __construct(
        protected PaymentGatewayResolver $gatewayResolver,
        protected BookingPaymentService $bookingPaymentService,
        protected CompactReferenceGenerator $referenceGenerator,
        protected PromoCodeService $promoCodeService,
        protected PiaNdcBookingStatusRefreshService $piaNdcStatusRefreshService,
    ) {}

    public function activeAbhiPayGateway(?int $agencyId = null): ?PaymentGateway
    {
        $query = PaymentGateway::query()
            ->where('code', PaymentGateway::CODE_ABHIPAY)
            ->where('is_active', true);

        if ($agencyId !== null) {
            $query->where(function ($builder) use ($agencyId): void {
                $builder->where('agency_id', $agencyId)
                    ->orWhereNull('agency_id');
            })->orderByRaw('CASE WHEN agency_id = ? THEN 0 ELSE 1 END', [$agencyId]);
        } else {
            $query->whereNull('agency_id');
        }

        $gateway = $query->first();
        if ($gateway === null || ! $gateway->isAvailableForCheckout()) {
            return null;
        }

        return $gateway;
    }

    public function isAbhiPayAvailableForBooking(Booking $booking): bool
    {
        return $this->activeAbhiPayGateway($booking->agency_id) !== null;
    }

    public function canOfferAbhiPayOnPublicReview(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Cancelled) {
            return false;
        }

        if (! $this->isAbhiPayAvailableForBooking($booking)) {
            return false;
        }

        $amount = $this->payableAmountForBooking($booking);

        return $amount > 0 || BookingPayableResolver::allowsZeroPayableCheckout($booking);
    }

    public function canShowAbhiPayOnPublicConfirmation(Booking $booking): bool
    {
        return $this->isAbhiPayAvailableForBooking($booking) && $this->canStartAbhiPayForBooking($booking);
    }

    public function piaNdcOnlinePaymentBlockedReason(Booking $booking): ?string
    {
        if (! $this->isPiaNdcBooking($booking)) {
            return null;
        }

        $inactiveMessage = 'Airline reservation must be active before online payment.';
        $missingMessage = 'Airline reservation must be created before online payment.';

        if ($booking->status === BookingStatus::Cancelled) {
            return $inactiveMessage;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];

        if (($context['option_pnr_released'] ?? false) === true || ($context['cancel_committed'] ?? false) === true) {
            return $inactiveMessage;
        }

        $interpreted = strtolower(trim((string) ($context['interpreted_status'] ?? '')));
        if (in_array($interpreted, ['released', 'no_active_segments', 'ticketed'], true)) {
            return $inactiveMessage;
        }

        if ((int) ($context['segment_count'] ?? -1) === 0
            && empty($context['payment_time_limit'])
            && empty($context['ticket_numbers'])) {
            $lastRefresh = is_array($meta['pia_ndc_last_status_refresh'] ?? null) ? $meta['pia_ndc_last_status_refresh'] : [];
            if (($lastRefresh['checked_at'] ?? '') !== '') {
                return $inactiveMessage;
            }
        }

        $supplierStatus = strtolower(trim((string) ($booking->supplier_booking_status ?? '')));
        if (in_array($supplierStatus, ['cancelled', 'released', 'closed', 'voided'], true)) {
            return $inactiveMessage;
        }

        $orderStatus = strtolower(trim((string) ($context['order_status'] ?? '')));
        if (in_array($orderStatus, ['closed', 'cancelled', 'canceled'], true)) {
            return $inactiveMessage;
        }

        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierRef = trim((string) ($booking->supplier_reference ?? ''));
        if ($pnr === '' && $supplierRef === '') {
            if (($meta['pia_ndc_auto_option_pnr']['status'] ?? '') === 'failed') {
                return $missingMessage;
            }

            return $missingMessage;
        }

        $activeStatuses = [
            'option_pnr_created',
            'pending_payment_or_ticketing',
            'opened',
            'created',
            'pending_ticketing',
            'confirmed',
            'pending',
        ];
        if ($supplierStatus !== '' && ! in_array($supplierStatus, $activeStatuses, true)) {
            return $inactiveMessage;
        }

        $paymentRequiredBy = $booking->payment_required_by;
        if ($paymentRequiredBy !== null && $paymentRequiredBy->isPast()) {
            return $inactiveMessage;
        }

        $pnrExpiresAt = $booking->pnr_expires_at;
        if ($pnrExpiresAt !== null && $pnrExpiresAt->isPast()) {
            return $inactiveMessage;
        }

        return null;
    }

    public function canStartAbhiPayForBooking(Booking $booking): bool
    {
        if ($blocked = $this->piaNdcOnlinePaymentBlockedReason($booking)) {
            return false;
        }

        $amount = $this->payableAmountForBooking($booking);
        if ($amount > 0) {
            return true;
        }

        return $amount <= 0 && BookingPayableResolver::allowsZeroPayableCheckout($booking);
    }

    public function abhiPayStartBlockedMessage(Booking $booking): ?string
    {
        return $this->piaNdcOnlinePaymentBlockedReason($booking);
    }

    private function isPiaNdcBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::PiaNdc->value;
    }

    public function payableAmountForBooking(Booking $booking): float
    {
        $booking->loadMissing('fareBreakdown');
        $total = BookingPayableResolver::customerPayableTotal($booking);
        $verifiedTotal = (float) $booking->payments()
            ->where('status', BookingPaymentStatus::Verified)
            ->sum('amount');

        return max(0, round($total - $verifiedTotal, 2));
    }

    public function findReusablePendingTransaction(Booking $booking): ?PaymentTransaction
    {
        return PaymentTransaction::query()
            ->where('booking_id', $booking->id)
            ->where('gateway', PaymentGateway::CODE_ABHIPAY)
            ->whereIn('status', [
                PaymentTransactionStatus::Initiated->value,
                PaymentTransactionStatus::Pending->value,
                PaymentTransactionStatus::Created->value,
            ])
            ->whereNotNull('gateway_payment_url')
            ->latest('id')
            ->first();
    }

    public function createAbhiPayTransaction(Booking $booking, ?User $user): PaymentTransaction
    {
        $gateway = $this->activeAbhiPayGateway($booking->agency_id);
        if ($gateway === null) {
            throw new InvalidArgumentException('AbhiPay is not configured or active.');
        }

        if ($this->isPiaNdcBooking($booking)) {
            try {
                $refresh = $this->piaNdcStatusRefreshService->refreshIfRequiredForPayment($booking);
                $booking = $refresh['booking'];
            } catch (PiaNdcValidationException) {
                throw new InvalidArgumentException('Airline reservation status must be refreshed before online payment.');
            }
        }

        if ($blocked = $this->piaNdcOnlinePaymentBlockedReason($booking)) {
            throw new InvalidArgumentException($blocked);
        }

        $amount = $this->payableAmountForBooking($booking);
        if ($amount <= 0 && ! BookingPayableResolver::allowsZeroPayableCheckout($booking)) {
            throw new InvalidArgumentException('No payable balance remains on this booking.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('No card payment required for this booking.');
        }

        $existing = $this->findReusablePendingTransaction($booking);
        if ($existing !== null && (float) $existing->amount === $amount) {
            return $existing;
        }

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $user?->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => $gateway->environment,
            'amount' => $amount,
            'currency' => (string) ($booking->currency ?? 'PKR'),
            'client_transaction_id' => $this->buildClientTransactionId($booking),
            'status' => PaymentTransactionStatus::Initiated,
            'gateway_message' => filled($booking->promo_code)
                ? json_encode([
                    'promo_code' => $booking->promo_code,
                    'promo_discount_amount' => (float) $booking->promo_discount_amount,
                ], JSON_THROW_ON_ERROR)
                : null,
        ]);
        $transaction->setRelation('booking', $booking);

        $gatewayDriver = $this->gatewayResolver->resolve(PaymentGateway::CODE_ABHIPAY);
        $result = $gatewayDriver->createPayment($transaction);

        $transaction->forceFill([
            'request_payload_json' => PaymentGatewayPayloadRedactor::redact($result->safeResponse['request'] ?? null),
            'response_payload_json' => PaymentGatewayPayloadRedactor::redact($result->safeResponse['response'] ?? null),
            'gateway_order_id' => $result->gatewayOrderId,
            'gateway_payment_url' => $result->redirectUrl,
            'gateway_code' => $result->gatewayCode,
            'gateway_message' => $result->gatewayMessage,
            'status' => $result->success
                ? PaymentTransactionStatus::Created
                : PaymentTransactionStatus::Failed,
            'failed_at' => $result->success ? null : now(),
        ])->save();

        if (! $result->success || ! filled($result->redirectUrl)) {
            throw new InvalidArgumentException($result->errorMessage ?? 'Unable to start AbhiPay payment.');
        }

        return $transaction->fresh();
    }

    public function processCallback(Request $request): PaymentTransaction
    {
        $gatewayDriver = $this->gatewayResolver->resolve(PaymentGateway::CODE_ABHIPAY);
        $callback = $gatewayDriver->handleCallback($request);

        $transaction = $callback->transaction;
        if ($transaction === null) {
            throw new InvalidArgumentException('Payment transaction not found for callback.');
        }

        if ($transaction->isPaid()) {
            return $transaction;
        }

        $transaction->forceFill([
            'callback_payload_json' => PaymentGatewayPayloadRedactor::redact($callback->safeCallbackPayload),
        ])->save();

        $verify = $gatewayDriver->verifyPayment($transaction);

        return $this->applyVerificationResult($transaction, $verify);
    }

    public function verifyTransaction(PaymentTransaction $transaction): PaymentTransaction
    {
        if ($transaction->isPaid()) {
            return $transaction;
        }

        $gatewayDriver = $this->gatewayResolver->resolve((string) $transaction->gateway);
        $verify = $gatewayDriver->verifyPayment($transaction);

        return $this->applyVerificationResult($transaction, $verify);
    }

    protected function applyVerificationResult(PaymentTransaction $transaction, PaymentGatewayVerifyResult $verify): PaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $verify): PaymentTransaction {
            $transaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->isPaid()) {
                return $transaction;
            }

            $transaction->forceFill([
                'gateway_order_id' => $verify->gatewayOrderId ?? $transaction->gateway_order_id,
                'gateway_status' => $verify->gatewayStatus,
                'gateway_code' => $verify->gatewayCode,
                'gateway_message' => $verify->gatewayMessage,
                'response_payload_json' => PaymentGatewayPayloadRedactor::redact($verify->safeResponse),
                'verified_at' => now(),
                'status' => $verify->status,
                'paid_at' => $verify->status === PaymentTransactionStatus::Paid ? now() : null,
                'failed_at' => $verify->status->isTerminal() && $verify->status !== PaymentTransactionStatus::Paid
                    ? now()
                    : $transaction->failed_at,
            ])->save();

            if ($verify->status === PaymentTransactionStatus::Paid && $transaction->booking_id !== null) {
                $this->settleVerifiedBookingPayment($transaction, $verify);
            }

            return $transaction->fresh(['booking']);
        });
    }

    protected function settleVerifiedBookingPayment(PaymentTransaction $transaction, PaymentGatewayVerifyResult $verify): void
    {
        $booking = $transaction->booking;
        if ($booking === null) {
            return;
        }

        $existingPayment = BookingPayment::query()
            ->where('booking_id', $booking->id)
            ->where('method', BookingPaymentMethod::AbhiPay)
            ->where('payment_reference', $transaction->client_transaction_id)
            ->where('status', BookingPaymentStatus::Verified)
            ->exists();

        if ($existingPayment) {
            return;
        }

        $systemUser = User::query()
            ->where('account_type', AccountType::PlatformAdmin)
            ->orderBy('id')
            ->first();

        $this->bookingPaymentService->recordVerifiedGatewayPayment($booking, [
            'method' => BookingPaymentMethod::AbhiPay,
            'amount' => (float) $transaction->amount,
            'currency' => (string) $transaction->currency,
            'payment_reference' => $transaction->client_transaction_id,
            'meta' => array_filter([
                'gateway' => PaymentGateway::CODE_ABHIPAY,
                'gateway_order_id' => $transaction->gateway_order_id,
                'payment_transaction_uuid' => $transaction->uuid,
                'gateway_status' => $verify->gatewayStatus,
                'masked_card' => $verify->maskedCard,
                'promo_code' => $booking->promo_code,
                'promo_discount_amount' => filled($booking->promo_code) ? (float) $booking->promo_discount_amount : null,
                'payable_before_promo' => $booking->payable_before_promo,
                'payable_after_promo' => $booking->payable_after_promo,
            ]),
        ], $systemUser);

        try {
            $this->promoCodeService->redeemForBooking($booking->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $transaction->user_id,
            'action' => 'booking.payment_abhipay_verified',
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => [],
                'new_values' => [
                    'payment_transaction_id' => $transaction->id,
                    'client_transaction_id' => $transaction->client_transaction_id,
                    'gateway_order_id' => $transaction->gateway_order_id,
                    'amount' => (float) $transaction->amount,
                    'currency' => (string) $transaction->currency,
                ],
            ],
        ]);

        try {
            $booking->bookingNotes()->create([
                'agency_id' => $booking->agency_id,
                'user_id' => $transaction->user_id,
                'note' => 'AbhiPay payment verified.',
                'note_type' => 'payment',
                'is_customer_visible' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('abhipay_booking_note_failed', [
                'booking_id' => $booking->id,
                'payment_transaction_id' => $transaction->id,
            ]);
        }
    }

    protected function buildClientTransactionId(Booking $booking): string
    {
        $uuid = (string) Str::uuid();
        $reference = (string) ($booking->booking_reference ?? $booking->id);

        return 'OTA-'.$reference.'-'.$uuid;
    }
}
