<?php

namespace Tests\Unit\Support\Bookings;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Enums\IatiReservationLifecycleStatus;
use App\Enums\IatiSupplierReservationSource;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use App\Support\Bookings\IatiReservationLifecycleService;
use App\Support\Bookings\IatiSupplierBookingEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiReservationLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function test_instant_payment_checkout_initializes_local_only_unpaid_state(): void
    {
        $booking = $this->baseBooking();
        $protection = [
            'requires_instant_payment' => true,
            'hold_supported' => false,
            'protection_mode' => 'instant_payment_required',
            'checkout_lock_expires_at' => now()->addMinutes(15)->toIso8601String(),
            'offer_expires_at' => now()->addHour()->toIso8601String(),
        ];

        app(IatiReservationLifecycleService::class)->initializeFromCheckout($booking, $protection);

        $booking->refresh();
        $presentation = app(IatiReservationLifecycleService::class)->presentation($booking);

        $this->assertSame(IatiReservationLifecycleStatus::LocalPaymentPendingNotReserved->value, $presentation['lifecycle_status']);
        $this->assertSame(IatiSupplierReservationSource::LocalOnly->value, $presentation['reservation_source']);
        $this->assertTrue($presentation['show_not_reserved_yet']);
        $this->assertNull($booking->supplier_reference);
        $this->assertFalse($presentation['may_show_pnr_pending']);
    }

    #[Test]
    public function test_hold_supported_outcome_stores_supplier_hold_and_expiry(): void
    {
        $booking = $this->baseBooking(holdSupported: true);
        app(IatiReservationLifecycleService::class)->initializeFromCheckout($booking, [
            'requires_instant_payment' => false,
            'hold_supported' => true,
            'protection_mode' => 'hold_price_guaranteed',
            'payment_required_by' => now()->addHours(2)->toIso8601String(),
            'checkout_lock_expires_at' => now()->addMinutes(15)->toIso8601String(),
        ]);

        app(IatiReservationLifecycleService::class)->applySupplierHoldOutcome(
            $booking,
            new SupplierBookingResultData(
                success: true,
                status: 'pending_ticketing',
                provider: SupplierProvider::Iati->value,
                supplier_reference: 'IATI-ORDER-99',
            ),
        );

        $presentation = app(IatiReservationLifecycleService::class)->presentation($booking->fresh());
        $this->assertSame(IatiSupplierReservationSource::SupplierHold->value, $presentation['reservation_source']);
        $this->assertSame(IatiReservationLifecycleStatus::SupplierHoldPendingPayment->value, $presentation['lifecycle_status']);
        $this->assertTrue($presentation['show_supplier_hold_active']);
        $this->assertNotNull($presentation['supplier_hold_expires_at']);
    }

    #[Test]
    public function test_expired_local_unpaid_booking_blocks_supplier_book(): void
    {
        $booking = $this->baseBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['iati_reservation'] = [
            'requires_instant_payment' => true,
            'local_checkout_expires_at' => now()->subMinute()->toIso8601String(),
            'supplier_reservation_source' => IatiSupplierReservationSource::LocalOnly->value,
        ];
        $booking->update(['meta' => $meta, 'payment_status' => 'unpaid']);

        $gate = app(IatiReservationLifecycleService::class)->assertSupplierBookAllowed($booking->fresh());
        $this->assertFalse($gate['allowed']);
        $this->assertSame('local_checkout_expired', $gate['error_code']);
    }

    #[Test]
    public function test_unpaid_instant_payment_blocks_supplier_book(): void
    {
        $booking = $this->baseBooking();
        app(IatiReservationLifecycleService::class)->initializeFromCheckout($booking, [
            'requires_instant_payment' => true,
            'hold_supported' => false,
            'protection_mode' => 'instant_payment_required',
            'checkout_lock_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);

        $gate = app(IatiReservationLifecycleService::class)->assertSupplierBookAllowed($booking->fresh());
        $this->assertFalse($gate['allowed']);
        $this->assertSame('payment_not_verified', $gate['error_code']);
    }

    #[Test]
    public function test_expired_local_paid_booking_still_requires_fresh_revalidation_at_book_time(): void
    {
        $booking = $this->baseBooking(paid: true);
        app(IatiReservationLifecycleService::class)->initializeFromCheckout($booking, [
            'requires_instant_payment' => true,
            'hold_supported' => false,
            'protection_mode' => 'instant_payment_required',
            'checkout_lock_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);

        $this->assertTrue(app(IatiReservationLifecycleService::class)->assertSupplierBookAllowed($booking->fresh())['allowed']);
    }

    #[Test]
    public function test_paid_booking_pre_book_revalidation_is_enforced_in_service_not_eligibility_gate(): void
    {
        $booking = $this->baseBooking(paid: true);
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Iati]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['supplier_connection_id'] = $connection->id;
        $booking->update(['meta' => $meta, 'selected_fare_total' => 89717]);

        $mock = Mockery::mock(IatiFareRevalidationService::class);
        $mock->shouldReceive('revalidate')->once()->andReturn(new OfferValidationResultData(
            is_valid: true,
            status: 'changed',
            price_changed: true,
            old_total: 89717,
            new_total: 95000,
            currency: 'PKR',
            validated_offer: NormalizedFlightOfferData::fromArray($meta['validated_offer_snapshot']),
        ));
        $this->app->instance(IatiFareRevalidationService::class, $mock);

        $result = app(IatiReservationLifecycleService::class)->runPreBookRevalidation($booking->fresh(), $connection);
        $this->assertFalse($result['ok']);
        $this->assertSame('fare_changed', $result['error_code']);

        $gate = app(IatiReservationLifecycleService::class)->assertSupplierBookAllowed($booking->fresh());
        $this->assertFalse($gate['allowed']);
        $this->assertContains('fare_change_pending', IatiSupplierBookingEligibility::evaluate($booking->fresh())['missing']);
    }

    #[Test]
    public function test_fare_decrease_records_difference_for_resolution(): void
    {
        $booking = $this->baseBooking(paid: true);
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Iati]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['supplier_connection_id'] = $connection->id;
        $booking->update(['meta' => $meta, 'selected_fare_total' => 90000]);

        $mock = Mockery::mock(IatiFareRevalidationService::class);
        $mock->shouldReceive('revalidate')->once()->andReturn(new OfferValidationResultData(
            is_valid: true,
            status: 'changed',
            price_changed: true,
            old_total: 90000,
            new_total: 85000,
            currency: 'PKR',
            validated_offer: NormalizedFlightOfferData::fromArray($meta['validated_offer_snapshot']),
        ));
        $this->app->instance(IatiFareRevalidationService::class, $mock);

        $result = app(IatiReservationLifecycleService::class)->runPreBookRevalidation($booking->fresh(), $connection);
        $this->assertFalse($result['ok']);
        $presentation = app(IatiReservationLifecycleService::class)->presentation($booking->fresh());
        $this->assertTrue($presentation['fare_change_requires_acceptance']);
        $this->assertEqualsWithDelta(-5000.0, (float) $presentation['fare_change_difference'], 0.01);
    }

    #[Test]
    public function test_unavailable_fare_blocks_supplier_book(): void
    {
        $booking = $this->baseBooking(paid: true);
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Iati]);

        $mock = Mockery::mock(IatiFareRevalidationService::class);
        $mock->shouldReceive('revalidate')->once()->andReturn(new OfferValidationResultData(
            is_valid: false,
            status: 'unavailable',
        ));
        $this->app->instance(IatiFareRevalidationService::class, $mock);

        $result = app(IatiReservationLifecycleService::class)->runPreBookRevalidation($booking->fresh(), $connection);
        $this->assertFalse($result['ok']);
        $this->assertSame(IatiReservationLifecycleStatus::FareUnavailableAdminReview->value, app(IatiReservationLifecycleService::class)->resolveLifecycleStatus($booking->fresh())->value);
    }

    #[Test]
    public function test_presentation_never_claims_reserved_without_supplier_order(): void
    {
        $booking = $this->baseBooking();
        $presentation = app(IatiReservationLifecycleService::class)->presentation($booking);
        $this->assertFalse($presentation['is_reserved_with_supplier']);
        $this->assertStringNotContainsString('Reserved with Airline', $presentation['customer_headline']);
    }

    protected function baseBooking(bool $paid = false, bool $holdSupported = false): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'route' => 'LHE → DXB',
            'selected_fare_total' => 89717,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'requires_instant_payment' => ! $holdSupported,
                'hold_supported' => $holdSupported,
                'protection_mode' => $holdSupported ? 'hold_price_guaranteed' : 'instant_payment_required',
                'offer_validation_status' => 'valid',
                'supplier_currency' => 'PKR',
                'search_criteria' => ['origin' => 'LHE', 'destination' => 'DXB', 'adults' => 1],
                'iati_context' => ['departure_fare_key' => 'dep-key', 'fare_detail_key' => 'fare-detail'],
                'validated_offer_snapshot' => [
                    'offer_id' => 'iati-offer-1',
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'airline_code' => 'PK',
                    'fare_breakdown' => ['supplier_total' => 89717, 'currency' => 'PKR'],
                    'raw_payload' => [
                        'provider_context' => [
                            'departure_fare_key' => 'dep-key',
                            'fare_detail_key' => 'fare-detail',
                            'offer_keys' => [],
                        ],
                    ],
                ],
            ],
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'pax@example.com',
            'phone' => '3001234567',
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => '1990-01-01',
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => '2030-01-01',
            'nationality' => 'PK',
            'passenger_type' => 'adult',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }
}
