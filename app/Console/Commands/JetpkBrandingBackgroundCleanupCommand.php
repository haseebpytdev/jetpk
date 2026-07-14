<?php

namespace App\Console\Commands;

use App\Enums\BrandingAssetProcessStatus;
use App\Models\AgencySetting;
use App\Models\BrandingAssetProcess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

class JetpkBrandingBackgroundCleanupCommand extends Command
{
    protected $signature = 'jetpk:branding-background-cleanup {--dry-run : Report without deleting files}';

    protected $description = 'Remove expired branding background-removal staging files';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $removedFiles = 0;
        $expiredRows = 0;
        $skippedActiveLogo = 0;

        $activeLogoPaths = AgencySetting::query()
            ->whereNotNull('logo_path')
            ->pluck('logo_path', 'agency_id')
            ->all();

        $processes = BrandingAssetProcess::query()
            ->where('expires_at', '<', now())
            ->whereIn('status', [
                BrandingAssetProcessStatus::Pending,
                BrandingAssetProcessStatus::Failed,
                BrandingAssetProcessStatus::Discarded,
                BrandingAssetProcessStatus::Completed,
            ])
            ->where('status', '!=', BrandingAssetProcessStatus::Accepted)
            ->limit(500)
            ->get();

        foreach ($processes as $process) {
            $expiredRows++;
            foreach ([$process->source_path, $process->result_path] as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }

                $agencyActivePath = $activeLogoPaths[$process->agency_id] ?? null;
                if ($agencyActivePath !== null && str_contains($path, (string) $agencyActivePath)) {
                    $skippedActiveLogo++;

                    continue;
                }

                if (! Storage::disk('local')->exists($path)) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("would_delete path={$path} process={$process->uuid}");
                    $removedFiles++;

                    continue;
                }

                Storage::disk('local')->delete($path);
                $removedFiles++;
            }

            if (! $dryRun) {
                $process->forceFill(['status' => BrandingAssetProcessStatus::Expired])->save();
            }
        }

        $this->line('dry_run='.($dryRun ? 'true' : 'false'));
        $this->line("staging_files_affected={$removedFiles} processes_expired={$expiredRows} skipped_active_logo={$skippedActiveLogo}");

        return self::SUCCESS;
    }

    public static function isScheduled(): bool
    {
        return collect(Schedule::events())
            ->contains(fn ($event): bool => str_contains((string) ($event->command ?? ''), 'jetpk:branding-background-cleanup'));
    }
}
