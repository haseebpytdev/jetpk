<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use App\Support\Booking\AgentBookingContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AgentBookingContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_activate_stores_secure_session_context_from_authenticated_agent(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $user->id)->firstOrFail();

        $request = Request::create('/agent/bookings/create', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        AgentBookingContext::activate($request, $user);

        $resolved = AgentBookingContext::resolve($request);
        $this->assertNotNull($resolved);
        $this->assertSame($agent->id, $resolved['agent_id']);
        $this->assertSame($user->id, $resolved['agent_user_id']);
        $this->assertSame($agent->agency_id, $resolved['agency_id']);
    }

    public function test_clear_removes_session_context(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $request = Request::create('/agent/bookings/create', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        AgentBookingContext::activate($request, $user);
        $this->assertTrue(AgentBookingContext::isActive($request));

        AgentBookingContext::clear($request);
        $this->assertFalse(AgentBookingContext::isActive($request));
    }

    public function test_resolve_checkout_channel_uses_session_agency_for_agent_mode(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $user->id)->firstOrFail();
        $agency = Agency::query()->findOrFail($agent->agency_id);

        $request = Request::create('/flights/results', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        AgentBookingContext::activate($request, $user);

        $channel = AgentBookingContext::resolveCheckoutChannel($request);

        $this->assertTrue($channel['agent_booking_mode']);
        $this->assertSame('agent_portal', $channel['source_channel']);
        $this->assertSame($agent->id, $channel['agent_id']);
        $this->assertSame($agency->id, $channel['agency']?->id);
    }

    public function test_resolve_checkout_channel_defaults_to_public_guest_without_session(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $request = Request::create('/', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        $channel = AgentBookingContext::resolveCheckoutChannel($request);

        $this->assertFalse($channel['agent_booking_mode']);
        $this->assertSame('public_guest', $channel['source_channel']);
        $this->assertNull($channel['agent_id']);
    }
}
