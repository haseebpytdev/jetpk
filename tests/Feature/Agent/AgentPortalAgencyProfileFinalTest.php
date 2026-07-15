<?php

namespace Tests\Feature\Agent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

/**
 * Final agency details and personal profile UAT for agent portal shell.
 */
class AgentPortalAgencyProfileFinalTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agency_details_show_for_admin_and_view_only_staff(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.agency.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-agency-details"', false)
            ->assertSee('Alpha Travel Services', false);

        $this->actingAs($scenario['staff']['A6'])->get(route('agent.agency.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-agency-details"', false)
            ->assertDontSee('data-testid="agent-agency-edit-link"', false);
    }

    public function test_agency_edit_updates_agent_meta_and_business_email(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $agent = $scenario['agentA'];

        $this->actingAs($scenario['adminA'])
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Updated Alpha Agency',
                'license_number' => 'LIC-UPDATED',
                'city' => 'Karachi',
                'country' => 'Pakistan',
                'address' => '99 Main Blvd',
                'phone' => '+923009998877',
                'email' => 'biz-updated@alpha.test',
            ])
            ->assertRedirect(route('agent.agency.show'))
            ->assertSessionHas('status', 'agency-updated');

        $agent->refresh();
        $this->assertSame('Updated Alpha Agency', $agent->meta['agency_name']);
        $this->assertSame('biz-updated@alpha.test', $scenario['adminA']->fresh()->email);
    }

    public function test_agency_update_rejects_duplicate_business_email(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->from(route('agent.agency.edit'))
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Alpha Travel',
                'email' => $scenario['adminB']->email,
            ])
            ->assertRedirect(route('agent.agency.edit'))
            ->assertSessionHasErrors('email');
    }

    public function test_agency_logo_upload_and_invalid_logo_rejection(): void
    {
        Storage::fake('public');
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Alpha With Logo',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect(route('agent.agency.show'));

        $scenario['agentA']->refresh();
        $this->assertNotEmpty($scenario['agentA']->meta['logo_path'] ?? null);
        Storage::disk('public')->assertExists($scenario['agentA']->meta['logo_path']);

        $this->actingAs($scenario['adminA'])
            ->from(route('agent.agency.edit'))
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Alpha With Logo',
                'logo' => UploadedFile::fake()->create('bad.txt', 10, 'text/plain'),
            ])
            ->assertRedirect(route('agent.agency.edit'))
            ->assertSessionHasErrors('logo');
    }

    public function test_personal_profile_separate_from_agency_fields(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="agent-portal-subnav"', false)
            ->assertSee('Profile settings', false)
            ->assertSee('name="name"', false)
            ->assertDontSee('name="agency_name"', false);

        $this->actingAs($scenario['staff']['A0'])->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="agent-portal-subnav"', false);
    }

    public function test_staff_without_agency_edit_cannot_update_agency(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A6'])
            ->patch(route('agent.agency.update'), ['agency_name' => 'Blocked'])
            ->assertForbidden();

        $this->actingAs($scenario['staff']['A7'])
            ->get(route('agent.agency.edit'))
            ->assertForbidden();

        $this->actingAs($scenario['staff']['A7'])
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Staff Edited Agency',
                'city' => 'Islamabad',
            ])
            ->assertForbidden();
    }
}
