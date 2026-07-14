<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepagePermissionsTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota-developer.enabled' => true]);
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->makeJetpkProfile();
        $this->agency = $this->seedJetpkAgency();
    }

    private \App\Models\Agency $agency;

    public function test_platform_admin_is_allowed(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->get('/admin/page-settings/home')
            ->assertOk();
    }

    public function test_staff_with_explicit_page_settings_permission_is_allowed(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::PageSettingsManage]);

        $this->actingAs($staff)
            ->get('/admin/page-settings/home')
            ->assertOk()
            ->assertSee('dashboard.css', false);
    }

    public function test_staff_with_unrelated_permission_is_denied(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff)
            ->get('/admin/page-settings/home')
            ->assertForbidden();
    }

    public function test_staff_with_empty_permission_array_is_denied(): void
    {
        $staff = $this->staffWithPermissions([]);

        $this->actingAs($staff)
            ->get('/admin/page-settings/home')
            ->assertForbidden();
    }

    public function test_staff_with_missing_meta_staff_permissions_is_denied(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($staff)
            ->get('/admin/page-settings/home')
            ->assertForbidden();
    }

    public function test_staff_with_null_meta_staff_permissions_is_denied(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->agency->id,
            'meta' => ['staff_permissions' => null],
        ]);

        $this->actingAs($staff)
            ->get('/admin/page-settings/home')
            ->assertForbidden();
    }

    public function test_agency_admin_is_denied(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::AgencyAdmin,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/page-settings/home')
            ->assertForbidden();
    }

    public function test_agent_is_denied(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($user)->get('/admin/page-settings/home')->assertForbidden();
    }

    public function test_agent_staff_is_denied(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($user)->get('/admin/page-settings/home')->assertForbidden();
    }

    public function test_customer_is_denied(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($user)->get('/admin/page-settings/home')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/page-settings/home')->assertRedirect('/login');
    }

    public function test_authorized_staff_uses_admin_dashboard_shell(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::PageSettingsManage]);

        $this->actingAs($staff)
            ->get('/admin/page-settings/home')
            ->assertOk()
            ->assertSee('Edit Home', false)
            ->assertSee('dashboard.css', false);
    }

    public function test_legacy_staff_without_explicit_permission_is_denied_even_if_has_staff_permission_would_pass_globally(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->assertTrue($staff->hasStaffPermission(StaffPermission::BookingsView));
        $this->assertFalse($staff->hasExplicitStaffPermission(StaffPermission::PageSettingsManage));

        $this->actingAs($staff)
            ->get('/admin/page-settings/home')
            ->assertForbidden();
    }

    public function test_all_page_settings_actions_require_explicit_permission(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $profile = $this->makeJetpkProfile();
        $denied = $this->staffWithPermissions([StaffPermission::BookingsView]);
        $allowed = $this->staffWithPermissions([StaffPermission::PageSettingsManage]);

        $asset = ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => 'home',
            'asset_key' => 'support_cta_background',
            'disk' => 'public',
            'path' => 'jetpk/homepage/test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'uploaded_by' => null,
        ]);

        $minimalContent = [
            'content' => [
                'routes' => ['enabled' => '1', 'items' => []],
            ],
        ];

        $calls = [
            fn () => $this->get('/admin/page-settings'),
            fn () => $this->get('/admin/page-settings/home'),
            fn () => $this->patch('/admin/page-settings/home', $minimalContent),
            fn () => $this->post('/admin/page-settings/home/publish'),
            fn () => $this->post('/admin/page-settings/home/preview'),
            fn () => $this->post('/admin/page-settings/home/refresh-fares'),
            fn () => $this->post('/admin/page-settings/home/assets', [
                'asset_key' => 'hero_image',
                'file' => UploadedFile::fake()->image('hero.jpg'),
            ]),
            fn () => $this->delete('/admin/page-settings/home/assets/'.$asset->id),
            fn () => $this->get('/admin/page-settings/palette'),
            fn () => $this->post('/admin/page-settings/palette/generate', ['logo_path' => 'client-assets/jetpk/logo/logo.svg']),
            fn () => $this->post('/admin/page-settings/palette/apply'),
        ];

        foreach ($calls as $call) {
            $this->actingAs($denied);
            $response = $call();
            $this->assertContains($response->getStatusCode(), [403, 302], 'Denied staff should not mutate page settings');
            if ($response->getStatusCode() === 302) {
                $this->assertStringNotContainsString('/admin/page-settings', (string) $response->headers->get('Location'));
            }
        }

        foreach ($calls as $call) {
            $this->actingAs($allowed);
            $response = $call();
            $this->assertNotSame(403, $response->getStatusCode(), 'Authorized staff should pass page-settings gate');
        }
    }

    public function test_page_settings_gate_is_client_page_settings_manage(): void
    {
        $this->assertTrue(Gate::has('client.page-settings.manage'));
        $this->assertFalse(Gate::has('homepage.settings.manage'));
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staffWithPermissions(array $permissions): User
    {
        return User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->agency->id,
            'meta' => ['staff_permissions' => $permissions],
        ]);
    }
}
