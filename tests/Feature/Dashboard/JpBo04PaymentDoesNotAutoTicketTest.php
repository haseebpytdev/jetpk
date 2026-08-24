<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use App\Services\Suppliers\TicketingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class JpBo04PaymentDoesNotAutoTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_and_verifying_payment_does_not_invoke_ticketing_service(): void
    {
        $this->mock(TicketingService::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->never();
        });

        $agency = Agency::factory()->create();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
        ]);

        /** @var BookingPaymentService $payments */
        $payments = app(BookingPaymentService::class);
        $payment = $payments->recordManualPayment($booking, $admin, [
            'method' => BookingPaymentMethod::BankTransfer->value,
            'amount' => 1000,
            'currency' => 'PKR',
            'notes' => 'JPQA-BO04 no-auto-ticket',
            'payment_reference' => 'JPQA-BO04-PAY',
            'admin_override' => true,
            'verify_now' => true,
        ]);

        $this->assertSame(BookingPaymentStatus::Verified, $payment->status);
        $booking->refresh();
        $this->assertNotSame('ticketed', (string) ($booking->ticketing_status ?? ''));
        Mockery::close();
    }
}
