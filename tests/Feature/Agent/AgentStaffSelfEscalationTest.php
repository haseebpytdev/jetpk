<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentStaffSelfEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_staff_cannot_update_own_permissions_via_portal(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'self@agency.test', [
            AgentPermission::StaffManage,
            AgentPermission::BookingsView,
        ]);

        $this->actingAs($staff)
            ->patch(route('agent.staff.update', $staff), [
                'name' => $staff->name,
                'email' => $staff->email,
                'status' => UserAccountStatus::Active->value,
                'permissions' => [
                    AgentPermission::BookingsView,
                    AgentPermission::BookingsCreate,
                    AgentPermission::WalletView,
                    AgentPermission::StaffManage,
                ],
            ])
            ->assertForbidden();

        $staff->refresh();
        $this->assertEqualsCanonicalizing(
            [AgentPermission::StaffManage, AgentPermission::BookingsView],
            $staff->meta['agent_permissions'] ?? [],
        );
    }

    public function test_agent_owner_can_update_other_staff_permissions(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$agentUser, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'other@agency.test', [AgentPermission::BookingsView]);

        $this->actingAs($agentUser)
            ->patch(route('agent.staff.update', $staff), [
                'name' => $staff->name,
                'email' => $staff->email,
                'status' => UserAccountStatus::Active->value,
                'permissions' => [AgentPermission::WalletView],
            ])
            ->assertRedirect(route('agent.staff.index'));

        $this->assertSame([AgentPermission::WalletView], $staff->fresh()->meta['agent_permissions']);
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
