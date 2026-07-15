<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use App\Enums\PromoRedemptionStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\PaymentGateway;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;
use App\Services\Payments\Gateways\AbhiPayGateway;
use App\Services\Payments\PaymentTransactionService;
use App\Services\Promos\PromoCodeCalculator;
use App\Services\Promos\PromoCodeService;
use App\Support\Payments\BookingPayableResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PromoCodeCheckoutTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected Agency $agency;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->admin = $this->platformAdmin();
        $this->agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
    }

    protected function createFlightBooking(float $total = 10000): Booking
    {
        $booking = Booking::factory()->for($this->agency)->create([
            'customer_id' => null,
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

    public function test_admin_can_create_percent_promo_99off(): void
    {
        $this->actingAs($this->admin)->post(route('admin.promo-codes.store'), [
            'code' => '99OFF',
            'type' => PromoCodeType::Percent->value,
            'value' => 99,
            'applies_to' => 'flights',
            'status' => PromoCodeStatus::Active->value,
        ])->assertRedirect(route('admin.promo-codes.index'));

        $promo = PromoCode::query()->where('code', '99OFF')->first();
        $this->assertNotNull($promo);
        $this->assertEquals(99, (float) $promo->value);
    }

    public function test_value_zero_is_rejected_on_admin_create(): void
    {
        $this->actingAs($this->admin)->post(route('admin.promo-codes.store'), [
            'code' => 'BAD0',
            'type' => PromoCodeType::Percent->value,
            'value' => 0,
            'applies_to' => 'flights',
            'status' => PromoCodeStatus::Active->value,
        ])->assertSessionHasErrors('value');
    }

    public function test_percent_above_99_rejected_without_internal_testing(): void
    {
        $this->actingAs($this->admin)->post(route('admin.promo-codes.store'), [
            'code' => 'FULL100',
            'type' => PromoCodeType::Percent->value,
            'value' => 100,
            'applies_to' => 'flights',
            'status' => PromoCodeStatus::Active->value,
            'internal_testing_only' => 0,
        ])->assertSessionHasErrors('value');
    }

    public function test_99_percent_off_calculates_final_payable_correctly(): void
    {
        $promo = PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => '99OFF',
            'type' => PromoCodeType::Percent,
            'value' => 99,
        ]);

        $calc = app(PromoCodeCalculator::class)->calculateDiscount($promo, 10000);
        $this->assertEquals(10000, $calc['original_payable']);
        $this->assertEquals(9900, $calc['discount_amount']);
        $this->assertEquals(100, $calc['final_payable']);
    }

    public function test_final_payable_never_negative(): void
    {
        $promo = PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'type' => PromoCodeType::Fixed,
            'value' => 50000,
            'currency' => 'PKR',
        ]);

        $calc = app(PromoCodeCalculator::class)->calculateDiscount($promo, 10000);
        $this->assertGreaterThanOrEqual(1, $calc['final_payable']);
    }

    public function test_inactive_code_cannot_apply(): void
    {
        PromoCode::factory()->inactive()->create([
            'agency_id' => $this->agency->id,
            'code' => 'OFF1',
        ]);
        $booking = $this->createFlightBooking();
        $result = app(PromoCodeService::class)->applyToBooking('OFF1', $booking);
        $this->assertFalse($result->success);
    }

    public function test_expired_code_cannot_apply(): void
    {
        PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => 'OLD1',
            'ends_at' => now()->subDay(),
        ]);
        $booking = $this->createFlightBooking();
        $result = app(PromoCodeService::class)->applyToBooking('OLD1', $booking);
        $this->assertFalse($result->success);
    }

    public function test_usage_limit_enforced_on_redeemed_count(): void
    {
        $promo = PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => 'ONCE',
            'usage_limit' => 1,
        ]);
        PromoRedemption::query()->create([
            'promo_code_id' => $promo->id,
            'code' => 'ONCE',
            'original_amount' => 1000,
            'discount_amount' => 100,
            'final_amount' => 900,
            'status' => PromoRedemptionStatus::Redeemed,
            'applied_at' => now(),
            'redeemed_at' => now(),
        ]);

        $booking = $this->createFlightBooking();
        $result = app(PromoCodeService::class)->applyToBooking('ONCE', $booking);
        $this->assertFalse($result->success);
    }

    public function test_min_order_amount_enforced(): void
    {
        PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => 'MIN5K',
            'min_amount' => 5000,
        ]);
        $booking = $this->createFlightBooking(4000);
        $result = app(PromoCodeService::class)->applyToBooking('MIN5K', $booking);
        $this->assertFalse($result->success);
    }

    public function test_promo_applies_to_booking_checkout_total(): void
    {
        PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => 'SAVE10',
            'type' => PromoCodeType::Percent,
            'value' => 10,
        ]);
        $booking = $this->createFlightBooking(10000);
        $result = app(PromoCodeService::class)->applyToBooking('SAVE10', $booking);
        $this->assertTrue($result->success);

        $booking->refresh();
        $this->assertSame('SAVE10', $booking->promo_code);
        $this->assertEquals(1000, (float) $booking->promo_discount_amount);
        $this->assertEquals(9000, (float) $booking->payable_after_promo);
        $this->assertEquals(10000, (float) $booking->fareBreakdown->total);
        $this->assertEquals(9000, BookingPayableResolver::customerPayableTotal($booking));
    }

    public function test_supplier_fare_fields_remain_unchanged_after_promo(): void
    {
        PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => 'HALF',
            'type' => PromoCodeType::Percent,
            'value' => 50,
        ]);
        $booking = $this->createFlightBooking(20000);
        $originalFareTotal = (float) $booking->fareBreakdown->total;
        app(PromoCodeService::class)->applyToBooking('HALF', $booking);
        $booking->refresh();
        $this->assertEquals($originalFareTotal, (float) $booking->fareBreakdown->total);
    }

    public function test_abhipay_payment_transaction_uses_discounted_payable(): void
    {
        PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => '99OFF',
            'type' => PromoCodeType::Percent,
            'value' => 99,
        ]);

        $customer = User::factory()->create([
            'current_agency_id' => $this->agency->id,
            'account_type' => AccountType::Customer,
        ]);
        $booking = $this->createFlightBooking(50000);
        $booking->update(['customer_id' => $customer->id]);
        app(PromoCodeService::class)->applyToBooking('99OFF', $booking, $customer);

        PaymentGateway::query()->create([
            'agency_id' => $this->agency->id,
            'code' => PaymentGateway::CODE_ABHIPAY,
            'name' => 'AbhiPay',
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-123',
            'merchant_secret_key' => 'secret-key-test-value',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
            'callback_url' => route('payments.abhipay.callback'),
        ]);

        Http::fake([
            'api.abhipay.com.pk/api/v3/orders' => Http::response([
                'resultCode' => AbhiPayGateway::SUCCESS_RESULT_CODE,
                'payload' => [
                    'orderId' => 'ORD-PROMO',
                    'paymentUrl' => 'https://pay.abhipay.com.pk/checkout/ORD-PROMO',
                ],
            ], 200),
        ]);

        $service = app(PaymentTransactionService::class);
        $booking = $booking->fresh(['fareBreakdown']);
        $this->assertEquals(500, $service->payableAmountForBooking($booking));
        $transaction = $service->createAbhiPayTransaction($booking, $customer);
        $this->assertEquals(500, (float) $transaction->amount);
    }

    public function test_duplicate_apply_does_not_duplicate_redemption(): void
    {
        PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => 'DUP',
            'value' => 10,
        ]);
        $booking = $this->createFlightBooking();
        $service = app(PromoCodeService::class);
        $service->applyToBooking('DUP', $booking);
        $service->applyToBooking('DUP', $booking->fresh());
        $this->assertSame(1, PromoRedemption::query()->where('booking_id', $booking->id)->where('status', PromoRedemptionStatus::Applied)->count());
    }

    public function test_redemption_count_increments_only_after_payment_success(): void
    {
        $promo = PromoCode::factory()->create([
            'agency_id' => $this->agency->id,
            'code' => 'PAY1',
            'value' => 10,
            'used_count' => 0,
        ]);
        $booking = $this->createFlightBooking();
        app(PromoCodeService::class)->applyToBooking('PAY1', $booking);
        $promo->refresh();
        $this->assertSame(0, $promo->used_count);

        app(PromoCodeService::class)->redeemForBooking($booking->fresh());
        $promo->refresh();
        $this->assertSame(1, $promo->used_count);
    }
}
