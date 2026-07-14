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

            return ($body['amount'] ?? null) === 4500000;
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
                    'amount' => 100000,
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
                    'amount' => 250000,
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
