<?php

namespace Tests\Unit\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\Dashboard\BookingOperationalCapabilitiesPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingOperationalCapabilitiesPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_booking_exposes_record_payment_and_not_issue_ticket_by_default(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'pnr' => null,
            'supplier_booking_status' => 'not_started',
            'amount_paid' => 0,
        ]);

        $caps = (new BookingOperationalCapabilitiesPresenter)->present($admin, $booking);

        $this->assertTrue($caps['can_record_payment']);
        $this->assertTrue($caps['can_admin_mark_paid'] || $caps['can_record_payment']);
        $this->assertTrue($caps['can_generate_pnr'] || $caps['can_retry_pnr']);
        $this->assertArrayHasKey('sabre_void_support', $caps);
        $this->assertArrayHasKey('reasons', $caps);
    }

    public function test_cancelled_booking_blocks_destructive_capabilities(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Cancelled,
            'payment_status' => 'paid',
            'pnr' => 'ABC123',
            'amount_paid' => 1000,
        ]);

        $caps = (new BookingOperationalCapabilitiesPresenter)->present($admin, $booking);

        $this->assertFalse($caps['can_generate_pnr']);
        $this->assertFalse($caps['can_retry_pnr']);
        $this->assertFalse($caps['can_record_payment']);
        $this->assertFalse($caps['can_request_cancellation']);
        $this->assertFalse($caps['can_cancel_supplier_booking']);
    }
}
