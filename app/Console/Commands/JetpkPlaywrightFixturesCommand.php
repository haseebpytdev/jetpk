<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Creates or destroys local Playwright QA fixtures (non-production).
 */
class JetpkPlaywrightFixturesCommand extends Command
{
    protected $signature = 'jetpk:playwright-fixtures
                            {--create : Create demo users and minimal records}
                            {--destroy : Remove demo users created by this command}
                            {--clear-branding-logo : Temporarily clear agency logo for fallback QA}
                            {--restore-branding-logo : Restore agency logo from QA backup}
                            {--enable-bg-removal-fixture : Enable local fixture background-removal provider for Playwright}
                            {--restore-bg-removal-settings : Restore background-removal settings from QA backup}
                            {--force : Allow on production (still no supplier mutations)}';

    protected $description = 'Create or destroy JetPK Playwright QA fixtures (local/testing only)';

    private const BACKUP_PATH = 'playwright/jetpk-9h-b/branding-backup.json';

    private const BG_REMOVAL_BACKUP_PATH = 'playwright/jetpk-9h-c2/bg-removal-backup.json';

    /** @var list<string> */
    private const DEMO_EMAILS = [
        'admin@ota.demo',
        'staff@ota.demo',
        'agent@ota.demo',
        'agent.staff@demo.ota',
        'customer@ota.demo',
    ];

    public function handle(): int
    {
        if (
            ! $this->option('create')
            && ! $this->option('destroy')
            && ! $this->option('clear-branding-logo')
            && ! $this->option('restore-branding-logo')
            && ! $this->option('enable-bg-removal-fixture')
            && ! $this->option('restore-bg-removal-settings')
        ) {
            $this->error('Specify --create, --destroy, --clear-branding-logo, --restore-branding-logo, --enable-bg-removal-fixture, or --restore-bg-removal-settings');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing fixture changes in production without --force');

            return self::FAILURE;
        }

        if ($this->option('clear-branding-logo')) {
            return $this->clearBrandingLogo();
        }

        if ($this->option('restore-branding-logo')) {
            return $this->restoreBrandingLogo();
        }

        if ($this->option('enable-bg-removal-fixture')) {
            return $this->enableBackgroundRemovalFixture();
        }

        if ($this->option('restore-bg-removal-settings')) {
            return $this->restoreBackgroundRemovalSettings();
        }

        if ($this->option('destroy')) {
            $deleted = User::query()->whereIn('email', self::DEMO_EMAILS)->delete();
            $this->line("destroyed_users={$deleted}");

            return self::SUCCESS;
        }

        Artisan::call('jetpk:seed-demo-users', ['--skip-devcp' => true], $this->output);
        $this->installQaBrandingLogo();

        $this->newLine();
        $this->line('Fixture credentials (local only):');
        $this->table(['role', 'email', 'password'], [
            ['admin', 'admin@ota.demo', 'password'],
            ['staff', 'staff@ota.demo', 'password'],
            ['agent', 'agent@ota.demo', 'password'],
            ['agent_staff', 'agent.staff@demo.ota', 'password'],
            ['customer', 'customer@ota.demo', 'password'],
        ]);

        $this->line('supplier_mutations=false payment_mutations=false email_sent=false');
        $this->line('qa_branding_logo=installed');

        return self::SUCCESS;
    }

    private function installQaBrandingLogo(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->first();
        if ($agency === null) {
            $this->warn('qa_branding_logo=skipped (agency missing)');

            return;
        }

        $settings = AgencySetting::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            ['display_name' => $agency->name, 'timezone' => 'Asia/Karachi', 'country' => 'Pakistan', 'currency' => 'PKR'],
        );

        $path = "agencies/{$agency->id}/branding/jetpk-qa-9hb.png";
        if (! Storage::disk('public')->exists($path)) {
            $png = $this->generateQaLogoPng();
            Storage::disk('public')->put($path, $png);
        }

        $backup = [
            'agency_id' => $agency->id,
            'logo_path' => $settings->logo_path,
            'favicon_path' => $settings->favicon_path,
            'qa_logo_path' => $path,
        ];
        File::ensureDirectoryExists(storage_path('app/playwright/jetpk-9h-b'));
        File::put(storage_path('app/'.self::BACKUP_PATH), json_encode($backup, JSON_PRETTY_PRINT));

        $settings->logo_path = $path;
        $settings->save();

        $this->mirrorPublicDiskFile($path);

        $this->line('qa_branding_logo_path='.$path);
    }

    private function mirrorPublicDiskFile(string $path): void
    {
        $source = storage_path('app/public/'.$path);
        if (! is_file($source)) {
            return;
        }

        $target = public_path('storage/'.$path);
        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);
    }

    private function clearBrandingLogo(): int
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->first();
        if ($agency === null) {
            $this->error('Agency missing');

            return self::FAILURE;
        }

        $settings = AgencySetting::query()->where('agency_id', $agency->id)->first();
        if ($settings === null) {
            $this->error('Agency settings missing');

            return self::FAILURE;
        }

        $backupPath = storage_path('app/'.self::BACKUP_PATH);
        if (! File::exists($backupPath)) {
            File::ensureDirectoryExists(dirname($backupPath));
            File::put($backupPath, json_encode([
                'agency_id' => $agency->id,
                'logo_path' => $settings->logo_path,
                'favicon_path' => $settings->favicon_path,
                'qa_logo_path' => null,
            ], JSON_PRETTY_PRINT));
        }

        $settings->logo_path = null;
        $settings->save();

        $publicMirror = public_path('storage/agencies/'.$agency->id.'/branding/jetpk-qa-9hb.png');
        if (is_file($publicMirror)) {
            File::delete($publicMirror);
        }

        Artisan::call('optimize:clear');
        $this->line('qa_branding_logo=cleared');

        return self::SUCCESS;
    }

    private function restoreBrandingLogo(): int
    {
        $backupPath = storage_path('app/'.self::BACKUP_PATH);
        if (! File::exists($backupPath)) {
            $this->installQaBrandingLogo();

            return self::SUCCESS;
        }

        /** @var array{agency_id: int, logo_path: ?string, favicon_path: ?string, qa_logo_path: ?string} $backup */
        $backup = json_decode(File::get($backupPath), true);
        $settings = AgencySetting::query()->where('agency_id', $backup['agency_id'])->first();
        if ($settings === null) {
            $this->error('Agency settings missing');

            return self::FAILURE;
        }

        $restorePath = $backup['qa_logo_path'] ?? $backup['logo_path'];
        if ($restorePath !== null && Storage::disk('public')->exists($restorePath)) {
            $settings->logo_path = $restorePath;
        } else {
            $this->installQaBrandingLogo();

            return self::SUCCESS;
        }

        $settings->save();
        if ($restorePath !== null) {
            $this->mirrorPublicDiskFile($restorePath);
        }
        $this->line('qa_branding_logo=restored');

        return self::SUCCESS;
    }

    private function generateQaLogoPng(): string
    {
        $img = imagecreatetruecolor(160, 48);
        if ($img === false) {
            throw new \RuntimeException('Unable to allocate QA branding image');
        }

        $blue = imagecolorallocate($img, 0, 82, 204);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $blue);
        imagestring($img, 3, 12, 16, 'JETPK QA', $white);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    private function enableBackgroundRemovalFixture(): int
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->first();
        if ($agency === null) {
            $this->error('Agency missing');

            return self::FAILURE;
        }

        $setting = \App\Models\BackgroundRemovalSetting::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            [
                'provider' => 'test_fixture',
                'timeout_seconds' => 30,
                'max_source_bytes' => 5_242_880,
                'max_source_pixels' => 16_777_216,
                'is_enabled' => false,
                'default_for_logos' => false,
            ],
        );

        File::ensureDirectoryExists(storage_path('app/playwright/jetpk-9h-c2'));
        File::put(storage_path('app/'.self::BG_REMOVAL_BACKUP_PATH), json_encode([
            'agency_id' => $agency->id,
            'provider' => $setting->provider,
            'is_enabled' => $setting->is_enabled,
            'default_for_logos' => $setting->default_for_logos,
            'timeout_seconds' => $setting->timeout_seconds,
        ], JSON_PRETTY_PRINT));

        $setting->forceFill([
            'provider' => 'test_fixture',
            'is_enabled' => true,
            'default_for_logos' => true,
            'timeout_seconds' => 30,
        ])->save();

        config(['background-removal.force_fixture_provider' => true]);
        $this->line('bg_removal_fixture=enabled provider=test_fixture');

        return self::SUCCESS;
    }

    private function restoreBackgroundRemovalSettings(): int
    {
        $backupPath = storage_path('app/'.self::BG_REMOVAL_BACKUP_PATH);
        if (! File::exists($backupPath)) {
            $this->line('bg_removal_fixture=noop');

            return self::SUCCESS;
        }

        /** @var array{agency_id: int, provider: string, is_enabled: bool, default_for_logos: bool, timeout_seconds: int} $backup */
        $backup = json_decode(File::get($backupPath), true);
        $setting = \App\Models\BackgroundRemovalSetting::query()->where('agency_id', $backup['agency_id'])->first();
        if ($setting !== null) {
            $setting->forceFill([
                'provider' => $backup['provider'],
                'is_enabled' => $backup['is_enabled'],
                'default_for_logos' => $backup['default_for_logos'],
                'timeout_seconds' => $backup['timeout_seconds'],
            ])->save();
        }

        $this->line('bg_removal_fixture=restored');

        return self::SUCCESS;
    }
}
