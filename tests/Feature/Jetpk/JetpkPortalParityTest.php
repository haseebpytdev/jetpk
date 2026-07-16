<?php

namespace Tests\Feature\Jetpk;

use App\Enums\AccountType;
use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use App\Support\Agents\AgentPermission;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class JetpkPortalParityTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('client_route_parity.enabled', false);
        app(CurrentClientContext::class)->set($this->makeJetpkProfile());
    }

    public function test_customer_profile_uses_jetpk_portal_shell(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="customer-account-subnav"', false)
            ->assertSee('data-testid="jp-portal-sidebar-profile"', false)
            ->assertSee('data-testid="jp-portal-sidebar-logout"', false)
            ->assertSee('data-testid="jp-portal-profile-settings"', false)
            ->assertSee('data-testid="jp-portal-top-profile"', false)
            ->assertSee('method="post"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('css/ota-public.css', false);
    }

    public function test_customer_profile_update_preserves_route(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Portal Customer',
            'email' => 'portal-customer@jetpk.test',
            'username' => 'portalcustomer',
        ]);

        $this->actingAs($customer)
            ->patch(route('profile.update'), [
                'name' => 'Portal Customer Updated',
                'email' => 'portal-customer@jetpk.test',
                'username' => 'portalcustomer',
                'phone' => '+923001112233',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'profile-updated');

        $this->assertSame('Portal Customer Updated', $customer->fresh()->name);
    }

    public function test_agent_profile_uses_jetpk_portal_shell(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="agent-portal-subnav"', false)
            ->assertSee('data-testid="jp-portal-sidebar-profile"', false)
            ->assertSee('data-testid="jp-portal-sidebar-logout"', false)
            ->assertSee('data-testid="jp-portal-profile-settings"', false)
            ->assertDontSee('css/ota-public.css', false);
    }

    public function test_agent_staff_profile_sidebar_always_includes_logout(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A0'])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="jp-portal-sidebar-logout"', false)
            ->assertSee('data-testid="jp-portal-sidebar-profile"', false)
            ->assertDontSee('href="'.route('agent.bookings.index').'"', false);
    }

    public function test_agent_staff_limited_nav_hides_unauthorized_modules(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $html = $this->actingAs($scenario['staff']['A0'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="jp-portal-sidebar-logout"', $html);
        $this->assertStringContainsString('data-testid="jp-portal-sidebar-profile"', $html);

        preg_match('/data-testid="agent-portal-subnav"[\s\S]*?<\/nav>/', $html, $navMatch);
        $navHtml = $navMatch[0] ?? '';
        $this->assertNotSame('', $navHtml);
        $this->assertStringNotContainsString('href="'.route('agent.bookings.index').'"', $navHtml);
        $this->assertStringNotContainsString('href="'.route('agent.wallet.show').'"', $navHtml);
        $this->assertStringNotContainsString('href="'.route('agent.staff.index').'"', $navHtml);
    }

    public function test_agent_staff_with_bookings_permission_sees_bookings_nav(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A1'])
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('href="/agent/bookings"', false);
    }

    public function test_sidebar_logout_uses_post_form(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('data-testid="jp-portal-sidebar-logout"', false);
    }

    public function test_mobile_customer_profile_includes_logout(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->withHeader('Sec-CH-Viewport-Width', '390')
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="mobile-customer-logout"', false)
            ->assertSee('action="'.route('logout').'"', false);
    }

    public function test_mobile_agent_profile_includes_logout(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A0'])
            ->withHeader('Sec-CH-Viewport-Width', '390')
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="mobile-agent-logout"', false);
    }

    private function makeJetpkProfile(): ClientProfile
    {
        $profile = ClientProfile::query()->create([
            'name' => 'Jet Pakistan',
            'slug' => 'jetpk',
            'environment' => 'staging',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => true,
            ]);
        }

        return $profile;
    }
}
