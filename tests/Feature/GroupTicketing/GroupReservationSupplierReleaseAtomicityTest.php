<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupBookingPassenger;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\GroupTicketing\GroupReservationService;
use App\Services\Suppliers\AlHaider\AlHaiderClient;
use App\Services\Suppliers\AlHaider\AlHaiderProviderException;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Regression matrix for Al-Haider create/cancel gates and release atomicity.
 * No live supplier mutations — AlHaiderClient is mocked.
 */
class GroupReservationSupplierReleaseAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.al_haider.enabled' => false,
            'suppliers.al_haider.booking_enabled' => false,
            'suppliers.al_haider.cancel_enabled' => false,
            'ota.group_ticketing.require_live_provider_for_reservation' => false,
            'ota.group_ticketing.block_booking_when_provider_unavailable' => false,
            'ota.group_ticketing.require_live_provider_for_public_results' => false,
        ]);
    }

    private function inventory(int $held = 1, int $total = 5): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => '3348',
            'public_id' => 'ALH-3348-ATOM',
            'title' => 'Atomicity Test',
            'sector' => 'LYP-SHJ',
            'total_seats' => $total,
            'held_seats' => $held,
            'sold_seats' => 0,
            'price' => 70000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }

    private function heldBooking(GroupInventory $inventory, ?string $supplierReservationId = '60175'): GroupBooking
    {
        $user = User::factory()->create(['account_type' => AccountType::Customer]);

        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-ATOM-001',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::ReservedAwaitingPayment,
            'seat_count' => 1,
            'total_amount' => 70000,
            'currency' => 'PKR',
            'contact_name' => 'QA',
            'contact_email' => 'qa@example.com',
            'contact_phone' => '+923001111111',
            'reservation_created_at' => now()->subMinutes(30),
            'expires_at' => now()->subMinute(),
            'supplier_reservation_id' => $supplierReservationId,
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

        return $booking->fresh('passengers');
    }

    public function test_supplier_cancel_success_decrements_local_held_seats(): void
    {
        config(['suppliers.al_haider.cancel_enabled' => true]);

        $inventory = $this->inventory(held: 1);
        $booking = $this->heldBooking($inventory);

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('cancelReservation')
            ->once()
            ->with('60175', Mockery::type('array'))
            ->andReturn(['ok' => true]);
        $this->app->instance(AlHaiderClient::class, $client);

        $result = app(GroupReservationService::class)->releaseUnpaidBooking($booking, 'unpaid_timeout');

        $inventory->refresh();
        $this->assertSame(GroupBookingStatus::Released, $result->status);
        $this->assertNotNull($result->released_at);
        $this->assertNotNull($result->supplier_released_at);
        $this->assertSame(0, $inventory->held_seats);
        $this->assertSame('60175', $result->supplier_reservation_id);
    }

    public function test_supplier_cancel_403_keeps_local_held_seats(): void
    {
        config(['suppliers.al_haider.cancel_enabled' => true]);

        $inventory = $this->inventory(held: 1);
        $booking = $this->heldBooking($inventory);
        $availableBefore = $inventory->availableSeats();

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('cancelReservation')
            ->once()
            ->andThrow(new AlHaiderProviderException('forbidden', 403, 'Cancel not permitted.'));
        $this->app->instance(AlHaiderClient::class, $client);

        $result = app(GroupReservationService::class)->releaseUnpaidBooking($booking, 'unpaid_timeout');

        $inventory->refresh();
        $this->assertSame(GroupBookingStatus::SupplierReleaseFailed, $result->status);
        $this->assertNull($result->released_at);
        $this->assertSame(1, $inventory->held_seats);
        $this->assertSame($availableBefore, $inventory->availableSeats());
        $this->assertSame('60175', $result->supplier_reservation_id);
        $this->assertSame('pending_supplier_release', $result->meta['reconciliation_state'] ?? null);
        $this->assertTrue($result->needsSupplierReleaseReconciliation());
        $panel = $result->supplierReleaseReconciliationPanel();
        $this->assertSame('GRP-ATOM-001', $panel['booking_reference']);
        $this->assertSame('60175', $panel['supplier_reservation_id']);
        $this->assertSame(1, $panel['requested_seats']);
        $this->assertNotNull($panel['failure_at']);
    }

    public function test_create_gate_off_cancel_gate_on_still_allows_cancel(): void
    {
        config([
            'suppliers.al_haider.booking_enabled' => false,
            'suppliers.al_haider.cancel_enabled' => true,
        ]);

        $inventory = $this->inventory(held: 1);
        $booking = $this->heldBooking($inventory);

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('cancelReservation')->once()->andReturn(['ok' => true]);
        $client->shouldNotReceive('reserveGroup');
        $this->app->instance(AlHaiderClient::class, $client);

        $result = app(GroupReservationService::class)->releaseUnpaidBooking($booking);

        $this->assertSame(GroupBookingStatus::Released, $result->status);
        $this->assertSame(0, $inventory->fresh()->held_seats);
    }

    public function test_create_gate_off_does_not_create_supplier_reservation(): void
    {
        config([
            'suppliers.al_haider.booking_enabled' => false,
            'suppliers.al_haider.cancel_enabled' => true,
        ]);

        $inventory = $this->inventory(held: 0);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-ATOM-CREATE-OFF',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PendingPassengerDetails,
            'seat_count' => 1,
            'total_amount' => 70000,
            'currency' => 'PKR',
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

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldNotReceive('reserveGroup');
        $this->app->instance(AlHaiderClient::class, $client);

        $result = app(GroupReservationService::class)->createReservation($booking->fresh('passengers'));

        $this->assertSame(GroupBookingStatus::ReservedAwaitingPayment, $result->status);
        $this->assertNull($result->supplier_reservation_id);
        $this->assertSame(1, $inventory->fresh()->held_seats);
    }

    public function test_missing_passport_fails_before_supplier_post(): void
    {
        config(['suppliers.al_haider.booking_enabled' => true]);

        $inventory = $this->inventory(held: 0);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-ATOM-NOPASS',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PendingPassengerDetails,
            'seat_count' => 1,
            'total_amount' => 70000,
            'currency' => 'PKR',
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
            'passport_number' => '',
            'passport_expiry' => '2030-01-01',
            'passenger_type' => 'adult',
            'sort_order' => 0,
        ]);

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldNotReceive('reserveGroup');
        $this->app->instance(AlHaiderClient::class, $client);

        try {
            app(GroupReservationService::class)->createReservation($booking->fresh('passengers'));
            $this->fail('Expected reservation to fail closed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('passport', strtolower($exception->getMessage()));
        }

        $this->assertSame(GroupBookingStatus::PendingPassengerDetails, $booking->fresh()->status);
        $this->assertSame(0, $inventory->fresh()->held_seats);
        $this->assertNull($booking->fresh()->supplier_reservation_id);
    }

    public function test_passenger_count_mismatch_fails_before_supplier_post(): void
    {
        config(['suppliers.al_haider.booking_enabled' => true]);

        $inventory = $this->inventory(held: 0);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-ATOM-MISMATCH',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PendingPassengerDetails,
            'seat_count' => 2,
            'total_amount' => 140000,
            'currency' => 'PKR',
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

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldNotReceive('reserveGroup');
        $this->app->instance(AlHaiderClient::class, $client);

        $this->expectException(\RuntimeException::class);
        app(GroupReservationService::class)->createReservation($booking->fresh('passengers'));
    }

    public function test_duplicate_create_does_not_call_supplier_again(): void
    {
        config(['suppliers.al_haider.booking_enabled' => true]);

        $inventory = $this->inventory(held: 1);
        $booking = $this->heldBooking($inventory, '60175');
        $booking->update(['status' => GroupBookingStatus::ReservedAwaitingPayment]);

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldNotReceive('reserveGroup');
        $this->app->instance(AlHaiderClient::class, $client);

        // Status is not PendingPassengerDetails — but supplier_reservation_id short-circuits first
        // when createReservation is called on an already-held booking that somehow re-enters.
        $booking->update(['status' => GroupBookingStatus::PendingPassengerDetails]);

        $result = app(GroupReservationService::class)->createReservation($booking->fresh('passengers'));

        $this->assertSame('60175', $result->supplier_reservation_id);
        $this->assertSame(1, $inventory->fresh()->held_seats);
    }

    public function test_cancel_gate_off_marks_supplier_release_failed_without_seat_release(): void
    {
        config([
            'suppliers.al_haider.booking_enabled' => false,
            'suppliers.al_haider.cancel_enabled' => false,
        ]);

        $inventory = $this->inventory(held: 1);
        $booking = $this->heldBooking($inventory);

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldNotReceive('cancelReservation');
        $this->app->instance(AlHaiderClient::class, $client);

        $result = app(GroupReservationService::class)->releaseUnpaidBooking($booking);

        $this->assertSame(GroupBookingStatus::SupplierReleaseFailed, $result->status);
        $this->assertSame(1, $inventory->fresh()->held_seats);
        $this->assertNull($result->released_at);
    }

    public function test_manual_supplier_cancel_reconcile_releases_local_seats_without_api(): void
    {
        $inventory = $this->inventory(held: 1);
        $booking = $this->heldBooking($inventory);
        $booking->update([
            'status' => GroupBookingStatus::SupplierReleaseFailed,
            'supplier_release_failed_at' => now(),
            'meta' => ['reconciliation_state' => 'pending_supplier_release'],
        ]);

        $client = Mockery::mock(AlHaiderClient::class);
        $client->shouldNotReceive('cancelReservation');
        $this->app->instance(AlHaiderClient::class, $client);

        $result = app(GroupReservationService::class)->reconcileAfterManualSupplierCancel($booking);

        $this->assertSame(GroupBookingStatus::Released, $result->status);
        $this->assertSame(0, $inventory->fresh()->held_seats);
        $this->assertSame('manual_supplier_cancel_reconciled', $result->meta['reconciliation_state'] ?? null);
        $this->assertSame('60175', $result->supplier_reservation_id);
    }
}
