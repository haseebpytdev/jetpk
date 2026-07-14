<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\PaymentGateway;
use App\Models\SupplierConnection;
use App\Services\Payments\PaymentTransactionService;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\Payments\PublicAbhiPayCheckoutPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiaNdcAbhiPayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_pia_ndc_single_provider_fare_sets_direct_card_continue_flag(): void
    {
        $offer = [
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'provider_context' => [
                'shopping_response_ref_id' => 'shop-ref',
                'offer_ref_id' => 'offer-ref',
                'offer_item_ref_id' => 'item-ref',
                'fare_type_code' => 'ECO LIGHT',
                'fare_basis' => 'VNBAG',
                'rbd' => 'V',
            ],
            'fare_breakdown' => ['supplier_total' => 24410],
        ];

        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([], $offer);

        $this->assertTrue($presentation['single_direct_fare_on_card'] ?? false);
        $this->assertCount(1, $presentation['fare_family_options_display']);
        $this->assertSame('ECO LIGHT', $presentation['fare_family_options_display'][0]['name'] ?? null);
    }

    public function test_abhipay_blocked_when_pia_option_pnr_released(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->configureAbhiPayGateway($agency);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::PiaNdc,
            'is_active' => true,
        ]);

        $booking = Booking::factory()->for($agency)->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'pnr' => '9FD3SK',
            'supplier_reference' => 'ORDER-9FD3SK',
            'supplier_booking_status' => 'option_pnr_created',
            'payment_status' => 'unpaid',
            'status' => BookingStatus::Pending,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => [
                    'option_pnr_released' => true,
                    'order_status' => 'CLOSED',
                ],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 24410,
            'taxes' => 0,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 24410,
            'currency' => 'PKR',
        ]);

        $service = app(PaymentTransactionService::class);
        $this->assertFalse($service->canStartAbhiPayForBooking($booking->fresh('fareBreakdown')));
        $this->assertSame(
            'Airline reservation must be active before online payment.',
            $service->abhiPayStartBlockedMessage($booking->fresh('fareBreakdown')),
        );

        $presenter = app(PublicAbhiPayCheckoutPresenter::class)->forBooking($booking->fresh('fareBreakdown'), true);
        $this->assertFalse($presenter['show_pay_button']);
        $this->assertSame('Airline reservation must be active before online payment.', $presenter['blocked_message']);
    }

    public function test_abhipay_confirmation_allows_pay_when_active_option_pnr_exists(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->configureAbhiPayGateway($agency);

        $booking = Booking::factory()->for($agency)->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'pnr' => '9FD3SK',
            'supplier_reference' => 'ORDER-9FD3SK',
            'supplier_booking_status' => 'option_pnr_created',
            'payment_status' => 'unpaid',
            'status' => BookingStatus::Pending,
            'meta' => ['supplier_provider' => SupplierProvider::PiaNdc->value],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 24410,
            'taxes' => 0,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 24410,
            'currency' => 'PKR',
        ]);

        $presenter = app(PublicAbhiPayCheckoutPresenter::class)->forBooking($booking->fresh('fareBreakdown'), true);
        $this->assertTrue($presenter['show_pay_button']);
        $this->assertNull($presenter['blocked_message']);
    }

    private function configureAbhiPayGateway(Agency $agency): PaymentGateway
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
}
