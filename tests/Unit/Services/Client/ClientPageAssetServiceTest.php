<?php

namespace Tests\Unit\Services\Client;

use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Services\Client\ClientPageAssetService;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPageAssetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_store_captures_metadata_before_move(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'jetpk',
            'asset_profile' => 'jetpk-assets',
        ]);

        $file = UploadedFile::fake()->image('hero-banner.jpg', 800, 600);
        $service = app(ClientPageAssetService::class);

        $asset = $service->store($profile, 'home', 'hero_image', $file, null, 'Hero alt');

        $this->assertInstanceOf(ClientPageAsset::class, $asset);
        $this->assertTrue(Storage::disk('public')->exists($asset->path));
        $this->assertSame('image/jpeg', $asset->meta_json['mime']);
        $this->assertGreaterThan(0, $asset->meta_json['size']);
        $this->assertSame('hero-banner.jpg', $asset->meta_json['original_name']);
        $this->assertSame('jpg', $asset->meta_json['extension']);
        $this->assertSame('Hero alt', $asset->alt_text);
        $this->assertStringContainsString('/storage/', $asset->public_url);
    }

    public function test_store_hero_background_triggers_validated_lcp_manifest(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'jetpk',
            'asset_profile' => 'jetpk-assets',
        ]);

        $file = new UploadedFile(
            $this->gradientJpegPath(),
            'hero-dashboard.jpg',
            'image/jpeg',
            null,
            true,
        );
        $service = app(ClientPageAssetService::class);

        $asset = $service->store($profile, 'home', 'hero_background', $file);

        $this->assertSame('hero_background', $asset->asset_key);
        $manifest = $asset->meta_json['hero_lcp'] ?? null;
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('fingerprint', $manifest);
        $this->assertArrayHasKey('variants', $manifest);
        $this->assertArrayHasKey('desktop', $manifest['variants']);
    }

    public function test_store_rejects_unreadable_upload(): void
    {
        $profile = $this->makeProfile();
        $file = UploadedFile::fake()->image('broken.jpg');
        $realPath = $file->getRealPath();
        $this->assertNotFalse($realPath);
        @unlink($realPath);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ClientPageAssetService::class)->store($profile, 'home', 'hero_image', $file);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = []): ClientProfile
    {
        $profile = ClientProfile::query()->create(array_merge([
            'name' => 'Test Client',
            'slug' => 'test-client-'.uniqid(),
            'domain' => null,
            'environment' => 'staging',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'v1-classic',
            'active_staff_theme' => 'v1-classic',
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ], $overrides));

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => false,
            ]);
        }

        return $profile;
    }

    private function gradientJpegPath(): string
    {
        $dir = storage_path('app/testing/hero-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/service-gradient.jpg';
        if (is_file($path)) {
            return $path;
        }

        $img = imagecreatetruecolor(1200, 600);
        for ($x = 0; $x < 1200; $x++) {
            for ($y = 0; $y < 600; $y++) {
                $color = imagecolorallocate($img, ($x + 25) % 255, ($y * 2) % 255, 130);
                imagesetpixel($img, $x, $y, $color);
            }
        }
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }
}
