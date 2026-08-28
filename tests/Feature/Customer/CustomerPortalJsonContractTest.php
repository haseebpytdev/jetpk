<?php

namespace Tests\Feature\Customer;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_dashboard_json_requires_customer_role(): void
    {
        [$customer] = $this->customerWithBooking();
        $agent = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $customer->current_agency_id,
        ]);

        $this->actingAs($agent)
            ->getJson(route('customer.dashboard'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->getJson(route('customer.dashboard'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'metrics' => ['upcoming_trips', 'pending_payment', 'total_bookings'],
                'recent_bookings' => [
                    ['booking_status' => ['code', 'label'], 'payment_status' => ['code', 'label']],
                ],
            ]);

        $dashboardPayload = $this->actingAs($customer)
            ->getJson(route('customer.dashboard'))
            ->json();
        $this->assertArrayHasKey('booking_status', $dashboardPayload['recent_bookings'][0]);
        $this->assertArrayNotHasKey('status', $dashboardPayload['recent_bookings'][0]);
    }

    public function test_customer_bookings_json_pagination_and_ownership(): void
    {
        [$customer, $booking] = $this->customerWithBooking();
        [$other] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->getJson(route('customer.bookings.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['booking_reference' => $booking->booking_reference]);

        $this->actingAs($other)
            ->getJson(route('customer.bookings.show', ['booking' => $booking->booking_reference]))
            ->assertForbidden();
    }

    public function test_customer_draft_booking_detail_resolves_by_id_without_reference(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $draft = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Draft,
            'payment_status' => 'unpaid',
            'booking_reference' => null,
            'pnr' => null,
            'route' => 'LHE-DXB',
            'travel_date' => now()->addDays(12)->toDateString(),
        ]);

        $list = $this->actingAs($customer)
            ->getJson(route('customer.bookings.index', ['format' => 'json']))
            ->assertOk()
            ->json();

        $item = collect($list['bookings'] ?? [])->firstWhere('route', 'LHE-DXB');
        $this->assertNotNull($item);
        $this->assertSame('draft', $item['booking_status']['code'] ?? null);
        $this->assertSame('/customer/bookings/'.$draft->id, $item['detail_url'] ?? null);

        $this->actingAs($customer)
            ->getJson(route('customer.bookings.show', ['booking' => $draft->id]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking.id', $draft->id);
    }

    public function test_customer_draft_resume_binds_same_booking_id_and_rejects_foreign_customer(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $other = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        $agency->users()->attach($other->id, ['role' => 'customer']);

        $draft = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Draft,
            'payment_status' => 'unpaid',
            'booking_reference' => null,
            'pnr' => null,
            'route' => 'LHE-DXB',
            'travel_date' => now()->addDays(12)->toDateString(),
            'meta' => [
                'checkout_offer_id' => 'offer-resume-1',
                'checkout_search_id' => '11111111-1111-1111-1111-111111111111',
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => now()->addDays(12)->toDateString(),
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
                'flight_offer_snapshot' => ['id' => 'offer-resume-1'],
            ],
        ]);

        $detail = $this->actingAs($customer)
            ->getJson(route('customer.bookings.show', ['booking' => $draft->id]))
            ->assertOk()
            ->json();

        $resumeAction = collect($detail['actions'] ?? [])->firstWhere('code', 'resume_checkout');
        $this->assertNotNull($resumeAction);
        $this->assertSame('/customer/bookings/'.$draft->id.'/resume', $resumeAction['url'] ?? null);

        $this->actingAs($other)
            ->getJson(route('customer.bookings.resume', ['booking' => $draft->id]))
            ->assertForbidden();

        $this->actingAs($customer)
            ->getJson(route('customer.bookings.resume', ['booking' => $draft->id]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking_id', $draft->id)
            ->assertJsonPath('next_url', '/booking/passengers');

        $this->assertSame($draft->id, session('ota_public_booking_id'));
    }

    public function test_numeric_draft_idor_is_forbidden_for_other_customer_and_guest(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $owner = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $other = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($owner->id, ['role' => 'customer']);
        $agency->users()->attach($other->id, ['role' => 'customer']);

        $draft = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $owner->id,
            'status' => BookingStatus::Draft,
            'payment_status' => 'unpaid',
            'booking_reference' => null,
            'pnr' => null,
        ]);

        $this->getJson(route('customer.bookings.show', ['booking' => $draft->id]))
            ->assertUnauthorized();

        $this->actingAs($other)
            ->getJson(route('customer.bookings.show', ['booking' => $draft->id]))
            ->assertForbidden();

        $this->actingAs($other)
            ->getJson(route('customer.bookings.resume', ['booking' => $draft->id]))
            ->assertForbidden();
    }

    public function test_customer_support_json_create_and_detail(): void
    {
        [$customer, $booking] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->postJson(route('customer.support.tickets.store'), [
                'subject' => 'Need help',
                'category' => 'payment',
                'body' => 'Please assist with payment.',
                'booking_id' => $booking->id,
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $ticket = SupportTicket::query()->where('created_by_user_id', $customer->id)->firstOrFail();

        $this->actingAs($customer)
            ->getJson(route('customer.support.tickets.show', ['ticket' => $ticket->ticket_reference]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ticket.reference', $ticket->ticket_reference);
    }

    public function test_customer_notifications_json_returns_available_inbox(): void
    {
        [$customer] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->getJson(route('customer.notifications.index'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('available', true)
            ->assertJsonPath('transport', 'EVENT_POLLING')
            ->assertJsonPath('unread_count', 0);
    }

    public function test_customer_profile_json_and_blade_route_preserved(): void
    {
        [$customer] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->getJson(route('customer.profile.show'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.email', $customer->email);

        $this->actingAs($customer)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: Booking}
     */
    private function customerWithBooking(): array
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
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
            'travel_date' => now()->addDays(5)->toDateString(),
        ]);

        return [$customer, $booking];
    }
}
