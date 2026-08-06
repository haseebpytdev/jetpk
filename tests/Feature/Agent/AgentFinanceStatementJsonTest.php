<?php

namespace Tests\Feature\Agent;

use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Jetpk\Concerns\BuildsJetpkPortalTestFixtures;
use Tests\TestCase;

class AgentFinanceStatementJsonTest extends TestCase
{
    use BuildsJetpkPortalTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootJetpkPortalContext();
    }

    public function test_agent_admin_can_fetch_finance_statement_json(): void
    {
        $this->actingAs($this->agentAdminUser())
            ->getJson(route('agent.finance.statement.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'agency',
                'period',
                'currency',
                'opening_balance',
                'closing_balance',
                'movements',
                'reconciliation',
                'export_url',
                'blade_fallback_url',
            ]);
    }

    public function test_staff_with_reports_view_can_fetch_statement_json(): void
    {
        $this->actingAs($this->agentStaffUser([AgentPermission::ReportsView]))
            ->getJson(route('agent.finance.statement.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_staff_without_reports_or_ledger_view_is_denied(): void
    {
        $this->actingAs($this->agentStaffUser([]))
            ->getJson(route('agent.finance.statement.show', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_invalid_period_returns_validation_json(): void
    {
        $this->actingAs($this->agentAdminUser())
            ->getJson(route('agent.finance.statement.show', [
                'format' => 'json',
                'date_from' => now()->addDay()->toDateString(),
                'date_to' => now()->subDay()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'validation');
    }

    public function test_export_url_is_internal_laravel_path(): void
    {
        $response = $this->actingAs($this->agentAdminUser())
            ->getJson(route('agent.finance.statement.show', ['format' => 'json']))
            ->assertOk();

        $exportUrl = (string) $response->json('export_url');
        $this->assertStringStartsWith('/laravel/agent/finance/statement/export', $exportUrl);
        $this->assertStringNotContainsString('http://', $exportUrl);
    }

    public function test_guest_is_denied_statement_json(): void
    {
        $this->getJson(route('agent.finance.statement.show', ['format' => 'json']))
            ->assertUnauthorized();
    }
}
