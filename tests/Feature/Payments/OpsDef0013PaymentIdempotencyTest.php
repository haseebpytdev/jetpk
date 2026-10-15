<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PaymentTransactionStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPayment;
use App\Models\LedgerTransaction;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\DTO\PaymentGatewayVerifyResult;
use App\Services\Payments\PaymentTransactionService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * OPS-DEF-0013: gateway settlement DB idempotency + provider-order uniqueness.
 */
class OpsDef0013PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    protected function configureGateway(Agency $agency, string $environment = 'test'): PaymentGateway
    {
        return PaymentGateway::query()->create([
            'agency_id' => $agency->id,
            'code' => PaymentGateway::CODE_ABHIPAY,
            'name' => 'AbhiPay',
            'environment' => $environment,
            'is_active' => true,
            'merchant_id' => 'MERCHANT-123',
            'merchant_secret_key' => 'secret-key-test-value',
            'base_url' => 'https://api.abhipay.com.pk/api/v3',
            'callback_url' => route('payments.abhipay.callback'),
        ]);
    }

    protected function createPayableBooking(Agency $agency, User $customer, float $total = 5000): Booking
    {
        $booking = Booking::factory()->for($agency)->create([
            'customer_id' => $customer->id,
            'booking_reference' => 'OPS13'.substr(uniqid(), -6),
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'currency' => 'PKR',
            'amount_paid' => 0,
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

    protected function createGatewayTransaction(Booking $booking, User $customer, array $overrides = []): PaymentTransaction
    {
        return PaymentTransaction::query()->create(array_merge([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 5000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-OPS13-'.uniqid(),
            'gateway_order_id' => 'ORD-OPS13-'.uniqid(),
            'status' => PaymentTransactionStatus::Created,
        ], $overrides));
    }

    protected function fakePaidVerify(string $orderId, string $clientTransactionId, int $minorAmount = 500000): void
    {
        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/'.$orderId => Http::response([
                'resultCode' => '00000',
                'payload' => [
                    'orderId' => $orderId,
                    'paymentStatus' => 'paid',
                    'amount' => $minorAmount,
                    'currency' => 'PKR',
                    'clientTransactionId' => $clientTransactionId,
                ],
            ], 200),
        ]);
    }

    public function test_schema_has_payment_transaction_link_and_provider_order_unique(): void
    {
        $this->assertTrue(Schema::hasColumn('booking_payments', 'payment_transaction_id'));

        $indexes = collect(Schema::getConnection()->select('PRAGMA index_list(booking_payments)'))
            ->pluck('name')
            ->all();
        $this->assertContains('booking_payments_payment_transaction_id_unique', $indexes);

        $ptIndexes = collect(Schema::getConnection()->select('PRAGMA index_list(payment_transactions)'))
            ->pluck('name')
            ->all();
        $this->assertContains('payment_transactions_gateway_env_order_unique', $ptIndexes);
    }

    public function test_second_booking_payment_for_same_transaction_blocked_by_db_unique(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = $this->createPayableBooking($agency, $customer, 1000);
        $tx = $this->createGatewayTransaction($booking, $customer, [
            'amount' => 1000,
            'client_transaction_id' => 'OTA-UNIQUE-BP-1',
            'gateway_order_id' => 'ORD-UNIQUE-BP-1',
        ]);

        BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'payment_transaction_id' => $tx->id,
            'payment_reference' => 'OTA-UNIQUE-BP-1',
            'method' => BookingPaymentMethod::AbhiPay,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'payment_transaction_id' => $tx->id,
            'payment_reference' => 'OTA-UNIQUE-BP-1-DUP',
            'method' => BookingPaymentMethod::AbhiPay,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);
    }

    public function test_provider_order_uniqueness_is_environment_scoped(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = $this->createPayableBooking($agency, $customer, 1000);

        PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'production',
            'amount' => 1000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-ORDER-PROD-1',
            'gateway_order_id' => 'ORDER123',
            'status' => PaymentTransactionStatus::Created,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'production',
            'amount' => 1000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-ORDER-PROD-2',
            'gateway_order_id' => 'ORDER123',
            'status' => PaymentTransactionStatus::Created,
        ]);
    }

    public function test_provider_order_allows_same_id_in_different_environment_and_nulls(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = $this->createPayableBooking($agency, $customer, 1000);

        PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'production',
            'amount' => 1000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-ENV-A',
            'gateway_order_id' => 'ORDER123',
            'status' => PaymentTransactionStatus::Created,
        ]);

        $testEnv = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 1000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-ENV-B',
            'gateway_order_id' => 'ORDER123',
            'status' => PaymentTransactionStatus::Created,
        ]);

        $nullA = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 1000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-NULL-A',
            'gateway_order_id' => null,
            'status' => PaymentTransactionStatus::Initiated,
        ]);

        $nullB = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'amount' => 1000,
            'currency' => 'PKR',
            'client_transaction_id' => 'OTA-NULL-B',
            'gateway_order_id' => null,
            'status' => PaymentTransactionStatus::Initiated,
        ]);

        $this->assertNotNull($testEnv->id);
        $this->assertNotNull($nullA->id);
        $this->assertNotNull($nullB->id);
    }

    public function test_duplicate_settlement_attempts_create_one_booking_payment_and_side_effects(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 5000);

        $notifyCount = 0;
        $this->mock(BookingCommunicationService::class, function ($mock) use (&$notifyCount): void {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('sendPaymentVerified')->andReturnUsing(function () use (&$notifyCount): void {
                $notifyCount++;
            });
        });

        $tx = $this->createGatewayTransaction($booking, $customer, [
            'amount' => 5000,
            'client_transaction_id' => 'OTA-RACE-1',
            'gateway_order_id' => 'ORD-RACE-1',
        ]);

        $this->fakePaidVerify('ORD-RACE-1', 'OTA-RACE-1', 500000);

        $service = app(PaymentTransactionService::class);
        $service->verifyTransaction($tx);
        $service->verifyTransaction($tx->fresh());

        $booking->refresh();
        $payments = BookingPayment::query()->where('booking_id', $booking->id)->get();
        $this->assertCount(1, $payments);
        $this->assertSame($tx->id, (int) $payments->first()->payment_transaction_id);
        $this->assertSame(BookingPaymentStatus::Verified, $payments->first()->status);
        $this->assertSame('5000.00', (string) $booking->amount_paid);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(PaymentTransactionStatus::Paid, $tx->fresh()->status);

        $ledgerCount = LedgerTransaction::query()
            ->where('source_type', (new BookingPayment)->getMorphClass())
            ->where('source_id', $payments->first()->id)
            ->where('transaction_type', LedgerTransactionType::BookingPaymentVerified)
            ->count();
        $this->assertSame(1, $ledgerCount);

        $auditCount = AuditLog::query()
            ->where('action', 'booking.payment_abhipay_verified')
            ->where('auditable_id', $booking->id)
            ->count();
        $this->assertSame(1, $auditCount);

        $this->assertSame(1, $notifyCount);
    }

    public function test_paid_state_not_downgraded_by_stale_pending_callback(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 2000);

        $tx = $this->createGatewayTransaction($booking, $customer, [
            'amount' => 2000,
            'client_transaction_id' => 'OTA-PAID-STALE',
            'gateway_order_id' => 'ORD-PAID-STALE',
            'status' => PaymentTransactionStatus::Paid,
            'paid_at' => now(),
            'verified_at' => now(),
        ]);

        BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'payment_transaction_id' => $tx->id,
            'payment_reference' => 'OTA-PAID-STALE',
            'method' => BookingPaymentMethod::AbhiPay,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 2000,
            'currency' => 'PKR',
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);
        $booking->forceFill(['amount_paid' => 2000, 'payment_status' => 'paid'])->save();

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders/ORD-PAID-STALE' => Http::response([
                'resultCode' => '00000',
                'payload' => [
                    'orderId' => 'ORD-PAID-STALE',
                    'paymentStatus' => 'pending',
                    'amount' => 200000,
                    'currency' => 'PKR',
                    'clientTransactionId' => 'OTA-PAID-STALE',
                ],
            ], 200),
        ]);

        $this->post(route('payments.abhipay.callback'), [
            'orderId' => 'ORD-PAID-STALE',
            'clientTransactionId' => 'OTA-PAID-STALE',
        ])->assertRedirect();

        $tx->refresh();
        $this->assertSame(PaymentTransactionStatus::Paid, $tx->status);
        $this->assertSame(1, BookingPayment::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }

    public function test_failed_transaction_does_not_settle_and_second_valid_tx_can_settle(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 3000);

        $failed = $this->createGatewayTransaction($booking, $customer, [
            'amount' => 3000,
            'client_transaction_id' => 'OTA-FAIL-1',
            'gateway_order_id' => 'ORD-FAIL-1',
            'status' => PaymentTransactionStatus::Failed,
            'failed_at' => now(),
        ]);

        $valid = $this->createGatewayTransaction($booking, $customer, [
            'amount' => 3000,
            'client_transaction_id' => 'OTA-OK-2',
            'gateway_order_id' => 'ORD-OK-2',
        ]);

        $this->fakePaidVerify('ORD-OK-2', 'OTA-OK-2', 300000);
        app(PaymentTransactionService::class)->verifyTransaction($valid);

        $this->assertSame(0, BookingPayment::query()
            ->where('payment_transaction_id', $failed->id)
            ->count());
        $this->assertSame(1, BookingPayment::query()
            ->where('payment_transaction_id', $valid->id)
            ->where('status', BookingPaymentStatus::Verified)
            ->count());
        $this->assertSame(PaymentTransactionStatus::Paid, $valid->fresh()->status);
    }

    public function test_manual_payment_keeps_null_payment_transaction_id(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = $this->createPayableBooking($agency, $customer, 1500);

        $payment = app(BookingPaymentService::class)->recordManualPayment($booking, $admin, [
            'method' => BookingPaymentMethod::Cash,
            'amount' => 1000,
            'currency' => 'PKR',
            'payment_reference' => 'CASH-OPS13',
            'verify_now' => true,
        ]);

        $this->assertNull($payment->payment_transaction_id);
        $this->assertSame(BookingPaymentStatus::Verified, $payment->status);

        // Multiple manual NULLs remain allowed (no global payment_reference unique).
        $second = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'payment_transaction_id' => null,
            'payment_reference' => 'BANK-OPS13',
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingPaymentStatus::Pending,
            'amount' => 1,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);
        $this->assertNull($second->payment_transaction_id);
        $this->assertSame(2, BookingPayment::query()
            ->where('booking_id', $booking->id)
            ->whereNull('payment_transaction_id')
            ->count());
    }

    public function test_record_verified_gateway_payment_requires_payment_transaction_id(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = $this->createPayableBooking($agency, $customer, 1000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('payment_transaction_id is required');

        app(BookingPaymentService::class)->recordVerifiedGatewayPayment($booking, [
            'method' => BookingPaymentMethod::AbhiPay,
            'amount' => 1000,
            'currency' => 'PKR',
            'payment_reference' => 'OTA-MISSING-TX',
        ]);
    }

    public function test_ambiguous_historical_backfill_is_refused_by_migration_logic(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = $this->createPayableBooking($agency, $customer, 1000);

        // Two PTs with same client id cannot exist (unique), so simulate ambiguous
        // booking_id mismatch path via reflection of migration helper semantics:
        // create BP with reference matching a PT for a different booking.
        $otherBooking = $this->createPayableBooking($agency, $customer, 1000);
        $tx = $this->createGatewayTransaction($otherBooking, $customer, [
            'client_transaction_id' => 'OTA-AMBIG-REF',
            'gateway_order_id' => 'ORD-AMBIG-REF',
        ]);

        BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'payment_reference' => 'OTA-AMBIG-REF',
            'method' => BookingPaymentMethod::AbhiPay,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_15_180000_add_payment_idempotency_constraints.php');
        $method = new ReflectionMethod($migration, 'backfillGatewayBookingPayments');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('booking_id mismatch');
        $method->invoke($migration);

        $this->assertNotNull($tx->id);
    }

    public function test_apply_verification_result_idempotent_via_reflection_double_call(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->configureGateway($agency);
        $booking = $this->createPayableBooking($agency, $customer, 4000);

        $tx = $this->createGatewayTransaction($booking, $customer, [
            'amount' => 4000,
            'client_transaction_id' => 'OTA-DBL-APPLY',
            'gateway_order_id' => 'ORD-DBL-APPLY',
        ]);

        $verify = new PaymentGatewayVerifyResult(
            verified: true,
            status: PaymentTransactionStatus::Paid,
            gatewayOrderId: 'ORD-DBL-APPLY',
            gatewayStatus: 'paid',
            gatewayCode: '00000',
            gatewayMessage: 'ok',
            safeResponse: ['paymentStatus' => 'paid'],
        );

        $service = app(PaymentTransactionService::class);
        $method = new ReflectionMethod($service, 'applyVerificationResult');
        $method->setAccessible(true);

        $method->invoke($service, $tx, $verify);
        $method->invoke($service, $tx->fresh(), $verify);

        $this->assertSame(1, BookingPayment::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('4000.00', (string) $booking->fresh()->amount_paid);
        $this->assertSame(1, AuditLog::query()
            ->where('action', 'booking.payment_abhipay_verified')
            ->where('auditable_id', $booking->id)
            ->count());
    }
}
