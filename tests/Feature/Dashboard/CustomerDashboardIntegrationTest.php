<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_dashboard_renders_kpis_pending_payment_and_empty_state(): void
    {
        [$customer] = $this->customerWithBookings();

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="customer-dashboard"', false)
            ->assertSee('data-testid="customer-dashboard-kpis"', false)
            ->assertSee('Total bookings', false)
            ->assertSee('Pending payment', false);
    }

    public function test_customer_dashboard_shows_upcoming_and_recent_when_seeded(): void
    {
        [$customer, $booking] = $this->customerWithBookings([
            'travel_date' => now()->addDays(10)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="customer-dashboard-upcoming"', false)
            ->assertSee($booking->display_reference, false)
            ->assertSee('data-testid="customer-recent-bookings"', false);
    }

    public function test_customer_bookings_index_preserves_filters_and_payment_state(): void
    {
        [$customer] = $this->customerWithBookings(['payment_status' => 'unpaid']);
        [, $paid] = $this->customerWithBookings([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($customer)->get(route('customer.bookings.index', ['filter' => 'pending_payment']))
            ->assertOk()
            ->assertSee('data-testid="customer-bookings-filters"', false)
            ->assertSee('ota-bstat', false)
            ->assertDontSee($paid->booking_reference, false);
    }

    public function test_customer_cannot_access_another_customers_booking(): void
    {
        [, $booking] = $this->customerWithBookings();
        [$other] = $this->customerWithBookings();

        $this->actingAs($other)->get(route('customer.bookings.show', $booking))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1?: Booking}
     */
    private function customerWithBookings(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
            'travel_date' => now()->addDays(5)->toDateString(),
        ], $overrides));
        $booking->contact()->create([
            'email' => 'customer@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
        ]);

        return [$customer, $booking->fresh(['contact'])];
    }
}
