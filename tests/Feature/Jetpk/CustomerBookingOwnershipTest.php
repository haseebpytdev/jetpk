<?php

namespace Tests\Feature\Jetpk;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_access_own_booking_detail(): void
    {
        [$customerA, $bookingA] = $this->customerWithBooking([
            'booking_reference' => 'BKG-OWN-A',
            'pnr' => 'PNR-OWN-A',
        ]);

        $this->actingAs($customerA)
            ->getJson(route('customer.bookings.show', ['booking' => $bookingA->booking_reference, 'format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking_reference', 'BKG-OWN-A');
    }

    public function test_customer_cannot_access_other_customer_booking_without_leaking_protected_data(): void
    {
        [$customerA, $bookingA] = $this->customerWithBooking([
            'booking_reference' => 'BKG-SECRET-A',
            'pnr' => 'PNR-SECRET-A',
        ]);
        [$customerB] = $this->customerWithBooking([
            'booking_reference' => 'BKG-OWN-B',
        ]);

        $bookingA->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Ms',
            'first_name' => 'Secret',
            'last_name' => 'Passenger',
            'is_lead_passenger' => true,
        ]);

        $response = $this->actingAs($customerB)
            ->getJson(route('customer.bookings.show', ['booking' => $bookingA->booking_reference, 'format' => 'json']));

        $response->assertForbidden();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('BKG-SECRET-A', $body);
        $this->assertStringNotContainsString('PNR-SECRET-A', $body);
        $this->assertStringNotContainsString('Secret Passenger', $body);
    }

    public function test_customer_booking_list_excludes_other_customer_bookings(): void
    {
        [$customerA, $bookingA] = $this->customerWithBooking([
            'booking_reference' => 'BKG-LIST-A',
        ]);
        [$customerB, $bookingB] = $this->customerWithBooking([
            'booking_reference' => 'BKG-LIST-B',
        ]);

        $this->actingAs($customerA)
            ->getJson(route('customer.bookings.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonFragment(['booking_reference' => $bookingA->booking_reference])
            ->assertJsonMissing(['booking_reference' => $bookingB->booking_reference]);

        $this->actingAs($customerB)
            ->getJson(route('customer.bookings.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonFragment(['booking_reference' => $bookingB->booking_reference])
            ->assertJsonMissing(['booking_reference' => $bookingA->booking_reference]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    private function customerWithBooking(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
            'travel_date' => now()->addDays(5)->toDateString(),
        ], $overrides));

        return [$customer, $booking];
    }
}
