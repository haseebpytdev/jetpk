<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\User;
use App\Services\Suppliers\SupplierBookingService;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\IatiSupplierBookingEligibility;
use App\Support\FlightSearch\SelectedFareContextAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiSupplierBookingEligibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_iati_paid_booking_with_persisted_snapshot_and_empty_search_id_is_eligible(): void
    {
        $booking = $this->iatiBookingWithSnapshot(searchId: '');

        $this->assertTrue(IatiSupplierBookingEligibility::appliesTo($booking));
        $this->assertTrue(IatiSupplierBookingEligibility::isEligible($booking));
        $this->assertTrue(app(SupplierBookingService::class)->isBookingEligible($booking));
        $this->assertNull(
            app(AdminBookingSupplierActions::class)->assertSupplierBookingPostAllowed(
                $booking,
                app(SupplierBookingService::class)->isBookingEligible($booking),
            ),
        );
        $actions = app(AdminBookingSupplierActions::class)->build(
            $booking,
            app(SupplierBookingService::class)->isBookingEligible($booking),
            false,
        );
        $this->assertTrue($actions['can_create_pnr']);
    }

    #[Test]
    public function test_booking_58_style_pending_paid_iati_passes_supplier_booking_eligibility_gate(): void
    {
        $booking = $this->iatiBookingWithSnapshot(searchId: '');
        $booking->update([
            'status' => BookingStatus::Pending,
            'payment_status' => 'paid',
            'pnr' => null,
            'supplier_reference' => null,
        ]);
        $booking = $booking->fresh(['passengers', 'contact', 'supplierBookings']);

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertTrue(IatiSupplierBookingEligibility::appliesTo($booking));
        $this->assertTrue(IatiSupplierBookingEligibility::isEligible($booking));
        $this->assertTrue(app(SupplierBookingService::class)->isBookingEligible($booking));
    }

    #[Test]
    public function test_create_supplier_booking_does_not_return_booking_not_eligible_for_ready_iati(): void
    {
        $booking = $this->iatiBookingWithSnapshot(searchId: '');
        $actor = User::factory()->create();

        $result = app(SupplierBookingService::class)->createSupplierBooking($booking, $actor);

        $this->assertNotSame('booking_not_eligible', $result->error_code);
    }

    #[Test]
    public function test_selected_fare_audit_resolves_from_booking_snapshot_without_search_cache(): void
    {
        $booking = $this->iatiBookingWithSnapshot(searchId: '');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $offer = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        $intent = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : null;
        $fareOptionKey = IatiSupplierBookingEligibility::selectedFareOptionKeyFromMeta($meta);

        $report = SelectedFareContextAuditor::buildReport($offer, '', 'offer-58', $fareOptionKey, [], $intent);

        $this->assertTrue($report['selection_resolved']);
        $this->assertSame('booking_snapshot', $report['selection_resolution_source']);
        $this->assertTrue($report['snapshot_context_present']);
        $this->assertTrue($report['snapshot_context_valid']);
        $this->assertSame('not_available', $report['live_search_resolution']);
        $this->assertNull($report['selection_error_code']);
    }

    #[Test]
    public function test_iati_missing_departure_fare_key_remains_blocked(): void
    {
        $booking = $this->iatiBookingWithSnapshot(searchId: '', includeDepartureFareKey: false);

        $readiness = IatiSupplierBookingEligibility::evaluate($booking);

        $this->assertFalse($readiness['eligible']);
        $this->assertContains('missing_departure_fare_key', $readiness['missing']);
        $this->assertFalse(app(SupplierBookingService::class)->isBookingEligible($booking));
    }

    #[Test]
    public function test_iati_existing_supplier_reference_remains_duplicate_blocked(): void
    {
        $booking = $this->iatiBookingWithSnapshot(searchId: '');
        $booking->update(['supplier_reference' => 'order-existing-58']);

        $readiness = IatiSupplierBookingEligibility::evaluate($booking);

        $this->assertFalse($readiness['eligible']);
        $this->assertContains('already_has_supplier_order', $readiness['missing']);
        $this->assertFalse(app(SupplierBookingService::class)->isBookingEligible($booking));
    }

    #[Test]
    public function test_duffel_booking_keeps_existing_status_gate(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Duffel->value,
            'status' => BookingStatus::Pending,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'offer_validation_status' => 'valid',
                'validated_offer_snapshot' => ['offer_id' => 'duffel-offer'],
            ],
        ]);

        $this->assertFalse(app(SupplierBookingService::class)->isBookingEligible($booking));
    }

    #[Test]
    public function test_simple_iati_fare_without_selected_fare_option_id_does_not_block_on_missing_selection(): void
    {
        $booking = $this->simpleIatiBooking60Style(paymentStatus: 'unpaid');

        $readiness = IatiSupplierBookingEligibility::evaluate($booking);

        $this->assertFalse($readiness['eligible']);
        $this->assertContains('payment_not_verified', $readiness['missing']);
        $this->assertNotContains('missing_selected_fare_option', $readiness['missing']);
        $this->assertTrue($readiness['selected_fare_option_present']);
    }

    #[Test]
    public function test_paid_simple_iati_fare_is_eligible_for_supplier_book(): void
    {
        $booking = $this->simpleIatiBooking60Style(paymentStatus: 'paid');

        $readiness = IatiSupplierBookingEligibility::evaluate($booking);

        $this->assertTrue($readiness['eligible']);
        $this->assertNotContains('missing_selected_fare_option', $readiness['missing']);
        $this->assertTrue(app(SupplierBookingService::class)->isBookingEligible($booking));
    }

    #[Test]
    public function test_branded_iati_fare_still_requires_selected_fare_option_id(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'offer_validation_status' => 'valid',
                'validated_offer_snapshot' => [
                    'offer_id' => 'offer-branded',
                    'branded_fares' => [
                        ['id' => 'brand-1', 'name' => 'Economy Flex'],
                    ],
                    'raw_payload' => [
                        'provider_context' => [
                            'departure_fare_key' => 'dep-branded',
                            'fare_detail_key' => 'fare-detail-branded',
                            'offer_keys' => ['offer-key-1', 'offer-key-2'],
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

        $readiness = IatiSupplierBookingEligibility::evaluate($booking->fresh(['passengers', 'contact', 'supplierBookings']));

        $this->assertFalse($readiness['eligible']);
        $this->assertContains('missing_selected_fare_option', $readiness['missing']);
    }

    protected function simpleIatiBooking60Style(string $paymentStatus): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => $paymentStatus,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'checkout_search_id' => '05e1da25-5925-40bc-8f66-58ec552a9fca',
                'checkout_offer_id' => 'iati_7e96ed26e2213b49',
                'offer_validation_status' => 'valid',
                'iati_context' => [
                    'departure_fare_key' => 'dep-pk-60',
                    'fare_detail_key' => 'fare-detail-pk-60',
                ],
                'validated_offer_snapshot' => [
                    'offer_id' => 'iati_7e96ed26e2213b49',
                    'airline_code' => 'PK',
                    'raw_payload' => [
                        'provider_context' => [
                            'departure_fare_key' => 'dep-pk-60',
                            'fare_detail_key' => 'fare-detail-pk-60',
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
            'phone_country_code' => '92',
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }

    protected function iatiBookingWithSnapshot(string $searchId, bool $includeDepartureFareKey = true): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'search_id' => $searchId,
                'selected_fare_option_id' => 'iati-fare-2-85158-1',
                'selected_branded_fare_id' => 'iati_brand_1',
                'offer_validation_status' => 'valid',
                'selected_fare_family_option' => [
                    'option_key' => 'iati-fare-2-85158-1',
                    'name' => 'IATI Fare 2',
                    'displayed_price' => 85158,
                    'baggage_summary' => '20 kg',
                    'carry_on_summary' => '1 piece',
                ],
                'validated_offer_snapshot' => [
                    'offer_id' => 'offer-58',
                    'supplier_provider' => 'iati',
                    'fare_breakdown' => [
                        'supplier_total' => 85158,
                        'currency' => 'PKR',
                    ],
                    'raw_payload' => [
                        'provider_context' => array_filter([
                            'departure_fare_key' => $includeDepartureFareKey ? 'dep-match-key' : null,
                            'selected_branded_fare_id' => 'iati_brand_1',
                            'selected_fare_option_id' => 'iati-fare-2-85158-1',
                        ]),
                    ],
                ],
            ],
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'pax@example.com',
            'phone' => '3001234567',
            'phone_country_code' => '92',
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }
}
