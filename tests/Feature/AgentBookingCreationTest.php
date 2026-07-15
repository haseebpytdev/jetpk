<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use App\Support\Booking\AgentBookingContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class AgentBookingCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_access_agent_bookings_index(): void
    {
        $agentUser = $this->seededAgentUser();

        $this->actingAs($agentUser)
            ->get('/agent/bookings')
            ->assertOk();
    }

    public function test_agent_create_launcher_shows_search_flights_not_mock_form(): void
    {
        $agentUser = $this->seededAgentUser();

        $this->actingAs($agentUser)
            ->get(route('agent.bookings.create'))
            ->assertOk()
            ->assertSee('Search flights', false)
            ->assertSee('Create flight booking', false)
            ->assertDontSee('Select mock flight', false)
            ->assertDontSee('Trip & mock flight selection', false);
    }

    public function test_agent_create_activates_booking_context_in_session(): void
    {
        $agentUser = $this->seededAgentUser();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        $this->actingAs($agentUser)->get(route('agent.bookings.create'))->assertOk();

        $context = session(AgentBookingContext::SESSION_KEY);
        $this->assertIsArray($context);
        $this->assertSame('agent', $context['booking_context']);
        $this->assertSame($agent->id, $context['agent_id']);
        $this->assertSame($agentUser->id, $context['agent_user_id']);
    }

    public function test_agent_bookings_store_redirects_to_launcher(): void
    {
        [$depart, $agentUser] = $this->seedAgentWithCheckoutDoubles();

        $this->actingAs($agentUser)
            ->post('/agent/bookings', $this->legacyStorePayload($depart))
            ->assertRedirect(route('agent.bookings.create'));

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_agent_public_checkout_creates_agent_portal_booking(): void
    {
        [$depart, $agentUser] = $this->seedAgentWithCheckoutDoubles();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB', 'agent_portal', $agent->id);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($agentUser)->get(route('agent.bookings.create'))->assertOk();

        $this->actingAs($agentUser)->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'ali.customer@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($agent->id, $booking->agent_id);
        $this->assertSame('agent_portal', $booking->source_channel);
        $this->assertSame('duffel', $booking->supplier);
    }

    public function test_agent_can_view_own_booking(): void
    {
        $agentUser = $this->seededAgentUser();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'source_channel' => 'agent_portal',
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($agentUser)
            ->get('/agent/bookings/'.$booking->id)
            ->assertOk();
    }

    public function test_agent_cannot_view_another_agents_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $otherAgentUser = User::factory()->agent()->create([
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($otherAgentUser->id, ['role' => 'agent']);
        $otherAgent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $otherAgentUser->id,
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $otherAgent->id,
            'source_channel' => 'agent_portal',
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'booking_reference' => 'OTA-OTHER-AGENT',
        ]);

        $this->actingAs($agentUser)
            ->get('/agent/bookings/'.$booking->id)
            ->assertForbidden();
    }

    public function test_agent_cannot_access_admin_bookings(): void
    {
        $agentUser = $this->seededAgentUser();

        $this->actingAs($agentUser)
            ->get('/admin/bookings')
            ->assertForbidden();
    }

    public function test_agent_portal_booking_is_queryable_by_agent_id(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = Agent::query()->whereHas('user', fn ($q) => $q->where('email', 'agent@ota.demo'))->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'source_channel' => 'agent_portal',
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
        ]);

        $this->assertSame(1, Booking::query()->where('agent_id', $agent->id)->where('source_channel', 'agent_portal')->count());
        $this->assertSame($booking->id, Booking::query()->where('agent_id', $agent->id)->value('id'));
    }

    protected function seededAgentUser(): User
    {
        $this->seed(OtaFoundationSeeder::class);

        return User::query()->where('email', 'agent@ota.demo')->firstOrFail();
    }

    /**
     * @return array{0: string, 1: User}
     */
    protected function seedAgentWithCheckoutDoubles(): array
    {
        $depart = now()->addDays(16)->format('Y-m-d');
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, $depart);

        return [$depart, User::query()->where('email', 'agent@ota.demo')->firstOrFail()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function legacyStorePayload(string $depart): array
    {
        return [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'dob' => now()->subYears(30)->toDateString(),
            'nationality' => 'PK',
            'email' => 'ali.customer@example.com',
            'phone' => '+923001112233',
            'country' => 'Pakistan',
            'agent_note' => 'Customer requested morning departure.',
        ];
    }
}
