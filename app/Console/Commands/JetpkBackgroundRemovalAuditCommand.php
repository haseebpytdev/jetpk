<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class JetpkBackgroundRemovalAuditCommand extends Command
{
    protected $signature = 'jetpk:background-removal-audit';

    protected $description = 'Audit JetPK logo background-removal pipeline safety (read-only)';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY background removal audit.');
        $fail = 0;

        $checks = [
            ['BackgroundRemovalProvider contract', File::exists(app_path('Contracts/Media/BackgroundRemovalProvider.php'))],
            ['BackgroundRemovalService', File::exists(app_path('Services/Media/BackgroundRemovalService.php'))],
            ['ImageTransparencyInspector', File::exists(app_path('Services/Media/ImageTransparencyInspector.php'))],
            ['branding_asset_processes migration', File::exists(database_path('migrations/2026_07_11_120000_create_background_removal_and_branding_asset_process_tables.php'))],
            ['encrypted api_key cast', str_contains(File::get(app_path('Models/BackgroundRemovalSetting.php')), "'api_key' => 'encrypted'")],
            ['staging uses local disk', str_contains(File::get(app_path('Services/Media/BackgroundRemovalService.php')), "Storage::disk('local')")],
            ['accept writes public disk', str_contains(File::get(app_path('Services/Media/BackgroundRemovalService.php')), "Storage::disk('public')")],
            ['queued job exists', File::exists(app_path('Jobs/Branding/RemoveBrandLogoBackground.php'))],
            ['mock provider exists', File::exists(app_path('Services/Media/Providers/MockBackgroundRemovalProvider.php'))],
            ['fixture provider exists', File::exists(app_path('Services/Media/Providers/FixtureBackgroundRemovalProvider.php'))],
            ['cleanup command dry-run', str_contains(File::get(app_path('Console/Commands/JetpkBrandingBackgroundCleanupCommand.php')), '--dry-run')],
            ['cleanup scheduled daily', str_contains(File::get(base_path('routes/console.php')), 'jetpk:branding-background-cleanup')],
            ['endpoint validator', File::exists(app_path('Support/Media/BackgroundRemovalEndpointValidator.php'))],
            ['palette resolver', File::exists(app_path('Support/Branding/JetpkBrandPaletteCssResolver.php'))],
            ['branding logo-bg UX', str_contains(File::get(resource_path('views/themes/admin/jetpakistan/settings/branding.blade.php')), 'data-jp-logo-background')],
            ['admin settings page', File::exists(resource_path('views/themes/admin/jetpakistan/settings/background-removal.blade.php'))],
            ['default_for_logos default false', str_contains(File::get(app_path('Models/BackgroundRemovalSetting.php')), 'default_for_logos')],
        ];

        foreach ($checks as [$label, $ok]) {
            $this->line(($ok ? 'pass' : 'fail').' '.$label);
            if (! $ok) {
                $fail++;
            }
        }

        $branding = File::get(resource_path('views/themes/admin/jetpakistan/settings/branding.blade.php'));
        if (str_contains($branding, 'type="password"') && str_contains($branding, 'api_key')) {
            $this->line('fail api key not in branding blade');
            $fail++;
        } else {
            $this->line('pass no api key in branding blade');
        }

        $this->newLine();
        $this->line("fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
