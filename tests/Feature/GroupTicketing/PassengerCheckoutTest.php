<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota.group_ticketing.inventory_search_sync_enabled' => false]);
    }

    private function inventory(int $seats = 5): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'pax-1',
            'public_id' => 'ALH-PAX-1',
            'title' => 'Pax Test',
            'sector' => 'SKT-SHJ',
            'total_seats' => $seats,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function passengerPayload(int $index = 0): array
    {
        return [
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'nationality' => 'Pakistani',
            'document_type' => 'passport',
            'passport_number' => 'AB123456'.$index,
            'passport_issue_date' => '2020-01-01',
            'passport_expiry' => '2030-01-01',
            'passenger_type' => 'adult',
        ];
    }

    public function test_passenger_form_validates_required_fields(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->post(route('group-ticketing.booking.passengers.store', $inventory), [
            'seat_count' => 1,
        ])->assertSessionHasErrors(['passengers', 'contact_name']);
    }

    public function test_cannot_request_more_seats_than_available(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory(2);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->post(route('group-ticketing.booking.passengers.store', $inventory), [
            'seat_count' => 3,
            'contact_name' => 'Ali Khan',
            'contact_email' => 'ali@example.com',
            'contact_phone' => '+923001234567',
            'passengers' => [
                $this->passengerPayload(0),
                $this->passengerPayload(1),
                $this->passengerPayload(2),
            ],
        ])->assertSessionHasErrors(['seat_count']);
    }

    public function test_review_confirm_creates_reservation_with_expires_at(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->post(route('group-ticketing.booking.passengers.store', $inventory), [
            'seat_count' => 1,
            'contact_name' => 'Ali Khan',
            'contact_email' => 'ali@example.com',
            'contact_phone' => '+923001234567',
            'passengers' => [$this->passengerPayload()],
        ])->assertRedirect();

        $booking = GroupBooking::query()->firstOrFail();
        $this->assertSame(GroupBookingStatus::PendingPassengerDetails, $booking->status);
        $this->assertNull($booking->expires_at);

        $this->post(route('group-ticketing.booking.review.confirm', $booking))
            ->assertRedirect(route('group-ticketing.booking.payment', $booking));

        $booking->refresh();
        $this->assertSame(GroupBookingStatus::ReservedAwaitingPayment, $booking->status);
        $this->assertNotNull($booking->expires_at);
        $this->assertNotNull($booking->reservation_created_at);
        $this->assertTrue($booking->expires_at->greaterThan(now()->addMinutes(24)));
    }

    public function test_passengers_page_shows_booking_preview(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->get(route('group-ticketing.booking.passengers', $inventory))
            ->assertOk()
            ->assertSee('Complete your booking', false)
            ->assertSee('Group Ticketing', false)
            ->assertSee('Booking summary', false)
            ->assertSee('PKR', false);
    }
}
