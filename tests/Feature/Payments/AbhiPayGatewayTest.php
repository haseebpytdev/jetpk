<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPayment;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Gateways\AbhiPayGateway;
use App\Services\Payments\PaymentTransactionService;
use App\Support\Bookings\BookingPaymentSummaryPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbhiPayGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    protected function configureGateway(Agency $agency): PaymentGateway
    {
        return PaymentGateway::query()->create([
            'agency_id' => $agency->id,
            'code' => PaymentGateway::CODE_ABHIPAY,
            'name' => 'AbhiPay',
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-123',
            'merchant_secret_key' => 'secret-key-test-value',
            'base_url' => 'https://api.abhipay.com.pk/api/v3',
            'callback_url' => route('payments.abhipay.callback'),
        ]);
    }

    protected function createPayableBooking(Agency $agency, User $customer, float $total = 50000): Booking
    {
        $booking = Booking::factory()->for($agency)->create([
            'customer_id' => $customer->id,
            'booking_reference' => 'ABHIPAY01',
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'currency' => 'PKR',
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => $total,
            'taxes' => 0,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => $total,
            'currency' => 'PKR',
        ]);

        return $booking->fresh(['fareBreakdown']);
    }

    public function test_admin_can_save_abhipay_settings_with_encrypted_secret(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.payments.abhipay.update'), [
                'is_active' => true,
                'environment' => 'test',
                'merchant_id' => 'MID-999',
                'merchant_secret_key' => 'super-secret-key',
                'base_url' => PaymentGateway::DEFAULT_BASE_URL,
            ])
            ->assertRedirect();

        $gateway = PaymentGateway::query()->where('agency_id', $agency->id)->firstOrFail();
        $this->assertTrue($gateway->is_active);
        $this->assertSame('MID-999', $gateway->merchant_id);
        $this->assertSame('super-secret-key', $gateway->merchant_secret_key);
        $this->assertStringNotContainsString('super-secret-key', $this->get(route('admin.settings.payments.index'))->getContent());
        $this->assertTrue(AuditLog::query()->where('action', 'payment_gateway.abhipay.updated')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'payment_gateway.abhipay.secret_replaced')->exists());
    }

    public function test_start_uses_booking_amount_not_request_amount(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 45000);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders' => Http::response([
                'resultCode' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'payload' => [
                    'orderId' => 'ORD-1',
                    'paymentUrl' => 'https://pay.abhipay.com.pk/checkout/ORD-1',
                ],
            ], 200),
        ]);

        $this->actingAs($customer)
            ->post(route('payments.abhipay.start', $booking), ['amount' => 1])
            ->assertRedirect('https://pay.abhipay.com.pk/checkout/ORD-1');

        $transaction = PaymentTransaction::query()->firstOrFail();
        $this->assertSame('45000.00', $transaction->amount);
        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['amount'] ?? null) === 45000;
        });
    }

    public function test_callback_does_not_mark_paid_without_verification(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 1000);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 1000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-ABHIPAY01-test',
            'gateway_order_id' => 'ORD-PENDING',
            'status' => PaymentTransactionStatus::Created,
        ]);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/ORD-PENDING' => Http::response([
                'resultCode' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'payload' => [
                    'orderId' => 'ORD-PENDING',
                    'paymentStatus' => 'pending',
                    'amount' => 1000,
                    'currency' => 'PKR',
                    'clientTransactionId' => 'OTA-ABHIPAY01-test',
                ],
            ], 200),
        ]);

        $this->post(route('payments.abhipay.callback'), [
            'orderId' => 'ORD-PENDING',
            'clientTransactionId' => 'OTA-ABHIPAY01-test',
        ])->assertRedirect();

        $transaction->refresh();
        $booking->refresh();
        $this->assertNotSame(PaymentTransactionStatus::Paid, $transaction->status);
        $this->assertSame('unpaid', $booking->payment_status);
    }

    public function test_verify_paid_marks_transaction_and_booking_paid(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 2500);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 2500,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-PAID-1',
            'gateway_order_id' => 'ORD-PAID',
            'status' => PaymentTransactionStatus::Created,
        ]);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/ORD-PAID' => Http::response([
                'resultCode' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'payload' => [
                    'orderId' => 'ORD-PAID',
                    'paymentStatus' => 'paid',
                    'amount' => 2500,
                    'currency' => 'PKR',
                    'clientTransactionId' => 'OTA-PAID-1',
                ],
            ], 200),
        ]);

        app(PaymentTransactionService::class)->verifyTransaction($transaction);

        $transaction->refresh();
        $booking->refresh();
        $this->assertSame(PaymentTransactionStatus::Paid, $transaction->status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertTrue(BookingPayment::query()
            ->where('booking_id', $booking->id)
            ->where('method', BookingPaymentMethod::AbhiPay)
            ->where('status', BookingPaymentStatus::Verified)
            ->exists());
    }

    public function test_callback_is_idempotent_for_paid_transaction(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 1200);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 1200,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-IDEM-1',
            'gateway_order_id' => 'ORD-IDEM',
            'status' => PaymentTransactionStatus::Paid,
            'paid_at' => now(),
            'verified_at' => now(),
        ]);

        BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'payment_reference' => 'OTA-IDEM-1',
            'method' => BookingPaymentMethod::AbhiPay,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 1200,
            'currency' => 'PKR',
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);

        Http::fake();

        $this->post(route('payments.abhipay.callback'), [
            'orderId' => 'ORD-IDEM',
            'clientTransactionId' => 'OTA-IDEM-1',
        ])->assertRedirect();

        $this->assertSame(1, BookingPayment::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }

    public function test_amount_mismatch_does_not_mark_paid(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->createPayableBooking($agency, User::query()->where('email', 'customer@ota.demo')->firstOrFail(), 3000);
        $this->configureGateway($agency);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 3000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-MISMATCH',
            'gateway_order_id' => 'ORD-MISMATCH',
            'status' => PaymentTransactionStatus::Created,
        ]);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/ORD-MISMATCH' => Http::response([
                'resultCode' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'payload' => [
                    'orderId' => 'ORD-MISMATCH',
                    'paymentStatus' => 'paid',
                    'amount' => 100,
                    'currency' => 'PKR',
                    'clientTransactionId' => 'OTA-MISMATCH',
                ],
            ], 200),
        ]);

        app(PaymentTransactionService::class)->verifyTransaction($transaction);
        $transaction->refresh();
        $this->assertSame(PaymentTransactionStatus::VerificationFailed, $transaction->status);
    }

    public function test_v3_create_order_sends_major_units_for_wave9_fixture(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        // Selected branded fare / review / payable fixture: PKR 79,089
        $booking = $this->createPayableBooking($agency, $customer, 79089);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders' => Http::response([
                'code' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'message' => 'Operation performed successfully',
                'payload' => [
                    'orderId' => 'ORD-79089',
                    'paymentUrl' => 'https://pay.abhipay.com.pk/checkout/ORD-79089',
                ],
            ], 200),
        ]);

        $this->actingAs($customer)
            ->post(route('payments.abhipay.start', $booking))
            ->assertRedirect('https://pay.abhipay.com.pk/checkout/ORD-79089');

        $transaction = PaymentTransaction::query()->firstOrFail();
        $this->assertSame('79089.00', $transaction->amount);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $amount = $body['amount'] ?? null;

            // ABHIPAY_V3_AMOUNT_MAJOR_UNITS + ABHIPAY_AMOUNT_PARITY
            return $amount === 79089
                && $amount !== 7908900
                && ($body['currency'] ?? null) === 'PKR'
                && ($body['language'] ?? null) === 'EN'
                && ($body['operation'] ?? null) === 'PURCHASE'
                && ($body['cardSave'] ?? null) === false
                && filled($body['clientTransactionId'] ?? null)
                && filled($body['callbackUrl'] ?? null)
                && filled($body['description'] ?? null)
                && str_contains($request->url(), '/api/v3/orders')
                && $request->hasHeader('Authorization');
        });
    }

    public function test_v3_rejects_100x_remote_amount_without_silent_normalization(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 79089);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 79089.00,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-100X-REJECT',
            'gateway_order_id' => 'ORD-100X',
            'status' => PaymentTransactionStatus::Created,
        ]);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/ORD-100X' => Http::response([
                'code' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'message' => 'Operation performed successfully',
                'payload' => [
                    'orderId' => 'ORD-100X',
                    'paymentStatus' => 'paid',
                    'amount' => 7908900,
                    'currencyType' => 'PKR',
                    'clientTransactionId' => 'OTA-100X-REJECT',
                ],
            ], 200),
        ]);

        app(PaymentTransactionService::class)->verifyTransaction($transaction);
        $transaction->refresh();

        // ABHIPAY_100X_AMOUNT_REJECTED — must not silently divide by 100
        $this->assertSame(PaymentTransactionStatus::VerificationFailed, $transaction->status);
        $this->assertNotSame(PaymentTransactionStatus::Paid, $transaction->status);
    }

    public function test_v3_amount_match_passes_for_matching_major_units(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 79089);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 79089.00,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-MATCH-79089',
            'gateway_order_id' => 'ORD-MATCH-79089',
            'status' => PaymentTransactionStatus::Created,
        ]);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/ORD-MATCH-79089' => Http::response([
                'code' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'message' => 'Operation performed successfully',
                'payload' => [
                    'orderId' => 'ORD-MATCH-79089',
                    'paymentStatus' => 'paid',
                    'amount' => 79089.00,
                    'currencyType' => 'PKR',
                    'clientTransactionId' => 'OTA-MATCH-79089',
                ],
            ], 200),
        ]);

        app(PaymentTransactionService::class)->verifyTransaction($transaction);
        $transaction->refresh();
        $this->assertSame(PaymentTransactionStatus::Paid, $transaction->status);
    }

    public function test_v3_currency_type_mismatch_fails_verification(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 79089);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 79089.00,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-CURR-MISMATCH',
            'gateway_order_id' => 'ORD-CURR',
            'status' => PaymentTransactionStatus::Created,
        ]);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/ORD-CURR' => Http::response([
                'code' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'message' => 'Operation performed successfully',
                'payload' => [
                    'orderId' => 'ORD-CURR',
                    'paymentStatus' => 'paid',
                    'amount' => 79089.00,
                    'currencyType' => 'USD',
                    'clientTransactionId' => 'OTA-CURR-MISMATCH',
                ],
            ], 200),
        ]);

        app(PaymentTransactionService::class)->verifyTransaction($transaction);
        $transaction->refresh();

        // ABHIPAY_CURRENCYTYPE_VERIFIED
        $this->assertSame(PaymentTransactionStatus::VerificationFailed, $transaction->status);
        $this->assertNotSame(PaymentTransactionStatus::Paid, $transaction->status);
    }

    public function test_v3_verify_by_rrn_uses_client_transaction_id(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 1.00);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 1.00,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-BY-RRN-1',
            'gateway_order_id' => null,
            'status' => PaymentTransactionStatus::Created,
        ]);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/by-rrn/OTA-BY-RRN-1' => Http::response([
                'code' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'message' => 'Operation performed successfully',
                'payload' => [
                    'orderId' => 'ORD-BY-RRN',
                    'paymentStatus' => 'paid',
                    'amount' => 1.00,
                    'currencyType' => 'PKR',
                    'clientTransactionId' => 'OTA-BY-RRN-1',
                ],
            ], 200),
        ]);

        app(PaymentTransactionService::class)->verifyTransaction($transaction);
        $transaction->refresh();

        // ABHIPAY_BY_RRN_VERIFY + ABHIPAY_CLIENT_TRANSACTION_ID
        $this->assertSame(PaymentTransactionStatus::Paid, $transaction->status);
        $this->assertSame('ORD-BY-RRN', $transaction->gateway_order_id);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/orders/by-rrn/OTA-BY-RRN-1'));
    }

    public function test_create_payment_is_idempotent_for_existing_redirectable_transaction(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 79089);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders' => Http::response([
                'code' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'message' => 'Operation performed successfully',
                'payload' => [
                    'orderId' => 'ORD-IDEM-CREATE',
                    'paymentUrl' => 'https://pay.abhipay.com.pk/checkout/ORD-IDEM-CREATE',
                ],
            ], 200),
        ]);

        $this->actingAs($customer)
            ->post(route('payments.abhipay.start', $booking))
            ->assertRedirect('https://pay.abhipay.com.pk/checkout/ORD-IDEM-CREATE');

        $this->actingAs($customer)
            ->post(route('payments.abhipay.start', $booking))
            ->assertRedirect('https://pay.abhipay.com.pk/checkout/ORD-IDEM-CREATE');

        // ABHIPAY_CREATE_IDEMPOTENT — one PaymentTransaction
        $this->assertSame(1, PaymentTransaction::query()->where('booking_id', $booking->id)->count());
    }

    public function test_abhipay_secret_never_exposed_in_start_response_or_payload(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 1.00);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders' => Http::response([
                'code' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'payload' => [
                    'orderId' => 'ORD-SECRET',
                    'paymentUrl' => 'https://pay.abhipay.com.pk/checkout/ORD-SECRET',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($customer)
            ->post(route('payments.abhipay.start', $booking));

        $response->assertRedirect('https://pay.abhipay.com.pk/checkout/ORD-SECRET');
        $this->assertStringNotContainsString('secret-key-test-value', $response->headers->get('Location') ?? '');
        $this->assertStringNotContainsString('secret-key-test-value', $response->getContent());

        Http::assertSent(function ($request) {
            $serialized = json_encode($request->data());

            // Authorization header is server-side only; body must not include the secret.
            return $request->hasHeader('Authorization')
                && ! str_contains((string) $serialized, 'secret-key-test-value')
                && ($request->data()['amount'] ?? null) === 1;
        });
    }

    public function test_missing_configuration_hides_abhipay_from_checkout_card(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = $this->createPayableBooking($agency, $customer, 1000);

        $html = view('components.bookings.detail-payment-card', [
            'booking' => $booking,
            'summary' => BookingPaymentSummaryPresenter::forBooking($booking, true, 'customer'),
            'audience' => 'customer',
        ])->render();

        $this->assertStringNotContainsString('abhipay-checkout-option', $html);
    }
}
