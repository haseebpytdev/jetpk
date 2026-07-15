<?php

namespace Tests\Feature\Agent;

use App\Models\Agent;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentAgencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_view_own_agency_details(): void
    {
        [$agentUser] = $this->seedAgent();

        $this->actingAs($agentUser)
            ->get(route('agent.agency.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-agency-details"', false)
            ->assertSee('data-testid="agent-agency-edit-link"', false);
    }

    public function test_agent_can_edit_and_update_agency_details(): void
    {
        [$agentUser, $agent] = $this->seedAgent();

        $this->actingAs($agentUser)
            ->get(route('agent.agency.edit'))
            ->assertOk()
            ->assertSee('data-testid="agent-agency-edit-form"', false);

        $this->actingAs($agentUser)
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Skyline Travels',
                'license_number' => 'LIC-100',
                'city' => 'Lahore',
                'country' => 'Pakistan',
                'address' => '12 Mall Road',
                'phone' => '+923001234567',
                'email' => 'biz@skyline.test',
            ])
            ->assertRedirect(route('agent.agency.show'))
            ->assertSessionHas('status', 'agency-updated');

        $agent->refresh();
        $agentUser->refresh();

        $this->assertSame('Skyline Travels', $agent->meta['agency_name']);
        $this->assertSame('LIC-100', $agent->meta['license_number']);
        $this->assertSame('biz@skyline.test', $agentUser->email);

        $this->actingAs($agentUser)
            ->get(route('agent.agency.show'))
            ->assertOk()
            ->assertSee('Skyline Travels', false)
            ->assertSee('Lahore', false);
    }

    public function test_agency_update_validation_errors(): void
    {
        [$agentUser] = $this->seedAgent();

        $this->actingAs($agentUser)
            ->from(route('agent.agency.edit'))
            ->patch(route('agent.agency.update'), ['agency_name' => ''])
            ->assertRedirect(route('agent.agency.edit'))
            ->assertSessionHasErrors('agency_name');
    }

    public function test_agent_staff_without_edit_permission_cannot_update_agency(): void
    {
        [$agentUser, $agent] = $this->seedAgent();
        $staff = User::factory()->agentStaff()->create([
            'current_agency_id' => $agent->agency_id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => [AgentPermission::AgencyView],
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('agent.agency.edit'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Blocked Update',
            ])
            ->assertForbidden();
    }

    public function test_agency_logo_upload_stores_file(): void
    {
        Storage::fake('public');
        [$agentUser, $agent] = $this->seedAgent();

        $file = UploadedFile::fake()->image('logo.png');

        $this->actingAs($agentUser)
            ->patch(route('agent.agency.update'), [
                'agency_name' => 'Logo Agency',
                'logo' => $file,
            ])
            ->assertRedirect(route('agent.agency.show'));

        $agent->refresh();
        $this->assertNotEmpty($agent->meta['logo_path'] ?? null);
        Storage::disk('public')->assertExists($agent->meta['logo_path']);
    }

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seedAgent(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        return [$agentUser, $agent];
    }
}
