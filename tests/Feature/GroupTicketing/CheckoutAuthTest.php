<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutAuthTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '1',
            'public_id' => 'ALH-1',
            'title' => 'Auth Test Group',
            'sector' => 'LHE-MCT',
            'total_seats' => 5,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 100000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_passengers_checkout(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();

        $this->get(route('group-ticketing.booking.passengers', $inventory))
            ->assertRedirect(route('login'));

        $this->assertSame(0, GroupBooking::query()->count());
    }

    public function test_authenticated_user_can_access_passengers_checkout(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();

        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->get(route('group-ticketing.booking.passengers', $inventory))
            ->assertOk()
            ->assertSee('Passenger details', false);
    }

    public function test_guest_login_with_group_redirect_returns_to_passengers_checkout(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();
        $user = User::factory()->create([
            'account_type' => AccountType::Customer,
            'email_verified_at' => now(),
        ]);

        $passengersPath = route('group-ticketing.booking.passengers', $inventory, false);

        $this->get(route('login', [
            'redirect' => $passengersPath,
            'checkout_return' => $passengersPath,
        ]))
            ->assertOk()
            ->assertSee('Please log in or create an account to book this group ticket.', false);

        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect($passengersPath);

        $this->assertSame(0, GroupBooking::query()->count());
    }
}
