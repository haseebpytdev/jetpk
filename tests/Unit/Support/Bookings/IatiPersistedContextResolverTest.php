<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\IatiPersistedContextResolver;
use App\Support\Bookings\IatiSupplierBookingEligibility;
use App\Support\Bookings\TicketingReadinessPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiPersistedContextResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_resolver_recovers_departure_fare_key_from_selected_fare_family_option(): void
    {
        $meta = [
            'supplier_provider' => SupplierProvider::Iati->value,
            'fare_option_key' => 'iati-fare-2-85158-1',
            'selected_fare_family_option' => [
                'option_key' => 'iati-fare-2-85158-1',
                'departure_fare_key' => 'dep-from-family',
                'name' => 'Fare 2',
                'displayed_price' => 85158,
            ],
            'validated_offer_snapshot' => [
                'offer_id' => 'offer-58',
                'fare_breakdown' => ['supplier_total' => 85158],
            ],
        ];

        $context = IatiPersistedContextResolver::resolveProviderContext($meta);

        $this->assertSame('dep-from-family', $context['departure_fare_key']);
    }

    #[Test]
    public function test_enrich_meta_for_persistence_sets_iati_context_and_selection_ids(): void
    {
        $snapshot = json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')), true);
        $normalized = [
            'offer_id' => 'offer-58',
            'supplier_provider' => 'iati',
            'raw_payload' => [
                'provider_context' => [
                    'departure_fare_key' => 'dep-match-key',
                ],
            ],
            'fare_family_options_display' => [
                [
                    'option_key' => 'iati-fare-2-85158-1',
                    'departure_fare_key' => 'dep-match-key',
                    'name' => 'Fare 2',
                    'price_total' => 85158,
                ],
            ],
        ];

        $meta = IatiPersistedContextResolver::enrichMetaForPersistence([
            'validated_offer_snapshot' => $normalized,
            'fare_option_key' => 'iati-fare-2-85158-1',
            'selected_fare_family_option' => [
                'option_key' => 'iati-fare-2-85158-1',
                'name' => 'Fare 2',
                'displayed_price' => 85158,
            ],
        ], SupplierProvider::Iati->value, 'iati-fare-2-85158-1');

        $this->assertSame('iati-fare-2-85158-1', $meta['selected_fare_option_id']);
        $this->assertNotEmpty($meta['iati_context']['departure_fare_key'] ?? null);
    }

    #[Test]
    public function test_airblue_detection_uses_carrier_code_pa(): void
    {
        $this->assertTrue(IatiPersistedContextResolver::isAirBlueBooking([
            'validated_offer_snapshot' => ['airline_code' => 'PA'],
        ]));
    }

    #[Test]
    public function test_readiness_returns_eligible_for_supplier_book_for_valid_persisted_snapshot(): void
    {
        $booking = $this->eligibleIatiBooking();
        $readiness = IatiPersistedContextResolver::readiness($booking);

        $this->assertTrue($readiness['eligible_for_supplier_book']);
        $this->assertSame('/book', $readiness['next_supplier_action']);
        $this->assertTrue($readiness['departure_fare_key_present']);
        $this->assertFalse($readiness['supplier_order_exists']);
        $this->assertFalse($readiness['live_supplier_call_attempted']);
        $this->assertSame([], $readiness['blocking_reasons']);
    }

    #[Test]
    public function test_readiness_matches_command_style_safe_output_without_raw_keys(): void
    {
        $booking = $this->eligibleIatiBooking();
        $readiness = IatiPersistedContextResolver::readiness($booking);

        $this->assertSame('iati-fare-2-85158-1', $readiness['selected_fare_option_id']);
        $this->assertSame('booking_snapshot', $readiness['selected_fare_resolved_from']);
        $this->assertArrayNotHasKey('departure_fare_key', $readiness);
        $this->assertArrayNotHasKey('fare_detail_key', $readiness);
    }

    #[Test]
    public function test_admin_create_pnr_reason_uses_iati_specific_blocker_not_generic(): void
    {
        $booking = $this->bookingMissingDepartureKey();
        $actions = app(AdminBookingSupplierActions::class);
        $state = $actions->build($booking, false, false);

        $reason = (string) ($state['create_pnr_reason'] ?? '');
        $this->assertStringContainsString('IATI supplier booking blocked', $reason);
        $this->assertStringNotContainsString('Offer validation and booking prerequisites are not complete.', $reason);
    }

    #[Test]
    public function test_ticketing_readiness_does_not_mark_iati_as_supplier_not_supported(): void
    {
        $booking = $this->eligibleIatiBooking();
        $readiness = TicketingReadinessPresenter::forBooking($booking);

        $this->assertNotSame(
            TicketingReadinessPresenter::OVERALL_BLOCKED_SUPPLIER_NOT_SUPPORTED,
            $readiness['overall_status'],
        );
    }

    #[Test]
    public function test_paid_booking_with_family_departure_key_is_eligible_without_search_id(): void
    {
        $booking = $this->eligibleIatiBooking();
        $booking->update(['meta' => array_merge(is_array($booking->meta) ? $booking->meta : [], [
            'search_id' => '',
            'validated_offer_snapshot' => [
                'offer_id' => 'offer-58',
                'fare_breakdown' => ['supplier_total' => 85158],
            ],
            'selected_fare_family_option' => [
                'option_key' => 'iati-fare-2-85158-1',
                'departure_fare_key' => 'dep-from-family',
                'name' => 'Fare 2',
                'displayed_price' => 85158,
            ],
        ])]);

        $this->assertTrue(IatiSupplierBookingEligibility::isEligible($booking->fresh(['passengers', 'contact', 'supplierBookings'])));
    }

    protected function eligibleIatiBooking(): Booking
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
                'selected_fare_option_id' => 'iati-fare-2-85158-1',
                'selected_branded_fare_id' => 'iati_brand_1',
                'selected_fare_family_option' => [
                    'option_key' => 'iati-fare-2-85158-1',
                    'departure_fare_key' => 'dep-match-key',
                    'name' => 'Fare 2',
                    'displayed_price' => 85158,
                ],
                'validated_offer_snapshot' => [
                    'offer_id' => 'offer-58',
                    'raw_payload' => [
                        'provider_context' => ['departure_fare_key' => 'dep-match-key'],
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

    protected function bookingMissingDepartureKey(): Booking
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
                'selected_fare_option_id' => 'iati-fare-2-85158-1',
                'validated_offer_snapshot' => ['offer_id' => 'offer-58'],
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }
}
