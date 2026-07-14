<?php

namespace Tests\Feature\Rbac;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Support\Access\RolePermissionMatrix;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacConsistencyAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_active_policy_grants_agency_admin_platform_access(): void
    {
        $legacyAdmin = $this->legacyAgencyAdmin();

        $this->assertFalse(Gate::forUser($legacyAdmin)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($legacyAdmin)->allows('platform.admin'));
    }

    public function test_agency_admin_cannot_access_agent_applications(): void
    {
        $this->actingAs($this->legacyAgencyAdmin())
            ->get(route('admin.agent-applications.index'))
            ->assertForbidden();
    }

    public function test_staff_with_missing_staff_permissions_has_legacy_access(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = $this->legacyStaffUser();

        $this->assertTrue($staff->usesLegacyStaffPermissions());
        $this->assertTrue($staff->hasStaffPermission(StaffPermission::BookingsView));
        $this->assertTrue($staff->hasStaffPermission(StaffPermission::TicketingIssue));

        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();
    }

    public function test_staff_with_empty_staff_permissions_cannot_access_staff_dashboard(): void
    {
        $staff = $this->staffWithPermissions([]);

        $this->actingAs($staff)->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_staff_with_empty_staff_permissions_is_denied_gated_booking_actions(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);
        $staff = $this->staffWithPermissions([]);
        $booking = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::Pending,
        ]);

        $this->actingAs($staff)
            ->patch(route('staff.bookings.status', $booking), ['status' => BookingStatus::Confirmed->value])
            ->assertForbidden();
    }

    public function test_staff_preset_permissions_match_staff_permission_presets(): void
    {
        foreach (StaffPermission::presetKeys() as $preset) {
            $this->assertEqualsCanonicalizing(
                StaffPermission::presetPermissions($preset),
                RolePermissionMatrix::staffPresetPermissions()[$preset] ?? [],
            );
        }
    }

    public function test_staff_with_all_permissions_cannot_access_admin(): void
    {
        $staff = $this->staffWithPermissions(StaffPermission::all());

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.api-settings'))->assertForbidden();
    }

    public function test_roles_permissions_page_marks_agency_admin_as_legacy_disabled(): void
    {
        [$admin] = $this->platformAdmin();

        $response = $this->actingAs($admin)->get(route('admin.roles-permissions'));
        $response->assertOk()
            ->assertSee('Agency admin — legacy (disabled)', false);

        foreach (RolePermissionMatrix::areas() as $row) {
            $this->assertSame(
                RolePermissionMatrix::Denied,
                $row['agency_admin'] ?? RolePermissionMatrix::Denied,
                'Expected agency_admin denied for '.$row['area'],
            );
        }
    }

    public function test_staff_account_dropdown_does_not_expose_admin_bookings_link(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $response = $this->actingAs($staff)->get(route('home'));
        $response->assertOk();
        $this->assertStringNotContainsString('/admin/bookings', $response->getContent());
        $this->assertStringContainsString(route('staff.bookings.index', [], false), $response->getContent());
    }

    public function test_agent_account_dropdown_does_not_expose_admin_links(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $response = $this->actingAs($agent)->get(route('home'));
        $response->assertOk();
        $this->assertStringNotContainsString('/admin/bookings', $response->getContent());
    }

    public function test_legacy_agency_admin_account_dropdown_does_not_expose_admin_bookings(): void
    {
        $legacyAdmin = $this->legacyAgencyAdmin();

        $response = $this->actingAs($legacyAdmin)->get(route('home'));
        $response->assertOk();
        $this->assertStringNotContainsString('/admin/bookings', $response->getContent());
        $this->assertStringContainsString('Legacy account notice', $response->getContent());
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
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

    protected function legacyStaffUser(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $meta = $staff->meta ?? [];
        unset($meta['staff_permissions']);
        $staff->forceFill(['meta' => $meta])->save();

        return $staff->fresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staffWithPermissions(array $permissions): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => Agency::query()->where('slug', 'asif-travels')->firstOrFail()->id,
            'status' => UserAccountStatus::Active,
            'meta' => ['staff_permissions' => $permissions],
        ]);

        return $staff->fresh();
    }
}
