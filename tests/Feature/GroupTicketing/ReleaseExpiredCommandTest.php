<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReleaseExpiredCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_expired_command_releases_seats(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'rel-1',
            'public_id' => 'ALH-REL-1',
            'title' => 'Release Test',
            'total_seats' => 10,
            'held_seats' => 2,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['account_type' => AccountType::Customer]);

        GroupBooking::query()->create([
            'reference' => 'GRP-REL-001',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PaymentPending,
            'seat_count' => 2,
            'total_amount' => 100000,
            'currency' => 'PKR',
            'reservation_created_at' => now()->subMinutes(30),
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('group-ticketing:release-expired');

        $inventory->refresh();
        $booking = GroupBooking::query()->where('reference', 'GRP-REL-001')->firstOrFail();

        $this->assertSame(0, $inventory->held_seats);
        $this->assertSame(GroupBookingStatus::Released, $booking->status);
        $this->assertNotNull($booking->released_at);
        $this->assertSame('unpaid_timeout', $booking->release_reason);
    }
}
