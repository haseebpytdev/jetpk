<?php

namespace Tests\Feature\Rbac;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\SupplierConnection;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class LegacyAgencyAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_admin_login_redirects_to_legacy_notice(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::AgencyAdmin,
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('account.legacy', absolute: false));
    }

    public function test_agency_admin_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->legacyAgencyAdmin())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_agency_admin_cannot_access_admin_users(): void
    {
        $this->actingAs($this->legacyAgencyAdmin())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_agency_admin_cannot_access_admin_api_settings(): void
    {
        $this->actingAs($this->legacyAgencyAdmin())
            ->get(route('admin.api-settings'))
            ->assertForbidden();
    }

    public function test_agency_admin_cannot_access_system_health(): void
    {
        $this->actingAs($this->legacyAgencyAdmin())
            ->get(route('admin.system-health'))
            ->assertForbidden();
    }

    public function test_agency_admin_cannot_access_deployment_checklist(): void
    {
        $this->actingAs($this->legacyAgencyAdmin())
            ->get(route('admin.deployment-checklist'))
            ->assertForbidden();
    }

    public function test_agency_admin_cannot_access_go_live_checklist(): void
    {
        $this->actingAs($this->legacyAgencyAdmin())
            ->get(route('admin.go-live-checklist'))
            ->assertForbidden();
    }

    public function test_agency_admin_cannot_manage_users_through_policy(): void
    {
        $legacyAdmin = $this->legacyAgencyAdmin();
        $target = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $legacyAdmin->current_agency_id,
            'status' => UserAccountStatus::Active,
        ]);

        $this->assertFalse(Gate::forUser($legacyAdmin)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($legacyAdmin)->allows('view', $target));
        $this->assertFalse(Gate::forUser($legacyAdmin)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($legacyAdmin)->allows('update', $target));
    }

    public function test_agency_admin_cannot_access_supplier_connections_through_policy(): void
    {
        $legacyAdmin = $this->legacyAgencyAdmin();

        $this->assertFalse(Gate::forUser($legacyAdmin)->allows('viewAny', SupplierConnection::class));
        $this->assertFalse(Gate::forUser($legacyAdmin)->allows('create', SupplierConnection::class));
    }

    protected function legacyAgencyAdmin(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        if ($admin->account_type !== AccountType::AgencyAdmin) {
            $admin->forceFill(['account_type' => AccountType::AgencyAdmin])->save();
            $admin = $admin->fresh();
        }

        return $admin;
    }
}
