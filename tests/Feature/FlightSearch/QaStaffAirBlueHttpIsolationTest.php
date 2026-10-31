<?php

namespace Tests\Feature\FlightSearch;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\PublicContent\PublicShortRefService;
use App\Support\FlightSearch\QaStaffAirBlueContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaStaffAirBlueHttpIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function storeQaSearch(User $actor): array
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'KHI',
            'depart_date' => now()->addWeeks(3)->toDateString(),
            'adults' => 1,
            'cabin' => 'economy',
        ];
        $searchId = $store->store($criteria, [
            [
                'id' => 'airblue-qa-offer-1',
                'offer_id' => 'airblue-qa-offer-1',
                'supplier_provider' => 'airblue',
                'supplier_connection_id' => 23,
                'final_customer_price' => 15000,
                'currency' => 'PKR',
            ],
        ], [], [
            'qa_scope' => true,
            'qa_scope_type' => QaStaffAirBlueContext::SCOPE_TYPE,
            'qa_connection_id' => 23,
            'qa_actor_user_id' => $actor->id,
            'source_channel' => QaStaffAirBlueContext::SOURCE_CHANNEL,
            'criteria_cache_context' => [
                'source_channel' => QaStaffAirBlueContext::SOURCE_CHANNEL,
                'qa_actor_user_id' => $actor->id,
                'qa_connection_id' => 23,
            ],
        ]);

        return [$searchId, $store->get($searchId) ?? []];
    }

    public function test_anonymous_cannot_open_qa_bootstrap(): void
    {
        $this->get('/staff/qa/airblue-search')->assertRedirect();
    }

    public function test_customer_cannot_open_qa_bootstrap(): void
    {
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($customer)
            ->get('/staff/qa/airblue-search')
            ->assertForbidden();
    }

    public function test_agent_cannot_open_qa_bootstrap(): void
    {
        $agent = User::factory()->create(['account_type' => AccountType::Agent]);
        $this->actingAs($agent)
            ->get('/staff/qa/airblue-search')
            ->assertForbidden();
    }

    public function test_platform_admin_can_open_qa_bootstrap(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $this->actingAs($admin)
            ->get('/staff/qa/airblue-search')
            ->assertOk();
    }

    public function test_anonymous_cannot_read_qa_results_data(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        [$searchId] = $this->storeQaSearch($admin);

        $this->getJson('/flights/results/data?search_id='.$searchId)
            ->assertNotFound();
    }

    public function test_anonymous_cannot_read_qa_offer_details(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        [$searchId] = $this->storeQaSearch($admin);

        $this->getJson('/flights/results/offer?search_id='.$searchId.'&offer_id=airblue-qa-offer-1&format=json')
            ->assertNotFound();
    }

    public function test_authorized_admin_with_qa_session_can_read_results_data(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        [$searchId] = $this->storeQaSearch($admin);

        $this->actingAs($admin);
        $request = request();
        $request->setLaravelSession($this->app['session']->driver());
        QaStaffAirBlueContext::activate($request, $admin);

        $this->withSession([
            QaStaffAirBlueContext::SESSION_KEY => $request->session()->get(QaStaffAirBlueContext::SESSION_KEY),
        ])->getJson('/flights/results/data?search_id='.$searchId)
            ->assertOk();
    }

    public function test_qa_short_ref_resolve_blocked_for_anonymous(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        [$searchId] = $this->storeQaSearch($admin);
        $code = app(PublicShortRefService::class)->mintFlightSearch($searchId, 1800);

        $this->getJson('/api/public/content/short-refs/'.$code)
            ->assertStatus(410)
            ->assertJson(['expired' => true]);
    }

    public function test_normal_public_allowlist_excludes_airblue(): void
    {
        $allowed = config('ota.public_flight_results_suppliers', []);
        $this->assertIsArray($allowed);
        $this->assertNotContains('airblue', array_map('strtolower', $allowed));
    }

    public function test_qa_cache_context_differs_from_public_guest(): void
    {
        $this->assertNotSame('public_guest', QaStaffAirBlueContext::SOURCE_CHANNEL);
        $this->assertSame('qa_staff_airblue', QaStaffAirBlueContext::SOURCE_CHANNEL);
    }
}
