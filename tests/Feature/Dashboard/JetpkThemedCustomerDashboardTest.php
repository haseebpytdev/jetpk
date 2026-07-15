<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkThemedCustomerDashboardTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('client_route_parity.enabled', false);
    }

    public function test_jetpk_themed_customer_dashboard_resolves_with_portal_shell(): void
    {
        [$customer] = $this->jetpkCustomerWithBookings();

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="jp-customer-dashboard"', false)
            ->assertSee('data-testid="jp-customer-dashboard-kpis"', false)
            ->assertSee('class="jp-portal__top"', false);
    }

    public function test_jetpk_themed_dashboard_renders_kpis_and_support_meta(): void
    {
        [$customer] = $this->jetpkCustomerWithBookings();

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Total bookings', false)
            ->assertSee('Pending payment', false)
            ->assertSee('PNR confirmed', false)
            ->assertSee('Cancellation activity', false);
    }

    public function test_jetpk_themed_dashboard_shows_pending_payment_banner(): void
    {
        [$customer] = $this->jetpkCustomerWithBookings(['payment_status' => 'unpaid']);

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="jp-customer-dashboard-pending-alert"', false)
            ->assertSee('Payment pending', false);
    }

    public function test_jetpk_themed_dashboard_shows_upcoming_and_recent_when_seeded(): void
    {
        [$customer, $booking] = $this->jetpkCustomerWithBookings([
            'travel_date' => now()->addDays(12)->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="jp-customer-dashboard-upcoming"', false)
            ->assertSee($booking->display_reference, false)
            ->assertSee('data-testid="jp-customer-recent-bookings"', false);
    }

    public function test_jetpk_themed_dashboard_empty_state_when_no_bookings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->makeJetpkProfile();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('No bookings yet', false)
            ->assertDontSee('data-testid="jp-customer-recent-bookings"', false);
    }

    public function test_jetpk_themed_bookings_index_preserves_filters_and_payment_state(): void
    {
        [$customer] = $this->jetpkCustomerWithBookings(['payment_status' => 'unpaid']);
        [, $paid] = $this->jetpkCustomerWithBookings([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($customer)->get(route('customer.bookings.index', ['filter' => 'pending_payment']))
            ->assertOk()
            ->assertSee('data-testid="customer-bookings-filters"', false)
            ->assertDontSee($paid->booking_reference, false);
    }

    public function test_jetpk_themed_customer_cannot_access_another_customers_booking(): void
    {
        [, $booking] = $this->jetpkCustomerWithBookings();
        [$other] = $this->jetpkCustomerWithBookings();

        $this->actingAs($other)->get(route('customer.bookings.show', $booking))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1?: Booking}
     */
    private function jetpkCustomerWithBookings(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->makeJetpkProfile();
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
