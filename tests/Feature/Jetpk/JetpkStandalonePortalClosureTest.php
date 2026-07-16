<?php

namespace Tests\Feature\Jetpk;

use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Jetpk\Concerns\BuildsJetpkPortalTestFixtures;
use Tests\TestCase;

/**
 * JETPK-STANDALONE-PORTAL closure — finance views, icons, CSS, mobile travelers.
 */
class JetpkStandalonePortalClosureTest extends TestCase
{
    use BuildsJetpkPortalTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootJetpkPortalContext();
    }

    public function test_agent_finance_theme_views_exist_and_render(): void
    {
        $user = $this->agentAdminUser();

        $this->actingAs($user)->get(route('agent.accounting.ledger.index'))->assertOk();
        $this->actingAs($user)->get(route('agent.reports.index'))->assertOk();
        $this->actingAs($user)->get(route('agent.finance.statement.show'))->assertOk();
    }

    public function test_customer_travelers_mobile_shell_renders(): void
    {
        $this->actingAs($this->customerUser())
            ->withCookie('ota_view_mode', 'mobile')
            ->get(route('customer.travelers.index'))
            ->assertOk()
            ->assertSee('data-testid="ota-mobile-customer-travelers-index"', false);
    }

    public function test_jp_icon_names_are_defined(): void
    {
        $iconFile = resource_path('views/components/jp/icon.blade.php');
        $contents = File::get($iconFile);

        preg_match_all("/@case\\('([^']+)'\\)/", $contents, $matches);
        $defined = $matches[1];

        $this->assertContains('wallet', $defined);
        $this->assertContains('message-circle', $defined);
        $this->assertGreaterThanOrEqual(28, count($defined));
    }

    public function test_portal_css_defines_finance_selectors(): void
    {
        $css = File::get(public_path('themes/frontend/jetpakistan/css/portal.css'));

        foreach (['.jp-kpi-grid', '.jp-finance-recon-grid', '.jp-field-grid', '.jp-dl', '.jp-panel--filters'] as $selector) {
            $this->assertStringContainsString($selector, $css, "Missing selector {$selector}");
        }
    }

    public function test_standalone_config_is_enabled_by_default(): void
    {
        $this->assertTrue(config('client.standalone'));
        $this->assertSame('jetpk', config('client.canonical_client.slug'));
        $this->assertFalse(config('client.fallback_policy.allow_cross_client_views'));
    }

    public function test_agent_staff_finance_permission_matrix_unchanged(): void
    {
        $this->actingAs($this->agentStaffUser([]))
            ->get(route('agent.wallet.show'))
            ->assertForbidden();

        $this->actingAs($this->agentStaffUser([AgentPermission::WalletView]))
            ->get(route('agent.wallet.show'))
            ->assertOk();
    }
}
