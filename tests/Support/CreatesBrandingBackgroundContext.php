<?php

namespace Tests\Support;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\BackgroundRemovalSetting;
use App\Models\User;
use App\Services\Media\Providers\MockBackgroundRemovalProvider;

trait CreatesBrandingBackgroundContext
{
    protected Agency $brandingAgency;

    protected User $brandingAdmin;

    protected AgencySetting $brandingSettings;

    protected BackgroundRemovalSetting $backgroundRemovalSettings;

    protected function setUpBrandingBackgroundContext(): void
    {
        MockBackgroundRemovalProvider::reset();

        $this->brandingAgency = Agency::factory()->create([
            'slug' => 'jetpk-test-agency',
        ]);

        $this->brandingAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $this->brandingAgency->id,
        ]);

        $this->brandingSettings = AgencySetting::query()->create([
            'agency_id' => $this->brandingAgency->id,
            'display_name' => 'JetPK Test Agency',
            'timezone' => 'Asia/Karachi',
            'country' => 'Pakistan',
            'currency' => 'PKR',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'accent_color' => '#778899',
            'logo_path' => 'agencies/'.$this->brandingAgency->id.'/branding/active-logo.png',
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put(
            $this->brandingSettings->logo_path,
            $this->makeOpaquePngBytes(80, 40),
        );

        $this->backgroundRemovalSettings = BackgroundRemovalSetting::query()->create([
            'agency_id' => $this->brandingAgency->id,
            'provider' => 'mock',
            'is_enabled' => true,
            'default_for_logos' => false,
            'timeout_seconds' => 15,
            'max_source_bytes' => 5_242_880,
            'max_source_pixels' => 16_777_216,
        ]);
    }

    protected function enableMockProvider(callable $handler): void
    {
        MockBackgroundRemovalProvider::$handler = $handler;
        $this->backgroundRemovalSettings->forceFill([
            'provider' => 'mock',
            'is_enabled' => true,
        ])->save();
    }

    protected function disableBackgroundRemovalProvider(): void
    {
        MockBackgroundRemovalProvider::reset();
        $this->backgroundRemovalSettings->forceFill([
            'provider' => 'disabled',
            'is_enabled' => false,
        ])->save();
    }

    protected function makeOpaquePngBytes(int $width = 64, int $height = 64): string
    {
        $img = imagecreatetruecolor($width, $height);
        $blue = imagecolorallocate($img, 20, 60, 180);
        imagefill($img, 0, 0, $blue);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    protected function makeTransparentLogoPngBytes(int $width = 64, int $height = 64): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        $orange = imagecolorallocatealpha($img, 234, 122, 30, 0);
        imagefilledellipse($img, (int) ($width / 2), (int) ($height / 2), (int) ($width * 0.6), (int) ($height * 0.6), $orange);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    protected function makeFullyTransparentPngBytes(int $width = 32, int $height = 32): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    protected function writeTempPng(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jp-test-png-');
        $this->assertNotFalse($path);
        $pngPath = $path.'.png';
        @unlink($path);
        file_put_contents($pngPath, $bytes);

        return $pngPath;
    }
}
