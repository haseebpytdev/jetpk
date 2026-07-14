<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\GroupTicketing\GroupReservationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_reservation_releases_held_seats(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '5',
            'public_id' => 'ALH-5',
            'title' => 'Expiry Test',
            'total_seats' => 10,
            'held_seats' => 2,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['account_type' => AccountType::Customer]);

        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-2026-TEST01',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::ReservedAwaitingPayment,
            'seat_count' => 2,
            'total_amount' => 100000,
            'currency' => 'PKR',
            'expires_at' => now()->subMinute(),
        ]);

        app(GroupReservationService::class)->releaseExpired($booking);

        $booking->refresh();
        $inventory->refresh();

        $this->assertSame(GroupBookingStatus::Released, $booking->status);
        $this->assertSame(0, $inventory->held_seats);
    }
}
