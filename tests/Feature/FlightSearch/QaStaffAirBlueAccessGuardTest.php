<?php

namespace Tests\Feature\FlightSearch;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\FlightSearch\QaFlightSearchAccessGuard;
use App\Support\FlightSearch\QaStaffAirBlueContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QaStaffAirBlueAccessGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_cannot_access_qa_payload(): void
    {
        $payload = [
            'qa_scope' => true,
            'qa_actor_user_id' => 1,
            'source_channel' => QaStaffAirBlueContext::SOURCE_CHANNEL,
            'offers' => [],
        ];
        $request = Request::create('/flights/results/data', 'GET');
        $this->expectException(HttpException::class);
        app(QaFlightSearchAccessGuard::class)->assertCanAccess($payload, $request);
    }

    public function test_customer_cannot_access_qa_payload(): void
    {
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $payload = [
            'qa_scope' => true,
            'qa_actor_user_id' => $customer->id,
            'source_channel' => QaStaffAirBlueContext::SOURCE_CHANNEL,
        ];
        $request = Request::create('/flights/results/data', 'GET');
        $request->setUserResolver(static fn () => $customer);
        $this->expectException(HttpException::class);
        app(QaFlightSearchAccessGuard::class)->assertCanAccess($payload, $request);
    }

    public function test_agent_cannot_initiate_qa_search(): void
    {
        $agent = User::factory()->create(['account_type' => AccountType::Agent]);
        $request = Request::create('/staff/qa/airblue-search', 'GET');
        $request->setUserResolver(static fn () => $agent);
        $this->expectException(HttpException::class);
        app(QaFlightSearchAccessGuard::class)->assertCanInitiate($request);
    }

    public function test_platform_admin_with_active_session_can_access_own_qa_payload(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $this->actingAs($admin);
        $request = Request::create('/flights/results/data', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(static fn () => $admin);
        QaStaffAirBlueContext::activate($request, $admin);

        $payload = [
            'qa_scope' => true,
            'qa_actor_user_id' => $admin->id,
            'source_channel' => QaStaffAirBlueContext::SOURCE_CHANNEL,
        ];

        app(QaFlightSearchAccessGuard::class)->assertCanAccess($payload, $request);
        $this->assertTrue(true);
    }

    public function test_non_qa_payload_is_not_blocked(): void
    {
        $request = Request::create('/flights/results/data', 'GET');
        app(QaFlightSearchAccessGuard::class)->assertCanAccess([
            'source_channel' => 'public_guest',
            'offers' => [],
        ], $request);
        $this->assertTrue(true);
    }

    public function test_qa_store_meta_flags_are_detected(): void
    {
        $this->assertTrue(QaStaffAirBlueContext::payloadIsQaScope([
            'qa_scope' => true,
        ]));
        $this->assertTrue(QaStaffAirBlueContext::payloadIsQaScope([
            'criteria_cache_context' => ['source_channel' => QaStaffAirBlueContext::SOURCE_CHANNEL],
        ]));
        $this->assertFalse(QaStaffAirBlueContext::payloadIsQaScope([
            'source_channel' => 'public_guest',
        ]));
        $this->assertSame(FlightSearchResultStore::SESSION_TTL_SECONDS > 0, true);
    }
}
