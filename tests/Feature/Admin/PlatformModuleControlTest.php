<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PlatformModuleControlTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_admin_platform_modules_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('admin.platform.modules.index'));

        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get('/admin/platform/modules')->assertNotFound();
    }

    public function test_settings_hub_does_not_list_platform_module_control_card(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('Platform Module Control');
    }

    public function test_legacy_agency_admin_cannot_access_removed_admin_route(): void
    {
        $legacy = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($legacy)->get('/admin/platform/modules')->assertNotFound();
    }

    public function test_staff_cannot_access_removed_admin_route(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->actingAs(User::query()->where('email', 'staff@ota.demo')->firstOrFail())
            ->get('/admin/platform/modules')
            ->assertNotFound();
    }
}
