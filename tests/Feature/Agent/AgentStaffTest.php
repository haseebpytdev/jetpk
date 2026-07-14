<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_admin_can_create_staff_under_own_agency(): void
    {
        [$agentUser, $agent] = $this->seedAgent();

        $this->actingAs($agentUser)
            ->post(route('agent.staff.store'), [
                'name' => 'Portal Staff',
                'email' => 'staff@agency.test',
                'phone' => '+923001111111',
                'password' => 'password123',
                'permissions' => [AgentPermission::BookingsView, AgentPermission::AgencyView],
            ])
            ->assertRedirect(route('agent.staff.index'))
            ->assertSessionHas('status', 'staff-created');

        $staff = User::query()->where('email', 'staff@agency.test')->first();
        $this->assertNotNull($staff);
        $this->assertSame(AccountType::AgentStaff, $staff->account_type);
        $this->assertSame($agent->id, (int) ($staff->meta['owner_agent_id'] ?? 0));
        $this->assertContains(AgentPermission::BookingsView, $staff->meta['agent_permissions'] ?? []);
    }

    public function test_agent_admin_can_update_staff_permissions(): void
    {
        [$agentUser, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'update-me@agency.test');

        $this->actingAs($agentUser)
            ->patch(route('agent.staff.update', $staff), [
                'name' => 'Updated Staff',
                'email' => 'update-me@agency.test',
                'status' => UserAccountStatus::Active->value,
                'permissions' => [AgentPermission::WalletView],
            ])
            ->assertRedirect(route('agent.staff.index'));

        $staff->refresh();
        $this->assertSame('Updated Staff', $staff->name);
        $this->assertSame([AgentPermission::WalletView], $staff->meta['agent_permissions']);
    }

    public function test_agent_admin_can_disable_staff(): void
    {
        [$agentUser, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'disable-me@agency.test');

        $this->actingAs($agentUser)
            ->delete(route('agent.staff.destroy', $staff))
            ->assertRedirect(route('agent.staff.index'));

        $this->assertSame(UserAccountStatus::Inactive, $staff->fresh()->status);
    }

    public function test_agent_staff_cannot_create_other_staff_by_default(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'limited@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::AgencyView,
        ]);

        $this->actingAs($staff)
            ->get(route('agent.staff.create'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('agent.staff.store'), [
                'name' => 'Another Staff',
                'email' => 'another@agency.test',
                'password' => 'password123',
            ])
            ->assertForbidden();
    }

    public function test_staff_cannot_edit_another_agents_staff_user(): void
    {
        [, $agentA] = $this->seedAgent();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $otherAgentUser = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $otherAgent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $otherAgentUser->id,
        ]);

        $foreignStaff = $this->createStaffForAgent($otherAgent, 'foreign@agency.test');
        $staffA = $this->createStaffForAgent($agentA, 'mine@agency.test');

        $this->actingAs($staffA)
            ->get(route('agent.staff.edit', $foreignStaff))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_agent_staff_routes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('account_type', AccountType::Customer)->first();
        if ($customer === null) {
            $customer = User::factory()->customer()->create();
        }

        $this->actingAs($customer)
            ->get(route('agent.staff.index'))
            ->assertForbidden();
    }

    protected function createStaffForAgent(Agent $agent, string $email, array $permissions = []): User
    {
        return User::query()->create([
            'name' => 'Staff User',
            'username' => str_replace('@', '-', $email),
            'email' => $email,
            'password' => bcrypt('password'),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agent->agency_id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => $permissions,
            ],
        ]);
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
