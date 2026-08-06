<?php

namespace Tests\Feature\Agent;

use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Feature\Jetpk\Concerns\BuildsJetpkPortalTestFixtures;
use Tests\TestCase;

class AgentAccountingLedgerJsonTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use BuildsJetpkPortalTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootJetpkPortalContext();
    }

    public function test_agent_admin_can_fetch_accounting_ledger_json(): void
    {
        $this->actingAs($this->agentAdminUser())
            ->getJson(route('agent.accounting.ledger.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'summary',
                'filters',
                'transactions',
                'pagination',
                'blade_fallback_url',
            ]);
    }

    public function test_staff_with_ledger_view_can_fetch_accounting_ledger_json(): void
    {
        $this->actingAs($this->agentStaffUser([AgentPermission::LedgerView]))
            ->getJson(route('agent.accounting.ledger.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_staff_without_ledger_view_is_denied(): void
    {
        $this->actingAs($this->agentStaffUser([]))
            ->getJson(route('agent.accounting.ledger.index', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_accounting_ledger_json_does_not_include_other_agency_booking_reference(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $content = $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.accounting.ledger.index', ['format' => 'json']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('BKG-B-ONLY', $content);
    }

    public function test_guest_is_denied_accounting_ledger_json(): void
    {
        $this->getJson(route('agent.accounting.ledger.index', ['format' => 'json']))
            ->assertUnauthorized();
    }
}
