<?php

namespace Tests\Feature\Jetpk;

use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use App\Services\Homepage\JetpkHomepageAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageMediaOptimizeCommandTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    public function test_dry_run_reports_plan_without_writing_derivatives(): void
    {
        Storage::fake('public');
        $profile = $this->makeJetpkProfile();
        $asset = $this->storeDestinationWithoutDerivatives($profile);

        $beforeMeta = $asset->fresh()->meta_json;
        $exit = Artisan::call('jetpk:homepage-media-optimize', [
            '--dry-run' => true,
            '--profile' => 'jetpk',
            '--asset' => $asset->asset_key,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('DRY', $output);
        $this->assertStringContainsString('writes=no', $output);
        $this->assertSame($beforeMeta, $asset->fresh()->meta_json);
    }

    public function test_refuses_non_jetpk_profile(): void
    {
        $other = ClientProfile::query()->create([
            'name' => 'Other Agency',
            'slug' => 'other-agency',
            'asset_profile' => 'other-agency',
            'is_active' => true,
        ]);

        $exit = Artisan::call('jetpk:homepage-media-optimize', [
            '--dry-run' => true,
            '--profile' => $other->slug,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Refusing cross-client', Artisan::output());
    }

    public function test_write_mode_stores_responsive_media_and_keeps_original_url(): void
    {
        Storage::fake('public');
        $profile = $this->makeJetpkProfile();
        $asset = $this->storeDestinationWithoutDerivatives($profile);
        $originalUrl = $asset->public_url;

        $exit = Artisan::call('jetpk:homepage-media-optimize', [
            '--profile' => 'jetpk',
            '--asset' => $asset->asset_key,
        ]);

        $this->assertSame(0, $exit);
        $fresh = $asset->fresh();
        $this->assertSame($originalUrl, $fresh->public_url);
        $this->assertIsArray($fresh->meta_json['responsive_media'] ?? null);
        $this->assertArrayHasKey('variants', $fresh->meta_json['responsive_media']);
        $this->assertArrayHasKey('jpg', $fresh->meta_json['responsive_media']['variants']['320'] ?? []);
    }

    public function test_upload_stores_responsive_media_for_photographic_png(): void
    {
        Storage::fake('public');
        $profile = $this->makeJetpkProfile();
        $asset = app(JetpkHomepageAssetService::class)->storeDestinationImage(
            $profile,
            'dxb',
            $this->photographicUpload('dest.png', 960, 640),
            null,
            'Dubai',
        );

        $this->assertStringContainsString('/storage/', $asset->public_url);
        $this->assertIsArray($asset->meta_json['responsive_media'] ?? null);
        $this->assertSame('card', $asset->meta_json['responsive_media']['profile'] ?? null);
        Storage::disk('public')->assertExists($asset->path);
    }

    private function storeDestinationWithoutDerivatives(ClientProfile $profile): ClientPageAsset
    {
        $upload = $this->photographicUpload('dest.jpg', 900, 600);
        $relative = 'jetpk/homepage/popular-destinations/jetpk-assets/legacy-dest.jpg';
        Storage::disk('public')->put($relative, file_get_contents($upload->getRealPath()));

        return ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => 'home',
            'asset_key' => 'destination_dxb',
            'disk' => 'public',
            'path' => $relative,
            'public_url' => Storage::disk('public')->url($relative),
            'alt_text' => 'Dubai',
            'meta_json' => [
                'mime' => 'image/jpeg',
                'size' => Storage::disk('public')->size($relative),
                'extension' => 'jpg',
            ],
        ]);
    }

    private function photographicUpload(string $filename, int $width, int $height): UploadedFile
    {
        $dir = storage_path('app/testing/responsive-media-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/'.$filename;
        $img = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorallocate($img, $x % 255, (90 + $x) % 255, (180 - ($x % 90) + 40) % 255);
            imageline($img, $x, 0, $x, $height - 1, $color);
        }
        if (str_ends_with(strtolower($filename), '.png')) {
            imagepng($img, $path);
        } else {
            imagejpeg($img, $path, 90);
        }
        imagedestroy($img);

        return new UploadedFile($path, $filename, mime_content_type($path) ?: 'image/jpeg', null, true);
    }
}
