<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\IatiPersistedContextResolver;
use App\Support\Bookings\IatiSelectedOfferReadiness;
use App\Support\Bookings\IatiSupplierBookingEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiSelectedOfferReadinessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_latest_failed_selected_offer_unresolved_blocks_readiness(): void
    {
        $booking = $this->booking62Style();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Iati->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'selected_offer_unresolved',
            'error_message' => 'IATI fare confirmation returned no bookable offers. Admin review required.',
            'attempted_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);

        $readiness = IatiPersistedContextResolver::readiness($booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']));

        $this->assertFalse($readiness['eligible_for_supplier_book']);
        $this->assertContains('selected_offer_unresolved', $readiness['blocking_reasons']);
        $this->assertSame('admin_review_or_research', $readiness['next_supplier_action']);
    }

    #[Test]
    public function test_expired_local_checkout_without_bookable_offer_blocks_book(): void
    {
        $booking = $this->booking62Style(localCheckoutExpired: true);

        $readiness = IatiPersistedContextResolver::readiness($booking);

        $this->assertFalse($readiness['eligible_for_supplier_book']);
        $this->assertContains('local_checkout_expired_no_bookable_offer', $readiness['blocking_reasons']);
        $this->assertSame('admin_review_or_research', $readiness['next_supplier_action']);
    }

    #[Test]
    public function test_blank_selected_fare_fields_on_mixed_carrier_blocks_with_context_missing(): void
    {
        $booking = $this->booking62Style(includeFailedAttempt: false);

        $readiness = IatiSupplierBookingEligibility::evaluate($booking);

        $this->assertFalse($readiness['eligible']);
        $this->assertContains('selected_offer_context_missing', $readiness['missing']);
        $this->assertNull(IatiSelectedOfferReadiness::selectionResolvedFrom(
            $booking,
            is_array($booking->meta) ? $booking->meta : [],
            IatiPersistedContextResolver::resolveProviderContext(is_array($booking->meta) ? $booking->meta : [], $booking),
        ));
    }

    #[Test]
    public function test_admin_offer_validation_not_valid_after_selected_offer_unresolved(): void
    {
        $booking = $this->booking62Style();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Iati->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'selected_offer_unresolved',
            'error_message' => 'IATI fare confirmation returned no bookable offers. Admin review required.',
            'attempted_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);

        $presentation = IatiSelectedOfferReadiness::adminOfferValidationPresentation($booking->fresh(['supplierBookingAttempts']));

        $this->assertFalse($presentation['show_as_valid']);
        $this->assertStringContainsString('no bookable offer', strtolower($presentation['label']));

        $actions = app(AdminBookingSupplierActions::class)->build($booking->fresh(['supplierBookingAttempts']), false, false);
        $this->assertFalse($actions['can_create_pnr']);
        $this->assertFalse($actions['can_retry_pnr']);
    }

    #[Test]
    public function test_paid_same_carrier_simple_iati_remains_eligible(): void
    {
        $booking = $this->simpleSameCarrierBooking();

        $readiness = IatiSupplierBookingEligibility::evaluate($booking);

        $this->assertTrue($readiness['eligible']);
        $this->assertSame('simple_unbranded_fare_keys', IatiSelectedOfferReadiness::selectionResolvedFrom(
            $booking,
            is_array($booking->meta) ? $booking->meta : [],
            IatiPersistedContextResolver::resolveProviderContext(is_array($booking->meta) ? $booking->meta : [], $booking),
        ));

        $command = $this->artisan('ota:iati-booking-readiness', ['--booking-id' => $booking->id]);
        $command->assertExitCode(0)
            ->expectsOutputToContain('eligible_for_supplier_book=true')
            ->expectsOutputToContain('next_supplier_action=/book');
    }

    #[Test]
    public function test_fare_confirmation_diagnostics_exclude_raw_fare_keys(): void
    {
        $booking = $this->booking62Style();
        $diagnostics = IatiSelectedOfferReadiness::fareConfirmationDiagnostics($booking);

        $this->assertSame('fare_confirmation_selected_offer_resolution', $diagnostics['step']);
        $this->assertFalse($diagnostics['fare_option_key_present']);
        $this->assertTrue($diagnostics['departure_fare_key_present']);
        $this->assertSame(0, $diagnostics['offer_keys_count']);
        $this->assertTrue($diagnostics['mixed_carrier']);
        $this->assertSame(['PK', 'XY'], $diagnostics['marketing_carrier_chain']);
        $encoded = json_encode($diagnostics);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('dep-secret-key', $encoded);
        $this->assertStringNotContainsString('fare-detail-secret', $encoded);
    }

    protected function booking62Style(bool $localCheckoutExpired = true, bool $includeFailedAttempt = true): Booking
    {
        $agency = Agency::factory()->create();
        $expiry = $localCheckoutExpired ? now()->subHour() : now()->addHour();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => 'paid',
            'route' => 'LHE → ABT',
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'checkout_search_id' => '5b53d881-9246-4b3c-8296-2a85507096d7',
                'checkout_offer_id' => 'iati_eb230941fe3ae8db',
                'original_offer_id' => 'iati_eb230941fe3ae8db',
                'fare_option_key' => '',
                'offer_validation_status' => 'valid',
                'requires_instant_payment' => true,
                'checkout_lock_expires_at' => $expiry->toIso8601String(),
                'iati_context' => [
                    'departure_fare_key' => 'dep-secret-key',
                    'fare_detail_key' => 'fare-detail-secret',
                ],
                'iati_reservation' => [
                    'requires_instant_payment' => true,
                    'local_checkout_expires_at' => $expiry->toIso8601String(),
                ],
                'validated_offer_snapshot' => [
                    'offer_id' => 'iati_eb230941fe3ae8db',
                    'mixed_carrier' => true,
                    'segments' => [
                        ['marketing_carrier' => 'PK', 'operating_carrier' => 'PK'],
                        ['marketing_carrier' => 'XY', 'operating_carrier' => 'XY'],
                    ],
                    'raw_payload' => [
                        'provider_context' => [
                            'departure_fare_key' => 'dep-secret-key',
                            'fare_detail_key' => 'fare-detail-secret',
                            'offer_keys' => [],
                            'fare_offers' => [],
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

        if ($includeFailedAttempt) {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Iati->value,
                'action' => 'create_pnr',
                'status' => 'failed',
                'error_code' => 'selected_offer_unresolved',
                'error_message' => 'IATI fare confirmation returned no bookable offers. Admin review required.',
                'attempted_at' => now()->subMinutes(30),
                'completed_at' => now()->subMinutes(30),
            ]);
        }

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']);
    }

    protected function simpleSameCarrierBooking(): Booking
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
                'requires_instant_payment' => true,
                'checkout_lock_expires_at' => now()->addHour()->toIso8601String(),
                'iati_context' => [
                    'departure_fare_key' => 'dep-pk-60',
                    'fare_detail_key' => 'fare-detail-pk-60',
                ],
                'validated_offer_snapshot' => [
                    'offer_id' => 'iati_7e96ed26e2213b49',
                    'airline_code' => 'PK',
                    'mixed_carrier' => false,
                    'raw_payload' => [
                        'provider_context' => [
                            'departure_fare_key' => 'dep-pk-60',
                            'fare_detail_key' => 'fare-detail-pk-60',
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

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }
}
