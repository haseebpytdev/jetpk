<?php

namespace Tests\Feature;

use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use App\Services\Homepage\JetpkHomepageAssetService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageMediaTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedJetpkAirports();
    }

    public function test_valid_jpeg_png_webp_destination_uploads_succeed(): void
    {
        $profile = $this->makeJetpkProfile();
        $service = app(JetpkHomepageAssetService::class);

        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $ext => $mime) {
            $asset = $service->storeDestinationImage(
                $profile,
                'dest-'.$ext,
                UploadedFile::fake()->create('photo.'.$ext, 100, $mime),
            );
            $this->assertStringStartsWith('jetpk/homepage/popular-destinations/', $asset->path);
            Storage::disk('public')->assertExists($asset->path);
            $this->assertStringContainsString('/storage/', $asset->public_url);
        }
    }

    public function test_invalid_mime_and_disguised_executable_are_rejected(): void
    {
        $profile = $this->makeJetpkProfile();
        $service = app(JetpkHomepageAssetService::class);

        $this->expectException(ValidationException::class);
        $service->storeDestinationImage(
            $profile,
            'bad',
            UploadedFile::fake()->create('evil.pdf', 50, 'application/pdf'),
        );
    }

    public function test_oversized_image_is_rejected(): void
    {
        $profile = $this->makeJetpkProfile();
        $service = app(JetpkHomepageAssetService::class);

        $this->expectException(ValidationException::class);
        $service->storeDestinationImage(
            $profile,
            'big',
            UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg'),
        );
    }

    public function test_support_cta_upload_replace_and_remove_via_controller_save(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = \App\Models\User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => [
                'destinations' => ['enabled' => '1', 'items' => []],
                'routes' => ['enabled' => '1', 'items' => []],
                'support_cta' => ['enabled' => '1', 'background_mode' => 'gradient'],
            ],
            'support_cta_background_file' => UploadedFile::fake()->image('banner.jpg'),
        ])->assertRedirect();

        $asset = ClientPageAsset::query()->where('asset_key', 'support_cta_background')->first();
        $this->assertNotNull($asset);
        $oldPath = $asset->path;
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => [
                'destinations' => ['enabled' => '1', 'items' => []],
                'routes' => ['enabled' => '1', 'items' => []],
                'support_cta' => ['enabled' => '1', 'background_mode' => 'uploaded'],
            ],
            'support_cta_background_file' => UploadedFile::fake()->image('banner2.png'),
        ])->assertRedirect();

        $asset->refresh();
        $this->assertNotSame($oldPath, $asset->path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($asset->path);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => [
                'destinations' => ['enabled' => '1', 'items' => []],
                'routes' => ['enabled' => '1', 'items' => []],
                'support_cta' => ['enabled' => '1', 'background_mode' => 'gradient'],
            ],
            'support_cta_background_remove' => '1',
        ])->assertRedirect();

        $this->assertNull(ClientPageAsset::query()->where('asset_key', 'support_cta_background')->first());
    }

    public function test_disguised_executable_with_jpg_extension_is_rejected(): void
    {
        $profile = $this->makeJetpkProfile();
        $service = app(JetpkHomepageAssetService::class);

        $this->expectException(ValidationException::class);
        $service->storeDestinationImage(
            $profile,
            'exe',
            UploadedFile::fake()->create('fake.jpg', 10, 'application/x-php'),
        );
    }

    public function test_fallback_image_path_remains_available_without_uploads(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeValidFourCardHomeContent());

        $destinations = app(\App\Support\Client\JetpkHomepageSectionData::class)->destinationsForDisplay();
        $this->assertStringContainsString('homepage-destination-fallback.svg', $destinations[0]['image']);
        $this->assertFileExists(public_path('themes/frontend/jetpakistan/images/homepage-destination-fallback.svg'));
    }
}
