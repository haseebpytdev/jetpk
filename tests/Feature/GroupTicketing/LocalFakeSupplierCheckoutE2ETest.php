<?php

namespace Tests\Feature\GroupTicketing;

use App\Enums\AccountType;
use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Local persistence round-trip with Al-Haider booking gate OFF (no supplier mutation).
 *
 * Covers: auth gate → login resume → passengers (2 seats) → review hold →
 * manual payment → admin verify → confirmation/IDOR.
 */
class LocalFakeSupplierCheckoutE2ETest extends TestCase
{
    use PlatformAdminTestHelpers;
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
            'suppliers.al_haider.enabled' => false,
            'suppliers.al_haider.booking_enabled' => false,
        ]);
    }

    private function inventory(int $seats = 5): GroupInventory
    {
        return GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'e2e-3278',
            'public_id' => 'ALH-E2E-3278',
            'title' => 'E2E UAE — ISB-DXB',
            'sector' => 'ISB-DXB',
            'airline_name' => 'AIR SIAL',
            'total_seats' => $seats,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 70000,
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
            'first_name' => 'Test'.$index,
            'last_name' => 'Passenger',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'nationality' => 'Pakistani',
            'document_type' => 'passport',
            'passport_number' => 'E2E'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
            'passport_issue_date' => '2020-01-01',
            'passport_expiry' => '2030-01-01',
            'passenger_type' => 'adult',
        ];
    }

    public function test_full_local_checkout_manual_payment_with_booking_gate_off(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Storage::fake('public');

        $inventory = $this->inventory(5);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'email_verified_at' => now(),
        ]);
        $admin = $this->platformAdmin();
        $other = User::factory()->create(['account_type' => AccountType::Customer]);

        $passengersPath = '/groups/'.$inventory->public_id.'/passengers';

        // Anonymous blocked
        $this->getJson($passengersPath.'?format=json')->assertUnauthorized();
        $this->assertSame(0, GroupBooking::query()->count());

        // JSON login resumes checkout intent
        $this->postJson(route('login'), [
            'login' => $customer->email,
            'password' => 'password',
            'redirect' => $passengersPath,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect', $passengersPath);

        $this->actingAs($customer);

        $this->getJson($passengersPath.'?format=json')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Price/seat authority: reject overbook
        $this->postJson(route('group-ticketing.booking.passengers.store', $inventory->public_id), [
            'seat_count' => 9,
            'quoted_unit_price' => 70000,
            'contact_name' => 'Test Customer',
            'contact_email' => $customer->email,
            'contact_phone' => '+923001111111',
            'passengers' => array_map(fn (int $i) => $this->passengerPayload($i), range(0, 8)),
        ])->assertStatus(422);

        // Stale price requires reconfirm
        $this->postJson(route('group-ticketing.booking.passengers.store', $inventory->public_id), [
            'seat_count' => 2,
            'quoted_unit_price' => 65000,
            'contact_name' => 'Test Customer',
            'contact_email' => $customer->email,
            'contact_phone' => '+923001111111',
            'passengers' => [$this->passengerPayload(0), $this->passengerPayload(1)],
        ])
            ->assertStatus(409)
            ->assertJsonPath('status', 'price_changed');

        $create = $this->postJson(route('group-ticketing.booking.passengers.store', $inventory->public_id), [
            'seat_count' => 2,
            'quoted_unit_price' => 70000,
            'contact_name' => 'Test Customer',
            'contact_email' => $customer->email,
            'contact_phone' => '+923001111111',
            'passengers' => [$this->passengerPayload(0), $this->passengerPayload(1)],
        ]);

        $create->assertOk()->assertJsonPath('success', true);
        $bookingRef = (string) $create->json('booking.reference');
        $this->assertNotSame('', $bookingRef);

        $booking = GroupBooking::query()->where('reference', $bookingRef)->firstOrFail();
        $this->assertSame(GroupBookingStatus::PendingPassengerDetails, $booking->status);
        $this->assertSame(2, (int) $booking->seat_count);
        $this->assertEquals(140000.0, (float) $booking->total_amount);
        $this->assertNull($booking->supplier_reservation_id);

        // IDOR: other customer cannot open review
        $this->actingAs($other);
        $this->getJson('/groups/booking/'.$bookingRef.'/review?format=json')
            ->assertStatus(403);

        $this->actingAs($customer);
        $this->postJson(route('group-ticketing.booking.review.confirm', $booking))
            ->assertOk();

        $booking->refresh();
        $this->assertSame(GroupBookingStatus::ReservedAwaitingPayment, $booking->status);
        $this->assertNotNull($booking->expires_at);
        $this->assertNull($booking->supplier_reservation_id);

        $inventory->refresh();
        $this->assertSame(2, (int) $inventory->held_seats);

        // Manual payment
        $this->post(route('group-ticketing.booking.payment.submit', $booking), [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'E2E-TXN-001',
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 80, 'application/pdf'),
        ])->assertRedirect(route('group-ticketing.booking.confirmation', $booking));

        $booking->refresh();
        $this->assertSame(GroupBookingStatus::ManualPaymentPendingReview, $booking->status);

        // Idempotent: second submit must not invent a second booking
        $before = $booking->payment_reference;
        $this->post(route('group-ticketing.booking.payment.submit', $booking), [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'E2E-TXN-001-DUP',
        ]);
        $this->assertSame(1, GroupBooking::query()->count());
        $booking->refresh();
        $this->assertSame(GroupBookingStatus::ManualPaymentPendingReview, $booking->status);
        $this->assertSame($before, $booking->payment_reference);

        // Admin confirms payment (no supplier call with gate OFF)
        $this->actingAs($admin);
        $this->post(route('admin.group-bookings.verify-payment', $booking))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame(GroupBookingStatus::Confirmed, $booking->status);
        $this->assertNotNull($booking->admin_payment_verified_at);
        $this->assertSame($admin->id, (int) $booking->admin_payment_verified_by);
        $this->assertNull($booking->supplier_reservation_id);

        $this->actingAs($customer);
        $this->getJson('/groups/booking/'.$bookingRef.'/confirmation?format=json')
            ->assertOk()
            ->assertJsonPath('reference', $bookingRef)
            ->assertJsonPath('success', true);
    }
}
