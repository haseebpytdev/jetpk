<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Services\Dashboard\Api\DashboardPaymentsReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardPaymentsTxnSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_resolves_txn_prefixed_public_id(): void
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);
        $booking = Booking::factory()->create(['agency_id' => $agency->id]);
        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'payment_reference' => 'TXN-SEARCH-TEST',
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingPaymentStatus::Submitted,
            'amount' => 1000,
            'currency' => 'PKR',
        ]);

        $service = app(DashboardPaymentsReadService::class);
        $request = Request::create('/', 'GET', ['q' => 'TXN-'.$payment->id, 'pageSize' => 1]);
        $request->setUserResolver(fn () => $admin);

        $result = $service->paginate($admin, $request);

        $this->assertCount(1, $result['items']);
        $this->assertSame('TXN-'.$payment->id, $result['items'][0]['transactionId']);
    }
}
