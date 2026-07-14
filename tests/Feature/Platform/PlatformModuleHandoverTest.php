<?php

namespace Tests\Feature\Platform;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Sprint 8R — documents handover invariants (see docs/platform-modules.md).
 */
class PlatformModuleHandoverTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_handover_documentation_exists(): void
    {
        $path = base_path('docs/platform-modules.md');

        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('8M', $contents);
        $this->assertStringContainsString('php artisan test --filter=PlatformModule', $contents);
        $this->assertStringContainsString('Emergency reset', $contents);
    }

    public function test_admin_platform_modules_route_remains_404(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get('/admin/platform/modules')
            ->assertNotFound();
    }
}
