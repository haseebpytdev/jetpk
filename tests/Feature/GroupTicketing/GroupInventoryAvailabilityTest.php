<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\GroupTicketing\GroupInventoryAvailabilityService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupInventoryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ota.group_ticketing.inventory_search_sync_enabled' => false,
            'ota.group_ticketing.realtime_search_enabled' => false,
            'ota.group_ticketing.require_live_provider_for_public_results' => false,
            'ota.group_ticketing.require_live_provider_for_reservation' => false,
            'suppliers.al_haider.enabled' => false,
        ]);
    }

    private function inventory(int $seats = 5, bool $active = true): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'avail-1',
            'public_id' => 'ALH-AVAIL-1',
            'title' => 'Availability Test',
            'sector' => 'SKT-SHJ',
            'total_seats' => $seats,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => $active,
            'synced_at' => now(),
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

    public function test_passengers_get_redirects_when_package_unavailable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory(0, false);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->get(route('group-ticketing.booking.passengers', $inventory))
            ->assertRedirect(route('group-ticketing.search'))
            ->assertSessionHas('warning', GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE);
    }

    public function test_passenger_submit_blocks_insufficient_seats_with_exact_message(): void
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
        ])->assertSessionHasErrors([
            'seat_count' => GroupInventoryAvailabilityService::insufficientSeatsMessage(2),
        ]);
    }

    public function test_review_confirm_blocks_when_seats_no_longer_available(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory(3);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->post(route('group-ticketing.booking.passengers.store', $inventory), [
            'seat_count' => 2,
            'contact_name' => 'Ali Khan',
            'contact_email' => 'ali@example.com',
            'contact_phone' => '+923001234567',
            'passengers' => [$this->passengerPayload(0), $this->passengerPayload(1)],
        ])->assertRedirect();

        $booking = GroupBooking::query()->firstOrFail();
        $inventory->update(['held_seats' => 2]);

        $this->post(route('group-ticketing.booking.review.confirm', $booking))
            ->assertSessionHasErrors([
                'reservation' => GroupInventoryAvailabilityService::insufficientSeatsMessage(1),
            ]);

        $booking->refresh();
        $this->assertSame(GroupBookingStatus::PendingPassengerDetails, $booking->status);
    }
}
