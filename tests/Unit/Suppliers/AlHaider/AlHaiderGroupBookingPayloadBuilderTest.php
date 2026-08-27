<?php

namespace Tests\Unit\Suppliers\AlHaider;

use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupBookingPassenger;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\Suppliers\AlHaider\AlHaiderGroupBookingPayloadBuilder;
use App\Services\Suppliers\AlHaider\AlHaiderGroupBookingPayloadException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlHaiderGroupBookingPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(int $totalSeats = 5): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '3348',
            'public_id' => 'ALH-3348',
            'title' => 'Test',
            'sector' => 'LYP-SHJ',
            'total_seats' => $totalSeats,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 70000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array{type?: string, first?: string, last?: string, passport?: string|null, dob?: string|null, doe?: string|null}>  $passengers
     */
    private function booking(GroupInventory $inventory, int $seatCount, array $passengers, bool $withContact = true): GroupBooking
    {
        $user = User::factory()->create();
        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-PAYLOAD-TEST',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PendingPassengerDetails,
            'seat_count' => $seatCount,
            'total_amount' => 70000 * $seatCount,
            'currency' => 'PKR',
            'contact_name' => $withContact ? 'Seat Sync QA' : null,
            'contact_email' => $withContact ? 'qa@example.com' : null,
            'contact_phone' => $withContact ? '+923001111111' : null,
        ]);

        foreach ($passengers as $index => $row) {
            GroupBookingPassenger::query()->create([
                'group_booking_id' => $booking->id,
                'title' => 'Mr',
                'first_name' => $row['first'] ?? ('Seat'.$index),
                'last_name' => $row['last'] ?? 'SyncQA',
                'gender' => 'male',
                'date_of_birth' => array_key_exists('dob', $row) ? $row['dob'] : '1990-01-15',
                'passport_number' => array_key_exists('passport', $row) ? $row['passport'] : ('QA'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT)),
                'passport_expiry' => array_key_exists('doe', $row) ? $row['doe'] : '2030-01-01',
                'passenger_type' => $row['type'] ?? 'adult',
                'sort_order' => $index,
            ]);
        }

        return $booking->fresh('passengers');
    }

    public function test_builds_official_create_booking_shape_for_single_adult(): void
    {
        $inventory = $this->inventory();
        $booking = $this->booking($inventory, 1, [['type' => 'adult']]);

        $payload = app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking, $inventory);

        $this->assertSame(3348, $payload['group_id']);
        $this->assertSame(1, $payload['agency_info']['adults']);
        $this->assertSame(0, $payload['agency_info']['child']);
        $this->assertSame(0, $payload['agency_info']['infant']);
        $this->assertSame('GRP-PAYLOAD-TEST', $payload['agency_info']['agent_notes']);
        $this->assertSame('qa@example.com', $payload['agency_info']['email']);
        $this->assertSame('+923001111111', $payload['agency_info']['mobile']);
        $this->assertCount(1, $payload['booking_details']);
        $this->assertSame('Adult', $payload['booking_details'][0]['type']);
        $this->assertSame('MR', $payload['booking_details'][0]['title']);
        $this->assertSame('SyncQA', $payload['booking_details'][0]['surname']);
        $this->assertStringNotContainsString('Guest', json_encode($payload));
        $this->assertStringNotContainsString('Passenger', json_encode($payload['booking_details']));
    }

    public function test_builds_five_seat_adult_payload(): void
    {
        $inventory = $this->inventory(5);
        $passengers = array_fill(0, 5, ['type' => 'adult']);
        $booking = $this->booking($inventory, 5, $passengers);

        $payload = app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking, $inventory);

        $this->assertSame(5, $payload['agency_info']['adults']);
        $this->assertCount(5, $payload['booking_details']);
    }

    public function test_builds_all_available_seats_payload(): void
    {
        $inventory = $this->inventory(5);
        $passengers = array_fill(0, 5, ['type' => 'adult']);
        $booking = $this->booking($inventory, 5, $passengers);

        $payload = app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking, $inventory);

        $this->assertSame(5, $payload['agency_info']['adults'] + $payload['agency_info']['child']);
        $this->assertCount(5, $payload['booking_details']);
    }

    public function test_adult_child_combination_parity(): void
    {
        $inventory = $this->inventory(5);
        $booking = $this->booking($inventory, 3, [
            ['type' => 'adult'],
            ['type' => 'adult'],
            ['type' => 'child'],
        ]);

        $payload = app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking, $inventory);

        $this->assertSame(2, $payload['agency_info']['adults']);
        $this->assertSame(1, $payload['agency_info']['child']);
        $this->assertCount(3, $payload['booking_details']);
    }

    public function test_infant_does_not_consume_seat(): void
    {
        $inventory = $this->inventory(5);
        $booking = $this->booking($inventory, 1, [
            ['type' => 'adult'],
            ['type' => 'infant'],
        ]);

        $payload = app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking, $inventory);

        $this->assertSame(1, $payload['agency_info']['adults']);
        $this->assertSame(1, $payload['agency_info']['infant']);
        $this->assertCount(2, $payload['booking_details']);
    }

    public function test_missing_passport_fails_closed_without_synthetic_data(): void
    {
        $inventory = $this->inventory();
        $booking = $this->booking($inventory, 1, [
            ['type' => 'adult', 'passport' => ''],
        ]);

        $this->expectException(AlHaiderGroupBookingPayloadException::class);
        $this->expectExceptionMessage('passport');

        app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking, $inventory);
    }

    public function test_seat_count_mismatch_fails_closed(): void
    {
        $inventory = $this->inventory();
        $booking = $this->booking($inventory, 2, [
            ['type' => 'adult'],
        ]);

        $this->expectException(AlHaiderGroupBookingPayloadException::class);

        app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking, $inventory);
    }

    public function test_missing_contact_fails_closed(): void
    {
        $inventory = $this->inventory();
        $booking = $this->booking($inventory, 1, [['type' => 'adult']], withContact: false);

        $this->expectException(AlHaiderGroupBookingPayloadException::class);
        $this->expectExceptionMessage('Contact');

        app(AlHaiderGroupBookingPayloadBuilder::class)->build($booking, $inventory);
    }
}
