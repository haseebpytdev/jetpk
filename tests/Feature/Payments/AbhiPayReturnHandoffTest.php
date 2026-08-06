<?php

namespace Tests\Feature;

use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbhiPayReturnHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_success_view_includes_next_return_handoff_urls(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $booking = Booking::factory()->create([
            'booking_reference' => 'JP01DTEST1',
        ]);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'client_transaction_id' => 'txn-handoff-01d',
            'gateway_order_id' => 'ORD-01D',
            'amount' => 50000,
            'currency' => 'PKR',
            'status' => PaymentTransactionStatus::Paid,
            'gateway_payment_url' => 'https://pay.abhipay.com.pk/checkout/ORD-01D',
        ]);

        $this->get(route('payments.success', [
            'reference' => $transaction->client_transaction_id,
            'booking' => $booking->booking_reference,
        ]))
            ->assertOk()
            ->assertSee('Continue in JetPakistan', false)
            ->assertSee('/booking/payment/return', false)
            ->assertSee('reference=txn-handoff-01d', false)
            ->assertSee('View booking confirmation', false)
            ->assertSee('/booking/confirmation', false);
    }

    public function test_payment_cancel_view_includes_status_handoff_not_confirmation_primary(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $booking = Booking::factory()->create([
            'booking_reference' => 'JP01DCANCEL',
        ]);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::CODE_ABHIPAY,
            'environment' => 'test',
            'client_transaction_id' => 'txn-cancel-01d',
            'amount' => 50000,
            'currency' => 'PKR',
            'status' => PaymentTransactionStatus::Cancelled,
        ]);

        $this->get(route('payments.cancel', [
            'reference' => $transaction->client_transaction_id,
        ]))
            ->assertOk()
            ->assertSee('Check payment status', false)
            ->assertSee('/booking/payment/status', false)
            ->assertDontSee('View booking confirmation', false);
    }
}
