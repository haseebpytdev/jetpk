<?php

namespace Tests\Feature\Jetpk;

use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Jetpk\Concerns\BuildsJetpkPortalTestFixtures;
use Tests\TestCase;

/**
 * JP-PORTAL-3A — Agent finance resolver migration + permission matrix.
 */
class JetpkAgentFinanceResolverTest extends TestCase
{
    use BuildsJetpkPortalTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootJetpkPortalContext();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function financeRoutes(): array
    {
        return [
            'wallet' => ['agent.wallet.show', AgentPermission::WalletView],
            'deposits index' => ['agent.deposits.index', AgentPermission::WalletView],
            'deposit create' => ['agent.deposits.create', AgentPermission::PaymentsUpload],
            'ledger' => ['agent.ledger.index', AgentPermission::LedgerView],
            'accounting ledger' => ['agent.accounting.ledger.index', AgentPermission::LedgerView],
            'reports' => ['agent.reports.index', AgentPermission::ReportsView],
        ];
    }

    #[DataProvider('financeRoutes')]
    public function test_agent_admin_can_open_finance_page(string $routeName, string $permission): void
    {
        $this->actingAs($this->agentAdminUser())->get(route($routeName))->assertOk();
    }

    #[DataProvider('financeRoutes')]
    public function test_permitted_agent_staff_can_open_finance_page(string $routeName, string $permission): void
    {
        $this->actingAs($this->agentStaffUser([$permission]))->get(route($routeName))->assertOk();
    }

    #[DataProvider('financeRoutes')]
    public function test_unpermitted_agent_staff_is_denied(string $routeName, string $permission): void
    {
        $this->actingAs($this->agentStaffUser([]))->get(route($routeName))->assertForbidden();
    }

    public function test_agent_staff_can_never_reach_commissions(): void
    {
        $this->actingAs($this->agentStaffUser([AgentPermission::LedgerView, AgentPermission::WalletView]))
            ->get(route('agent.commissions.index'))
            ->assertForbidden();
    }

    public function test_denied_finance_url_returns_forbidden_for_limited_staff(): void
    {
        $this->actingAs($this->agentStaffUser([]))
            ->get(route('agent.wallet.show'))
            ->assertForbidden()
            ->assertSee('Access restricted', false);
    }

    public function test_profile_and_logout_survive_zero_permission_staff(): void
    {
        $this->actingAs($this->agentStaffUser([]))
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('jp-portal-sidebar-profile', false)
            ->assertSee('jp-portal-sidebar-logout', false);
    }

    public function test_no_finance_leak_in_navigation(): void
    {
        $this->actingAs($this->agentStaffUser([]))
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->assertDontSee(route('agent.wallet.show'), false)
            ->assertDontSee(route('agent.commissions.index'), false)
            ->assertDontSee(route('agent.ledger.index'), false);
    }

    public function test_finance_views_resolve_through_the_resolver(): void
    {
        $resolver = app(\App\Services\Client\RuntimeViewResolver::class);

        foreach ([
            'wallet', 'deposits.index', 'deposits.create', 'ledger.index',
            'accounting.ledger.index', 'accounting.ledger.show',
            'commissions.index', 'commissions.statement',
            'reports.index', 'finance.statement.show',
        ] as $logical) {
            $resolved = $resolver->view($logical, 'agent');
            $this->assertTrue(
                view()->exists($resolved),
                "client_view('{$logical}', 'agent') resolved to a missing view: {$resolved}"
            );
        }
    }

    public function test_legacy_finance_views_still_exist(): void
    {
        foreach ([
            'dashboard.agent.wallet', 'dashboard.agent.deposits.index', 'dashboard.agent.deposits.create',
            'dashboard.agent.ledger.index', 'dashboard.agent.accounting.ledger.index',
            'dashboard.agent.accounting.ledger.show', 'dashboard.agent.commissions.index',
            'dashboard.agent.commissions.statement', 'dashboard.agent.reports.index',
            'dashboard.agent.finance.statement.show',
        ] as $legacy) {
            $this->assertTrue(view()->exists($legacy), "fallback view missing: {$legacy}");
        }
    }
}
