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
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function reservedBooking(User $user): GroupBooking
    {
        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'pay-1',
            'public_id' => 'ALH-PAY-1',
            'title' => 'Pay Test',
            'total_seats' => 5,
            'held_seats' => 1,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        return GroupBooking::query()->create([
            'reference' => 'GRP-TEST-PAY01',
            'user_id' => $user->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::PaymentPending,
            'seat_count' => 1,
            'total_amount' => 50000,
            'currency' => 'PKR',
            'reservation_created_at' => now(),
            'expires_at' => now()->addMinutes(25),
        ]);
    }

    public function test_manual_payment_submission_changes_status(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Storage::fake('public');
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $booking = $this->reservedBooking($user);
        $this->actingAs($user);

        $this->post(route('group-ticketing.booking.payment.submit', $booking), [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TXN123456',
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('group-ticketing.booking.confirmation', $booking));

        $booking->refresh();
        $this->assertSame(GroupBookingStatus::ManualPaymentPendingReview, $booking->status);
        $this->assertNotNull($booking->payment_submitted_at);
        $this->assertSame('bank_transfer', $booking->payment_method);
    }

    public function test_admin_verify_payment_confirms_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::factory()->create(['account_type' => AccountType::Customer]);
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $booking = $this->reservedBooking($user);
        $booking->update([
            'status' => GroupBookingStatus::ManualPaymentPendingReview,
            'payment_submitted_at' => now(),
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TXN1',
        ]);

        $this->actingAs($admin);
        $this->post(route('admin.group-bookings.verify-payment', $booking))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame(GroupBookingStatus::Confirmed, $booking->status);
    }
}
