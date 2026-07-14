<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingHoldSession;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\IatiHoldSessionAutoLinkService;
use App\Support\Bookings\IatiReservationLifecycleService;
use App\Support\Bookings\IatiSupplierBookingEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiHoldSessionAutoLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_auto_links_exactly_one_matching_orphan_hold_for_instant_payment_iati_booking(): void
    {
        $booking = $this->unlinkedIatiBooking();
        $hold = $this->orphanHold($booking, [
            'validated_total_amount' => 120590.0,
            'validated_total_currency' => 'PKR',
            'converted_total_pkr' => 120590.0,
            'local_checkout_expires_at' => now()->addMinutes(20),
        ]);

        $linked = app(IatiHoldSessionAutoLinkService::class)->attemptAutoLink($booking);

        $this->assertNotNull($linked);
        $this->assertSame($hold->id, $linked->id);
        $booking->refresh();
        $hold->refresh();
        $this->assertSame($hold->id, $booking->hold_session_id);
        $this->assertSame($booking->id, $hold->booking_id);
        $this->assertEqualsWithDelta(120590.0, (float) $hold->validated_total_amount, 0.01);
        $this->assertSame('PKR', $hold->validated_total_currency);
        $this->assertEqualsWithDelta(120590.0, (float) $hold->converted_total_pkr, 0.01);
        $this->assertNull($hold->supplier_order_id);
        $this->assertNull($hold->supplier_order_reference);
        $this->assertNull($booking->pnr);
        $this->assertNull($booking->supplier_reference);
        $this->assertSame(['payment_not_verified'], IatiSupplierBookingEligibility::evaluate($booking->fresh())['missing']);
    }

    #[Test]
    public function test_does_not_overwrite_existing_linked_hold(): void
    {
        $booking = $this->unlinkedIatiBooking();
        $existingHold = $this->orphanHold($booking, ['search_id' => 'search-existing', 'offer_id' => 'offer-existing']);
        $booking->update(['hold_session_id' => $existingHold->id]);
        $existingHold->update(['booking_id' => $booking->id]);

        $orphan = $this->orphanHold($booking);

        $linked = app(IatiHoldSessionAutoLinkService::class)->attemptAutoLink($booking->fresh());

        $this->assertNull($linked);
        $booking->refresh();
        $this->assertSame($existingHold->id, $booking->hold_session_id);
        $orphan->refresh();
        $this->assertNull($orphan->booking_id);
    }

    #[Test]
    public function test_multiple_matching_orphans_do_not_auto_link(): void
    {
        Log::spy();

        $booking = $this->unlinkedIatiBooking();
        $this->orphanHold($booking);
        $this->orphanHold($booking);

        $linked = app(IatiHoldSessionAutoLinkService::class)->attemptAutoLink($booking);

        $this->assertNull($linked);
        $booking->refresh();
        $this->assertNull($booking->hold_session_id);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('iati_hold_session_auto_link_ambiguous', \Mockery::on(function (array $context) use ($booking): bool {
                return (int) ($context['booking_id'] ?? 0) === $booking->id
                    && count($context['candidate_hold_session_ids'] ?? []) === 2;
            }));
    }

    #[Test]
    public function test_hold_linked_to_another_booking_is_not_stolen(): void
    {
        $booking = $this->unlinkedIatiBooking();
        $otherBooking = Booking::factory()->create([
            'agency_id' => $booking->agency_id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
        ]);
        $foreignHold = $this->orphanHold($booking);
        $foreignHold->update(['booking_id' => $otherBooking->id]);

        $linked = app(IatiHoldSessionAutoLinkService::class)->attemptAutoLink($booking);

        $this->assertNull($linked);
        $booking->refresh();
        $foreignHold->refresh();
        $this->assertNull($booking->hold_session_id);
        $this->assertSame($otherBooking->id, $foreignHold->booking_id);
    }

    #[Test]
    public function test_non_iati_booking_is_unchanged(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'status' => BookingStatus::Pending,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'checkout_search_id' => 'search-61',
                'checkout_offer_id' => 'offer-61',
            ],
        ]);
        $hold = $this->orphanHold($booking);

        $linked = app(IatiHoldSessionAutoLinkService::class)->attemptAutoLink($booking);

        $this->assertNull($linked);
        $hold->refresh();
        $this->assertNull($hold->booking_id);
    }

    #[Test]
    public function test_auto_link_does_not_create_supplier_mutations(): void
    {
        $booking = $this->unlinkedIatiBooking();
        $this->orphanHold($booking);

        app(IatiHoldSessionAutoLinkService::class)->attemptAutoLink($booking);

        $this->assertSame(0, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertNull($booking->supplier_reference);
        $this->assertNull($booking->supplier_api_booking_id);
    }

    #[Test]
    public function test_lifecycle_mirrors_linked_hold_local_checkout_expiry(): void
    {
        $booking = $this->unlinkedIatiBooking();
        $holdExpiry = now()->addMinutes(22);
        $hold = $this->orphanHold($booking, ['local_checkout_expires_at' => $holdExpiry]);

        app(IatiHoldSessionAutoLinkService::class)->attemptAutoLink($booking);
        $booking = $booking->fresh(['holdSession']);

        app(IatiReservationLifecycleService::class)->initializeFromCheckout($booking, [
            'requires_instant_payment' => true,
            'hold_supported' => false,
            'protection_mode' => 'instant_payment_required',
            'checkout_lock_expires_at' => now()->addMinutes(5)->toIso8601String(),
        ], $hold->fresh());

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $block = is_array($meta['iati_reservation'] ?? null) ? $meta['iati_reservation'] : [];
        $this->assertSame($holdExpiry->toIso8601String(), $block['local_checkout_expires_at'] ?? null);

        $presentation = app(IatiReservationLifecycleService::class)->presentation($booking->fresh(['holdSession']));
        $this->assertNull($presentation['supplier_hold_expires_at']);
    }

    protected function unlinkedIatiBooking(): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'hold_session_id' => null,
            'selected_fare_total' => 120590,
            'revalidated_fare_total' => 120590,
            'route' => 'LHE → JED',
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'requires_instant_payment' => true,
                'hold_supported' => false,
                'protection_mode' => 'instant_payment_required',
                'offer_validation_status' => 'valid',
                'checkout_search_id' => 'search-61',
                'checkout_offer_id' => 'offer-61',
                'original_offer_id' => 'offer-61',
                'supplier_currency' => 'PKR',
                'search_criteria' => ['origin' => 'LHE', 'destination' => 'JED', 'adults' => 1],
                'iati_context' => ['departure_fare_key' => 'dep-key', 'fare_detail_key' => 'fare-detail'],
                'validated_offer_snapshot' => [
                    'offer_id' => 'offer-61',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'airline_code' => 'PF',
                    'fare_breakdown' => ['supplier_total' => 120590, 'currency' => 'PKR'],
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function orphanHold(Booking $booking, array $overrides = []): BookingHoldSession
    {
        return BookingHoldSession::query()->create(array_merge([
            'agency_id' => $booking->agency_id,
            'booking_id' => null,
            'search_id' => 'search-61',
            'offer_id' => 'offer-61',
            'supplier_provider' => SupplierProvider::Iati->value,
            'validated_total_amount' => 120590.0,
            'validated_total_currency' => 'PKR',
            'converted_total_pkr' => 120590.0,
            'hold_status' => 'not_supported',
            'requires_instant_payment' => true,
            'expires_at' => now()->addHour(),
        ], $overrides));
    }
}
