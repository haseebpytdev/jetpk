<?php

namespace Tests\Feature\Jetpk;

use App\Models\ClientPageAsset;
use App\Services\Homepage\JetpkHomepageAssetService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageCmsAssetUploadTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedJetpkAirports();
    }

    public function test_two_point_two_five_megabyte_jpeg_upload_is_accepted_via_page_settings_asset_endpoint(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = \App\Models\User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $routeId = 'route-lhe-dxb-'.uniqid();
        $assetKey = JetpkHomepageAssetService::routeAssetKey($routeId);

        $response = $this->actingAs($admin)->post('/admin/page-settings/home/assets?format=json', [
            'asset_key' => $assetKey,
            'file' => UploadedFile::fake()->create('jp-homepage-route-test-2250kb.jpg', 2250, 'image/jpeg'),
            'alt_text' => 'Trending route test image',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $asset = ClientPageAsset::query()->where('asset_key', $assetKey)->first();
        $this->assertNotNull($asset);
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_route_asset_key_contract_matches_slugified_item_id(): void
    {
        $itemId = 'route-lhe-dxb-abc12';
        $expected = JetpkHomepageAssetService::routeAssetKey($itemId);

        $this->assertSame('route_route_lhe_dxb_abc12', $expected);
    }

    public function test_oversize_image_returns_clear_validation_message(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->makeJetpkProfile();
        $admin = \App\Models\User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)->post('/admin/page-settings/home/assets?format=json', [
            'asset_key' => 'route_test_oversize',
            'file' => UploadedFile::fake()->create('oversize.jpg', 6000, 'image/jpeg'),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('5 MB', (string) $response->json('message'));
    }

    public function test_invalid_mime_is_rejected_with_clear_message(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->makeJetpkProfile();
        $admin = \App\Models\User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)->post('/admin/page-settings/home/assets?format=json', [
            'asset_key' => 'route_test_invalid',
            'file' => UploadedFile::fake()->create('fake.jpg', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('JPG, PNG and WebP', (string) $response->json('message'));
    }
}
