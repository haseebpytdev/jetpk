<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupBookingUserRestriction;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\GroupTicketing\GroupBookingRestrictionService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreeStrikeBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_unpaid_releases_blocks_group_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'blk-1',
            'public_id' => 'ALH-BLK-1',
            'title' => 'Block Test',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $service = app(GroupBookingRestrictionService::class);
        for ($i = 0; $i < 3; $i++) {
            $booking = GroupBooking::query()->create([
                'reference' => 'GRP-BLK-00'.$i,
                'user_id' => $user->id,
                'group_inventory_id' => $inventory->id,
                'status' => GroupBookingStatus::Released,
                'seat_count' => 1,
                'total_amount' => 50000,
                'currency' => 'PKR',
                'released_at' => now(),
                'release_reason' => 'unpaid_timeout',
            ]);
            $service->recordUnpaidRelease($user, $booking);
        }

        $this->assertTrue($service->isBlocked($user));

        $this->actingAs($user);
        $this->get(route('group-ticketing.booking.passengers', $inventory))
            ->assertRedirect(route('group-ticketing.search'));

        $this->get(route('group-ticketing.search'))->assertOk();
    }

    public function test_admin_reset_allows_booking_again(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'blk-2',
            'public_id' => 'ALH-BLK-2',
            'title' => 'Reset Test',
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        GroupBookingUserRestriction::query()->create([
            'user_id' => $user->id,
            'unpaid_release_count' => 3,
            'blocked_at' => now(),
        ]);

        $this->actingAs($admin);
        $this->post(route('admin.group-bookings.restrictions.reset', $user), [
            'reset_note' => 'Customer contacted support',
        ])->assertRedirect();

        $this->actingAs($user);
        $this->get(route('group-ticketing.booking.passengers', $inventory))
            ->assertOk();
    }
}
