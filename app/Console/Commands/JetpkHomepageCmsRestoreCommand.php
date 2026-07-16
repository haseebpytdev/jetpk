<?php

namespace App\Console\Commands;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Homepage\JetpkHomepageContentRestoreService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe JetPK homepage CMS section-scoped repair with backup and rollback.
 */
class JetpkHomepageCmsRestoreCommand extends Command
{
    private const BACKUP_ROOT = 'app/audits/jetpk-homepage-cms-restore';

    protected $signature = 'jetpk:homepage-cms-restore
                            {--profile=jetpk : Client profile slug}
                            {--dry-run : List proposed field-level repairs without writing}
                            {--apply : Apply repairs to draft and published homepage rows}
                            {--rollback= : Restore from a backup stamp directory name or full path}';

    protected $description = 'Repair blanked JetPK homepage CMS fields with section-scoped merge, backup, and rollback';

    public function handle(JetpkHomepageContentRestoreService $restoreService): int
    {
        if ($rollback = $this->option('rollback')) {
            return $this->runRollback((string) $rollback);
        }

        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');

        $this->line('Classification: '.($dryRun ? 'DRY-RUN' : 'WRITE').' JetPK homepage CMS restore.');
        $this->line('db_write_attempted='.($dryRun ? 'false' : 'true'));
        $this->newLine();

        if (! Schema::hasTable('client_page_settings')) {
            $this->error('client_page_settings table missing');

            return self::FAILURE;
        }

        $profile = ClientProfile::query()
            ->where('slug', (string) $this->option('profile'))
            ->where('is_master_profile', false)
            ->first();

        if ($profile === null) {
            $this->error('Client profile not found for slug: '.$this->option('profile'));

            return self::FAILURE;
        }

        $draft = $this->row($profile->id, ClientPageSettingStatus::Draft);
        $published = $this->row($profile->id, ClientPageSettingStatus::Published);
        $baseContent = is_array($draft?->content_json)
            ? $draft->content_json
            : (is_array($published?->content_json) ? $published->content_json : []);

        if ($baseContent === []) {
            $this->error('No homepage draft or published content found for profile '.$profile->slug);

            return self::FAILURE;
        }

        $changes = $restoreService->buildChangePlan($baseContent);
        $repaired = $restoreService->applyChangePlan($baseContent, $changes);
        $changeCount = count(array_filter($changes, static fn (array $row): bool => ($row['action'] ?? '') === 'CHANGED'));

        foreach ($changes as $change) {
            $this->line('PATH: '.$change['path']);
            $this->line('CURRENT VALUE: '.$this->displayValue($change['current'] ?? null));
            $this->line('PROPOSED VALUE: '.$this->displayValue($change['proposed'] ?? null));
            $this->line('SOURCE OF DEFAULT: '.$change['source']);
            $this->line('PRESERVED OR CHANGED: '.$change['action']);
            $this->line('RISK: '.$change['risk']);
            $this->newLine();
        }

        $this->info("Proposed changes: {$changeCount}");

        if ($dryRun) {
            $this->line('Dry-run complete. Re-run with --apply after review.');

            return self::SUCCESS;
        }

        $stamp = now()->utc()->format('Ymd\THis\Z');
        $backupDir = storage_path(self::BACKUP_ROOT.'/'.$stamp);
        File::ensureDirectoryExists($backupDir);

        $this->writeBackup($backupDir, $profile, $draft, $published, $baseContent, $changes, $repaired);

        foreach ([ClientPageSettingStatus::Draft, ClientPageSettingStatus::Published] as $status) {
            $row = $this->row($profile->id, $status);
            if ($row === null) {
                continue;
            }
            $row->content_json = $repaired;
            $row->save();
        }

        $this->info("Applied {$changeCount} field repairs to draft and published rows.");
        $this->line('Backup: '.$backupDir);
        $this->line('Rollback: php artisan jetpk:homepage-cms-restore --profile='.$profile->slug.' --rollback='.$stamp);

        return self::SUCCESS;
    }

    private function runRollback(string $target): int
    {
        $this->line('Classification: WRITE homepage CMS rollback.');
        $this->line('db_write_attempted=true');
        $this->newLine();

        $backupDir = is_dir($target)
            ? $target
            : storage_path(self::BACKUP_ROOT.'/'.trim($target, '/'));

        $rollbackPath = $backupDir.'/rollback.json';
        if (! is_file($rollbackPath)) {
            $this->error('Rollback file not found: '.$rollbackPath);

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($rollbackPath), true);
        if (! is_array($payload)) {
            $this->error('Invalid rollback JSON');

            return self::FAILURE;
        }

        $profileId = (int) ($payload['client_profile_id'] ?? 0);
        foreach (['draft', 'published'] as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                continue;
            }
            $status = $key === 'draft' ? ClientPageSettingStatus::Draft : ClientPageSettingStatus::Published;
            $row = ClientPageSetting::query()
                ->where('client_profile_id', $profileId)
                ->where('page_key', ClientPageKeys::HOME)
                ->where('status', $status)
                ->first();
            if ($row === null) {
                continue;
            }
            $row->content_json = $payload[$key]['content_json'] ?? $row->content_json;
            $row->save();
        }

        $this->info('Rollback applied from '.$backupDir);

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $repaired
     */
    private function writeBackup(
        string $backupDir,
        ClientProfile $profile,
        ?ClientPageSetting $draft,
        ?ClientPageSetting $published,
        array $baseContent,
        array $changes,
        array $repaired,
    ): void {
        $assets = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->orderBy('asset_key')
            ->get()
            ->map(static fn (ClientPageAsset $asset): array => $asset->toArray())
            ->all();

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'client_profile_id' => $profile->id,
            'client_slug' => $profile->slug,
            'page_key' => ClientPageKeys::HOME,
            'content_sha256_before' => hash('sha256', json_encode($baseContent)),
            'content_sha256_after' => hash('sha256', json_encode($repaired)),
            'draft' => $draft?->toArray(),
            'published' => $published?->toArray(),
            'assets' => $assets,
            'proposed_changes' => $changes,
        ];

        File::put($backupDir.'/homepage-cms-snapshot.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($backupDir.'/proposed-changes.json', json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $rollback = [
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'draft' => $draft ? ['id' => $draft->id, 'content_json' => $draft->content_json] : null,
            'published' => $published ? ['id' => $published->id, 'content_json' => $published->content_json] : null,
        ];
        File::put($backupDir.'/rollback.json', json_encode($rollback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put(
            $backupDir.'/rollback.sh',
            implode("\n", [
                '#!/usr/bin/env bash',
                'set -euo pipefail',
                'php artisan jetpk:homepage-cms-restore --profile='.$profile->slug.' --rollback='.basename($backupDir),
            ])."\n",
        );

        $homepageHtml = $this->fetchHomepageHtml();
        if ($homepageHtml !== null) {
            File::put($backupDir.'/homepage-rendered.html', $homepageHtml);
        }
    }

    private function fetchHomepageHtml(): ?string
    {
        try {
            $url = rtrim((string) config('app.url', 'http://127.0.0.1'), '/').'/';
            $response = Http::timeout(15)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function row(int $profileId, ClientPageSettingStatus $status): ?ClientPageSetting
    {
        return ClientPageSetting::query()
            ->where('client_profile_id', $profileId)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', $status)
            ->first();
    }

    private function displayValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        return (string) $value;
    }
}
