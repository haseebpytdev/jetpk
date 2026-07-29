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

class GroupTicketingNextJsonContractTest extends TestCase
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
            'ota.group_ticketing.block_booking_when_provider_unavailable' => false,
        ]);
    }

    private function inventory(): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'json-1',
            'public_id' => 'ALH-JSON-1',
            'title' => 'JSON Contract Test',
            'sector' => 'SKT-SHJ',
            'total_seats' => 5,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 99000,
            'currency' => 'PKR',
            'is_active' => true,
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

    public function test_search_data_returns_cards_json(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();

        $this->getJson(route('group-ticketing.search.data', ['sector' => $inventory->sector]))
            ->assertOk()
            ->assertJsonPath('bookable', true)
            ->assertJsonStructure([
                'cards' => [
                    ['public_id', 'price_formatted', 'available_seats', 'route_line'],
                ],
                'lock_state' => ['locked', 'unpaid_release_count', 'block_threshold'],
            ]);
    }

    public function test_package_json_contract(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();

        $this->getJson('/groups/package/'.$inventory->public_id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('package.public_id', $inventory->public_id)
            ->assertJsonPath('package.booking_conditions.manual_payment_only', true);
    }

    public function test_passenger_submit_json_returns_review_redirect(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->postJson(route('group-ticketing.booking.passengers.store', $inventory->public_id), [
            'seat_count' => 1,
            'contact_name' => 'Ali Khan',
            'contact_email' => 'ali@example.com',
            'contact_phone' => '+923001234567',
            'passengers' => [$this->passengerPayload()],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['redirect_path', 'booking' => ['reference', 'status']]);

        $booking = GroupBooking::query()->firstOrFail();
        $this->assertSame(GroupBookingStatus::PendingPassengerDetails, $booking->status);
        $this->assertNull($booking->expires_at);
    }

    public function test_review_confirm_json_starts_hold(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $inventory = $this->inventory();
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($user);

        $this->post(route('group-ticketing.booking.passengers.store', $inventory->public_id), [
            'seat_count' => 1,
            'contact_name' => 'Ali Khan',
            'contact_email' => 'ali@example.com',
            'contact_phone' => '+923001234567',
            'passengers' => [$this->passengerPayload()],
        ])->assertRedirect();

        $booking = GroupBooking::query()->firstOrFail();

        $this->postJson(route('group-ticketing.booking.review.confirm', $booking->reference))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['redirect_path', 'booking' => ['expires_at', 'hold_minutes']]);

        $booking->refresh();
        $this->assertSame(GroupBookingStatus::ReservedAwaitingPayment, $booking->status);
        $this->assertNotNull($booking->expires_at);
    }

    public function test_booking_status_json_for_cross_session_rejection(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $owner = User::factory()->create(['account_type' => AccountType::Customer]);
        $other = User::factory()->create(['account_type' => AccountType::Customer]);
        $inventory = $this->inventory();

        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-JSON-CROSS',
            'user_id' => $owner->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PaymentPending,
            'seat_count' => 1,
            'total_amount' => 99000,
            'currency' => 'PKR',
            'expires_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($other);
        $this->getJson(route('group-ticketing.booking.status', $booking->reference))
            ->assertForbidden()
            ->assertJsonPath('status', 'forbidden');
    }
}
