<?php

namespace Tests\Feature\Agent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPortalProfileAgencyTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agent_admin_profile_uses_agent_portal_shell(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="agent-portal-subnav"', false)
            ->assertSee('Profile settings', false);
    }

    public function test_agent_staff_profile_uses_agent_portal_shell(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A0'])->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-testid="agent-portal-subnav"', false);
    }

    public function test_profile_form_does_not_use_agency_name_as_personal_name_fields(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertDontSee('name="agency_name"', false)
            ->assertDontSee('name="first_name"', false);
    }

    public function test_agency_edit_updates_agent_meta_fields(): void
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
        $this->assertSame('LIC-UPDATED', $agent->meta['license_number']);
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

    public function test_agency_logo_upload_validation_and_storage(): void
    {
        Storage::fake('public');
        $scenario = $this->buildAgentPortalScenario();
        $file = UploadedFile::fake()->image('logo.png');

        $this->actingAs($scenario['adminA'])
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Alpha With Logo',
                'logo' => $file,
            ])
            ->assertRedirect(route('agent.agency.show'));

        $scenario['agentA']->refresh();
        $this->assertNotEmpty($scenario['agentA']->meta['logo_path'] ?? null);
        Storage::disk('public')->assertExists($scenario['agentA']->meta['logo_path']);
    }

    public function test_staff_without_agency_edit_cannot_update_agency(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A6'])
            ->patch(route('agent.agency.update'), ['agency_name' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_staff_with_legacy_agency_edit_permission_cannot_update_agency(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A7'])
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Staff Edited Agency',
                'city' => 'Islamabad',
            ])
            ->assertForbidden();

        $this->assertNotSame('Staff Edited Agency', $scenario['agentA']->fresh()->meta['agency_name']);
    }
}
