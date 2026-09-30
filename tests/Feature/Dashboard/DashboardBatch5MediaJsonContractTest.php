<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class DashboardBatch5MediaJsonContractTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Storage::fake('public');
    }

    public function test_platform_admin_can_upload_and_delete_media_json(): void
    {
        $admin = $this->platformAdmin();

        $upload = $this->actingAs($admin)
            ->postJson('/admin/settings/media?format=json', [
                'file' => UploadedFile::fake()->image('qa-media.jpg'),
                'collection' => 'general',
                'alt_text' => 'QA media',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $mediaId = (string) data_get($upload->json(), 'asset.id');
        $this->assertNotSame('', $mediaId);
        $publicUrl = (string) data_get($upload->json(), 'asset.url');
        $this->assertStringContainsString('/storage/', $publicUrl);
        $this->assertStringNotContainsString('..', $publicUrl);
        $publicUrl = (string) data_get($upload->json(), 'asset.url');
        $this->assertStringContainsString('/storage/', $publicUrl);
        $this->assertStringNotContainsString('..', $publicUrl);

        $this->actingAs($admin)
            ->deleteJson('/admin/settings/media/'.$mediaId.'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('deleted_id', $mediaId);
    }

    public function test_media_index_json_for_platform_admin_without_current_agency(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson('/admin/settings/media?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['assets', 'meta' => ['page', 'pageCount', 'pageSize', 'total']]);
    }
}
