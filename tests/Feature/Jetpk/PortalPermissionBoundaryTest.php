<?php

namespace Tests\Feature\Jetpk;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class PortalPermissionBoundaryTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use BuildsAgentPortalScenario;
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota-developer.enabled' => true]);
        config(['client_route_parity.enabled' => false]);
        $this->makeJetpkProfile();
        $this->agency = $this->seedJetpkAgency();
    }

    private \App\Models\Agency $agency;

    public function test_agent_owner_can_access_owner_only_commissions_route(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->get(route('agent.commissions.index'))
            ->assertOk();
    }

    public function test_agent_staff_without_owner_role_is_denied_owner_only_route_directly(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A11'])
            ->get(route('agent.commissions.index'))
            ->assertForbidden();
    }

    public function test_agent_staff_wallet_denial_applies_to_direct_endpoint_not_only_navigation(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $response = $this->actingAs($scenario['staff']['A1'])
            ->getJson(route('agent.wallet.show', ['format' => 'json']));

        $response->assertForbidden();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('25,000.00', $body);
        $this->assertStringNotContainsString((string) $scenario['walletA']->balance, $body);
    }

    public function test_platform_staff_with_bookings_permission_can_access_staff_bookings_route(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->assertLegacyStaffBookingsIndexRedirect($staff);
    }

    public function test_platform_staff_without_required_permission_is_denied_admin_route_without_payload(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $response = $this->actingAs($staff)
            ->get('/admin/page-settings/home');

        $response->assertForbidden();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('Edit Home', $body);
        $this->assertDoesNotMatchRegularExpression('/client_page_asset|page-settings-form/i', $body);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staffWithPermissions(array $permissions): User
    {
        $this->seed(OtaFoundationSeeder::class);

        return User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->agency->id,
            'meta' => ['staff_permissions' => $permissions],
        ]);
    }
}
