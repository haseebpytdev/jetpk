<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_user_form_hides_permission_matrix(): void
    {
        [$admin] = $this->platformAdmin();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $admin->current_agency_id,
            'status' => UserAccountStatus::Active,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $customer));
        $response->assertOk();
        $this->assertMatchesRegularExpression('/id="user-permission-card"\s+hidden/', $response->getContent());
    }

    public function test_staff_user_form_shows_editable_staff_permissions(): void
    {
        [$admin] = $this->platformAdmin();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $admin->current_agency_id,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $staff))
            ->assertOk()
            ->assertSee('Editable staff portal permissions', false)
            ->assertSee('Permission presets', false)
            ->assertSee('data-testid="staff-preset-'.StaffPermission::PresetManager.'"', false)
            ->assertSee('staff_permissions[]', false)
            ->assertSee(StaffPermission::TicketingIssue, false);
    }

    public function test_customer_edit_shows_portal_only_note(): void
    {
        [$admin] = $this->platformAdmin();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $admin->current_agency_id,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $customer))
            ->assertOk()
            ->assertSee('Customer portal access only', false)
            ->assertSee('Promote account type', false);
    }

    public function test_agency_admin_can_create_agent_staff_with_permissions(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        $agent = Agent::query()->where('agency_id', $admin->current_agency_id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Agent Staff A',
            'email' => 'agentstaff@example.test',
            'account_type' => AccountType::AgentStaff->value,
            'status' => UserAccountStatus::Active->value,
            'owner_agent_id' => $agent->id,
            'permissions' => [
                AgentPermission::BookingsView,
                AgentPermission::WalletView,
            ],
        ])->assertRedirect();

        $user = User::query()->where('email', 'agentstaff@example.test')->firstOrFail();
        $this->assertSame(AccountType::AgentStaff, $user->account_type);
        $this->assertSame($agent->id, $user->meta['owner_agent_id']);
        $this->assertEqualsCanonicalizing(
            [AgentPermission::BookingsView, AgentPermission::WalletView],
            $user->meta['agent_permissions'],
        );
    }

    public function test_customer_show_page_has_no_permissions_card(): void
    {
        [$admin] = $this->platformAdmin();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $admin->current_agency_id,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $customer))
            ->assertOk()
            ->assertSee('Customer portal only', false)
            ->assertSee('Open customer profile', false)
            ->assertDontSee('Effective access summary', false)
            ->assertDontSee('Capability area', false);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->first();
        if ($admin === null) {
            $this->seed(OtaFoundationSeeder::class);
            $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        }

        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
