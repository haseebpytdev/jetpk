<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Enums\SupportTicketMessageVisibility;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class CustomerDetailSupportNavigationTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('client_route_parity.enabled', false);
    }

    public function test_customer_can_open_own_booking_detail_with_presenter_sections(): void
    {
        [$customer, $booking] = $this->legacyCustomerWithBooking();

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee($booking->display_reference, false)
            ->assertSee('data-testid="customer-booking-detail-layout"', false);
    }

    public function test_customer_cannot_open_another_customers_booking_detail(): void
    {
        [, $booking] = $this->legacyCustomerWithBooking();
        [$other] = $this->legacyCustomerWithBooking();

        $this->actingAs($other)->get(route('customer.bookings.show', $booking))->assertForbidden();
    }

    public function test_jetpk_themed_booking_detail_resolves_with_breadcrumbs(): void
    {
        [$customer, $booking] = $this->jetpkCustomerWithBooking();

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('class="jp-portal__top"', false)
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-testid="customer-booking-detail-layout"', false);
    }

    public function test_support_ticket_index_create_and_show_render_breadcrumbs(): void
    {
        [$customer, $ticket] = $this->legacyCustomerWithTicket();

        $this->actingAs($customer)->get(route('customer.support.tickets.index'))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee('aria-current="page"', false);

        $this->actingAs($customer)->get(route('customer.support.tickets.create'))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee('data-testid="customer-support-ticket-form"', false);

        $this->actingAs($customer)->get(route('customer.support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee('#'.$ticket->id, false);
    }

    public function test_support_hub_redirect_has_no_breadcrumb_view(): void
    {
        [$customer] = $this->legacyCustomerWithBooking();

        $this->actingAs($customer)->get(route('customer.support.index'))
            ->assertRedirect(route('customer.support.tickets.index'));
    }

    public function test_customer_cannot_view_another_customers_support_ticket(): void
    {
        [, $ticket] = $this->legacyCustomerWithTicket();
        [$other] = $this->legacyCustomerWithBooking();

        $this->actingAs($other)->get(route('customer.support.tickets.show', $ticket))->assertForbidden();
    }

    public function test_support_reply_controls_respect_policy(): void
    {
        [$customer, $ticket] = $this->legacyCustomerWithTicket(['status' => SupportTicketStatus::Open]);

        $this->actingAs($customer)->get(route('customer.support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-testid="customer-support-reply-form"', false);

        $ticket->update(['status' => SupportTicketStatus::Resolved]);

        $this->actingAs($customer)->get(route('customer.support.tickets.show', $ticket->fresh()))
            ->assertOk()
            ->assertDontSee('data-testid="customer-support-reply-form"', false)
            ->assertSee('This ticket is finalised', false);
    }

    public function test_customer_profile_renders_universal_settings_and_breadcrumb(): void
    {
        [$customer] = $this->legacyCustomerWithBooking();

        $this->actingAs($customer)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('ota-dashboard-breadcrumbs', false)
            ->assertSee('Profile settings', false)
            ->assertSee('name="name"', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    private function legacyCustomerWithBooking(array $overrides = []): array
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    private function jetpkCustomerWithBooking(array $overrides = []): array
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

    /**
     * @param  array<string, mixed>  $ticketOverrides
     * @return array{0: User, 1: SupportTicket}
     */
    private function legacyCustomerWithTicket(array $ticketOverrides = []): array
    {
        [$customer, $booking] = $this->legacyCustomerWithBooking();
        $ticket = SupportTicket::query()->create(array_merge([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'created_by_user_id' => $customer->id,
            'subject' => 'Need help with payment',
            'category' => SupportTicketCategory::Payment,
            'status' => SupportTicketStatus::Open,
        ], $ticketOverrides));
        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'body' => 'Initial message',
            'visibility' => SupportTicketMessageVisibility::CustomerVisible,
        ]);

        return [$customer, $ticket->fresh()];
    }
}
