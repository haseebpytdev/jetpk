<?php

namespace Tests\Unit\Suppliers\AlHaider;

use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupBookingPassenger;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\Suppliers\AlHaider\AlHaiderGroupBookingPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlHaiderGroupBookingPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_official_create_booking_shape_for_single_adult(): void
    {
        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '3348',
            'public_id' => 'ALH-3348',
            'title' => 'Test',
            'sector' => 'LYP-SHJ',
            'total_seats' => 5,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 70000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-PAYLOAD-TEST',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PendingPassengerDetails,
            'seat_count' => 1,
            'total_amount' => 70000,
            'currency' => 'PKR',
            'contact_name' => 'Seat Sync QA',
            'contact_email' => 'qa@example.com',
            'contact_phone' => '+923001111111',
        ]);

        GroupBookingPassenger::query()->create([
            'group_booking_id' => $booking->id,
            'title' => 'Mr',
            'first_name' => 'Seat',
            'last_name' => 'SyncQA',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'passport_number' => 'QA1234567',
            'passport_expiry' => '2030-01-01',
            'passenger_type' => 'adult',
            'sort_order' => 0,
        ]);

        $payload = app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking->fresh('passengers'), $inventory);

        $this->assertSame(3348, $payload['group_id']);
        $this->assertSame(1, $payload['agency_info']['adults']);
        $this->assertSame(0, $payload['agency_info']['child']);
        $this->assertSame('GRP-PAYLOAD-TEST', $payload['agency_info']['agent_notes']);
        $this->assertCount(1, $payload['booking_details']);
        $this->assertSame('Adult', $payload['booking_details'][0]['type']);
        $this->assertSame('MR', $payload['booking_details'][0]['title']);
        $this->assertSame('SyncQA', $payload['booking_details'][0]['surname']);
    }
}
