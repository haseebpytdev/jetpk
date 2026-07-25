<?php

namespace Tests\Feature\Jetpk;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Support\JetpkHomepageFixture;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JetpkAuthenticatedRoleVerificationTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use JetpkHomepageFixture;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
        $this->makeJetpkProfile();
    }

    public function test_customer_dashboard_renders_without_supplier_calls(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="jp-customer-dashboard"', false);

        Http::assertNothingSent();
    }

    public function test_agent_dashboard_renders_without_supplier_calls(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="agent-portal-subnav"', false);

        Http::assertNothingSent();
    }

    public function test_staff_dashboard_renders_without_supplier_calls(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => Agency::query()->where('slug', 'asif-travels')->value('id'),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_platform_admin_dashboard_renders_without_supplier_calls(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="ota-dash-overview"', false);

        Http::assertNothingSent();
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    private function customerUser(): User
    {
        $agency = $this->seedJetpkAgency();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        return $customer;
    }
}
