<?php

namespace App\Console\Commands;

use App\Models\ClientPageAsset;
use App\Support\Client\ClientPageMediaConsumption;
use App\Support\Client\ClientPageMediaSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only audit of JetPK page asset storage, URLs, and frontend consumption.
 */
class JetpkPageAssetsAuditCommand extends Command
{
    protected $signature = 'jetpk:page-assets-audit {--client=jetpk : Client profile slug scope}';

    protected $description = 'Audit JetPK page asset disk paths, public URLs, and consumption matrix (read-only)';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY JetPK page assets audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $fail = 0;
        $warn = 0;
        $pass = 0;

        if (! Schema::hasTable('client_page_assets')) {
            $this->error('client_page_assets table missing');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $rows = [];

        foreach (ClientPageAsset::query()->orderBy('id')->get() as $asset) {
            $path = (string) $asset->path;
            $inStorage = $path !== '' && $disk->exists($path);
            $inLegacy = $path !== '' && is_file(public_path($path));
            $url = $path !== '' ? $disk->url($path) : '—';
            $consumed = collect(ClientPageMediaConsumption::matrix())
                ->first(fn (array $row): bool => $row['page_key'] === $asset->page_key
                    && $row['asset_key'] === $asset->asset_key
                    && $row['status'] === 'used') !== null;

            $status = 'pass';
            $note = 'canonical storage';
            if (! $inStorage && $inLegacy) {
                $status = 'warn';
                $note = 'legacy app/public only — run jetpk:page-assets-repair';
                $warn++;
            } elseif (! $inStorage && ! $inLegacy) {
                $status = 'fail';
                $note = 'file missing';
                $fail++;
            } else {
                $pass++;
            }

            if (! $consumed) {
                $status = $status === 'pass' ? 'warn' : $status;
                $note .= '; not consumed on frontend';
                if ($status === 'warn') {
                    // already counted
                } elseif ($status === 'pass') {
                    $warn++;
                    $pass--;
                }
            }

            $rows[] = [$asset->id, $asset->page_key, $asset->asset_key, $path, $status, $note, $url];
        }

        $this->table(['id', 'page', 'key', 'path', 'status', 'note', 'public_url'], $rows);

        $schemaRows = [];
        foreach (ClientPageMediaConsumption::matrix() as $row) {
            $exposed = in_array($row['asset_key'], ClientPageMediaSchema::assetKeysFor($row['page_key']), true);
            $schemaRows[] = [
                $row['page_key'],
                $row['asset_key'],
                $row['status'],
                $exposed ? 'yes' : 'no',
                $row['blade'],
            ];
            if ($row['status'] === 'used' && ! $exposed) {
                $fail++;
            }
            if (in_array($row['status'], ['dead', 'duplicate'], true) && $exposed) {
                $fail++;
            }
        }

        $this->newLine();
        $this->table(['page', 'key', 'status', 'admin_exposed', 'frontend'], $schemaRows);
        $this->newLine();
        $this->line("pass={$pass} warn={$warn} fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
