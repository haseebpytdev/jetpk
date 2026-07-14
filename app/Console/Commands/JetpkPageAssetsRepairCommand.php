<?php

namespace App\Console\Commands;

use App\Models\ClientPageAsset;
use App\Support\Client\ClientPageMediaConsumption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Repairs JetPK page assets stored under legacy app/public paths by copying to the public disk.
 */
class JetpkPageAssetsRepairCommand extends Command
{
    protected $signature = 'jetpk:page-assets-repair
                            {--apply : Copy files and update database records}
                            {--dry-run : Report only; default when --apply is omitted}
                            {--client=jetpk : Client profile slug scope}';

    protected $description = 'Repair JetPK page asset paths (legacy public/ → storage/app/public/)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line('Classification: '.($apply ? 'MUTATING' : 'DRY-RUN').' JetPK page asset repair.');
        $this->line('db_write_attempted='.($apply ? 'true' : 'false'));
        $this->newLine();

        if (! Schema::hasTable('client_page_assets')) {
            $this->error('client_page_assets table missing');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $pass = 0;
        $warn = 0;
        $fail = 0;
        $rows = [];

        $assets = ClientPageAsset::query()->orderBy('id')->get();

        foreach ($assets as $asset) {
            $path = trim((string) $asset->path);
            if ($path === '') {
                $rows[] = [$asset->id, $asset->page_key, $asset->asset_key, '—', '—', '—', 'fail', 'empty path'];
                $fail++;

                continue;
            }

            $legacyPath = public_path($path);
            $targetPath = $path;
            $inStorage = $disk->exists($targetPath);
            $inLegacy = is_file($legacyPath) && is_readable($legacyPath);
            $targetUrl = $disk->url($targetPath);

            if ($inStorage) {
                $newUrl = $disk->url($targetPath);
                $needsUrlUpdate = (string) $asset->public_url !== $newUrl;
                $status = $needsUrlUpdate ? 'warn' : 'pass';
                $note = $needsUrlUpdate ? 'refresh public_url' : 'already canonical';
                if ($needsUrlUpdate) {
                    $warn++;
                    if ($apply) {
                        $asset->forceFill(['public_url' => $newUrl, 'disk' => 'public'])->save();
                    }
                } else {
                    $pass++;
                }
                $rows[] = [$asset->id, $asset->page_key, $asset->asset_key, $targetPath, $targetPath, $targetUrl, $status, $note];

                continue;
            }

            if (! $inLegacy) {
                $rows[] = [$asset->id, $asset->page_key, $asset->asset_key, $legacyPath, $targetPath, $targetUrl, 'fail', 'missing source file'];
                $fail++;

                continue;
            }

            if ($apply) {
                File::ensureDirectoryExists(dirname($disk->path($targetPath)));
                $copied = copy($legacyPath, $disk->path($targetPath));
                if (! $copied || ! $disk->exists($targetPath)) {
                    $rows[] = [$asset->id, $asset->page_key, $asset->asset_key, $legacyPath, $targetPath, $targetUrl, 'fail', 'copy failed'];
                    $fail++;

                    continue;
                }

                $asset->forceFill([
                    'disk' => 'public',
                    'path' => $targetPath,
                    'public_url' => $disk->url($targetPath),
                ])->save();
            }

            $rows[] = [$asset->id, $asset->page_key, $asset->asset_key, $legacyPath, $targetPath, $targetUrl, $apply ? 'pass' : 'warn', $apply ? 'copied + updated' : 'would copy'];
            $apply ? $pass++ : $warn++;
        }

        $this->table(['id', 'page', 'key', 'source', 'target', 'url', 'status', 'note'], $rows);
        $this->newLine();
        $this->line("pass={$pass} warn={$warn} fail={$fail}");

        foreach (ClientPageMediaConsumption::matrix() as $row) {
            if ($row['status'] !== 'used') {
                continue;
            }
            $this->line('consumption: '.$row['page_key'].'/'.$row['asset_key'].' → '.$row['blade']);
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
