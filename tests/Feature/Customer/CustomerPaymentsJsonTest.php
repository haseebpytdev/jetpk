<?php

namespace Tests\Feature\Customer;

use App\Enums\AccountType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPaymentsJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receives_only_own_payment_rows(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$customer, $booking] = $this->customerWithBooking();
        [$other] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->getJson(route('customer.payments.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['booking_reference' => $booking->booking_reference]);

        $this->actingAs($other)
            ->getJson(route('customer.payments.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonMissing(['booking_reference' => $booking->booking_reference]);
    }

    public function test_guest_is_rejected_from_customer_payments_json(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->getJson(route('customer.payments.index', ['format' => 'json']))
            ->assertUnauthorized();
    }

    protected function customerWithBooking(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Confirmed,
            'payment_status' => 'paid',
            'booking_reference' => 'CPAY-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
        ]);

        $booking->payments()->create([
            'agency_id' => $agency->id,
            'amount' => 45000,
            'currency' => 'PKR',
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingPaymentStatus::Verified,
            'payment_reference' => 'PAY-'.fake()->unique()->numerify('######'),
        ]);

        return [$customer, $booking->fresh()];
    }
}
