<?php

namespace Tests\Feature\Rbac;

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

class StaffPermissionPresetTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_manager_preset_saves_all_staff_keys(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->legacyStaffPair();

        $this->actingAs($admin)->patch(route('admin.users.update', $staff), $this->staffUpdatePayload($staff, [
            'staff_permissions' => StaffPermission::presetPermissions(StaffPermission::PresetManager),
        ]))->assertRedirect(route('admin.users.show', $staff));

        $this->assertEqualsCanonicalizing(
            StaffPermission::all(),
            $staff->fresh()->meta['staff_permissions'] ?? [],
        );
        $this->assertFalse($staff->fresh()->usesLegacyStaffPermissions());
    }

    public function test_staff_operator_preset_saves_operational_keys(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->legacyStaffPair();

        $this->actingAs($admin)->patch(route('admin.users.update', $staff), $this->staffUpdatePayload($staff, [
            'staff_permissions' => StaffPermission::presetPermissions(StaffPermission::PresetOperator),
        ]))->assertRedirect(route('admin.users.show', $staff));

        $this->assertEqualsCanonicalizing(
            StaffPermission::presetPermissions(StaffPermission::PresetOperator),
            $staff->fresh()->meta['staff_permissions'] ?? [],
        );
    }

    public function test_staff_support_preset_saves_support_keys(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->legacyStaffPair();

        $this->actingAs($admin)->patch(route('admin.users.update', $staff), $this->staffUpdatePayload($staff, [
            'staff_permissions' => StaffPermission::presetPermissions(StaffPermission::PresetSupport),
        ]))->assertRedirect(route('admin.users.show', $staff));

        $this->assertEqualsCanonicalizing(
            StaffPermission::presetPermissions(StaffPermission::PresetSupport),
            $staff->fresh()->meta['staff_permissions'] ?? [],
        );
    }

    public function test_manual_staff_permission_toggle_changes_are_saved(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->legacyStaffPair();
        $custom = [
            StaffPermission::BookingsView,
            StaffPermission::TicketingIssue,
            StaffPermission::SupportReply,
        ];

        $this->actingAs($admin)->patch(route('admin.users.update', $staff), $this->staffUpdatePayload($staff, [
            'staff_permissions' => $custom,
        ]))->assertRedirect(route('admin.users.show', $staff));

        $this->assertEqualsCanonicalizing($custom, $staff->fresh()->meta['staff_permissions'] ?? []);
    }

    public function test_unknown_staff_permission_key_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->legacyStaffPair();

        $this->actingAs($admin)->from(route('admin.users.edit', $staff))
            ->patch(route('admin.users.update', $staff), $this->staffUpdatePayload($staff, [
                'staff_permissions' => [StaffPermission::BookingsView, 'admin.users.manage', 'not.a.permission'],
            ]))
            ->assertSessionHasErrors('staff_permissions.1');

        $this->assertTrue($staff->fresh()->usesLegacyStaffPermissions());
    }

    public function test_platform_permission_cannot_be_saved_as_staff_permission(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->legacyStaffPair();

        $this->actingAs($admin)->from(route('admin.users.edit', $staff))
            ->patch(route('admin.users.update', $staff), $this->staffUpdatePayload($staff, [
                'staff_permissions' => ['platform.settings.manage'],
            ]))
            ->assertSessionHasErrors('staff_permissions.0');

        $this->assertTrue($staff->fresh()->usesLegacyStaffPermissions());
    }

    public function test_legacy_mode_warning_appears_on_staff_edit_form(): void
    {
        [$admin, $staff] = $this->legacyStaffPair();

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $staff))
            ->assertOk()
            ->assertSee('data-testid="staff-legacy-access-warning"', false)
            ->assertSee('This staff user is currently using legacy full staff access. Saving permissions will enable permission-based access.', false);
    }

    public function test_permission_based_mode_appears_on_show_after_save(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->legacyStaffPair();

        $this->actingAs($admin)->patch(route('admin.users.update', $staff), $this->staffUpdatePayload($staff, [
            'staff_permissions' => StaffPermission::presetPermissions(StaffPermission::PresetSupport),
        ]))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $staff))
            ->assertOk()
            ->assertSee('data-testid="staff-access-mode-permissions"', false)
            ->assertSee('Permission-based', false)
            ->assertDontSee('data-testid="staff-access-mode-legacy"', false);
    }

    public function test_staff_permissions_are_not_written_for_non_staff_account_types(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();
        $agent = Agent::query()->where('agency_id', $admin->current_agency_id)->firstOrFail();
        $agentStaff = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $admin->current_agency_id,
            'status' => UserAccountStatus::Active,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => [AgentPermission::BookingsView],
            ],
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $agentStaff), [
            'name' => $agentStaff->name,
            'email' => $agentStaff->email,
            'account_type' => AccountType::AgentStaff->value,
            'status' => UserAccountStatus::Active->value,
            'owner_agent_id' => $agent->id,
            'permissions' => [AgentPermission::WalletView],
            'staff_permissions' => StaffPermission::all(),
            'staff_permissions_configured' => 1,
        ])->assertRedirect(route('admin.users.show', $agentStaff));

        $fresh = $agentStaff->fresh();
        $this->assertArrayNotHasKey('staff_permissions', $fresh->meta ?? []);
        $this->assertEqualsCanonicalizing(
            [AgentPermission::WalletView],
            $fresh->meta['agent_permissions'] ?? [],
        );
    }

    public function test_staff_edit_form_shows_preset_buttons(): void
    {
        [$admin, $staff] = $this->legacyStaffPair();

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $staff));
        $response->assertOk()
            ->assertSee('data-testid="staff-preset-'.StaffPermission::PresetManager.'"', false)
            ->assertSee('data-testid="staff-preset-'.StaffPermission::PresetOperator.'"', false)
            ->assertSee('data-testid="staff-preset-'.StaffPermission::PresetSupport.'"', false)
            ->assertSee('Permission presets', false);
    }

    public function test_platform_admin_edit_form_keeps_read_only_matrix(): void
    {
        [$admin] = $this->platformAdmin();
        $platformUser = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'status' => UserAccountStatus::Active,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $platformUser));
        $response->assertOk()
            ->assertSee('Effective access summary — read-only', false)
            ->assertSee('Platform Admin — full platform access', false);
        $this->assertMatchesRegularExpression('/data-permission-panel="staff"\s+hidden/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-permission-panel="platform_admin"\s+hidden/', $response->getContent());
    }

    public function test_customer_edit_form_hides_permission_matrix(): void
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function staffUpdatePayload(User $staff, array $overrides = []): array
    {
        return array_merge([
            'name' => $staff->name,
            'email' => $staff->email,
            'account_type' => AccountType::Staff->value,
            'status' => UserAccountStatus::Active->value,
            'staff_permissions_configured' => 1,
        ], $overrides);
    }

    /**
     * @return array{0: User, 1: User}
     */
    protected function legacyStaffPair(): array
    {
        [$admin] = $this->platformAdmin();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $admin->current_agency_id,
            'status' => UserAccountStatus::Active,
            'meta' => [],
        ]);

        return [$admin, $staff];
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
