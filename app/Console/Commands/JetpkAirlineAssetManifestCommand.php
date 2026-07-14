<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirlineAssetManifestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class JetpkAirlineAssetManifestCommand extends Command
{
    protected $signature = 'jetpk:airline-asset-manifest
                            {--root= : Absolute root to scan (staging promotion)}
                            {--archive= : Build manifest from tarball member bytes instead of --root}
                            {--output= : Output JSON path (default: storage/app/audits/jetpk-airport-parity/AIRLINE-ASSET-MANIFEST.json)}
                            {--compare= : Canonical manifest JSON path to compare against}';

    protected $description = 'Generate or compare airline logo asset manifest (path, size, sha256, mime).';

    public function handle(AirlineAssetManifestService $service): int
    {
        $output = (string) ($this->option('output') ?: storage_path('app/audits/jetpk-airport-parity/AIRLINE-ASSET-MANIFEST.json'));
        File::ensureDirectoryExists(dirname($output));

        $archiveArg = (string) ($this->option('archive') ?: '');
        $root = (string) ($this->option('root') ?: '');

        if ($archiveArg !== '') {
            if (str_starts_with($archiveArg, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $archiveArg)) {
                $this->error('Absolute archive paths are rejected; use a path relative to project root.');

                return self::FAILURE;
            }
            if (str_contains($archiveArg, '../') || str_starts_with($archiveArg, '..')) {
                $this->error('Archive path traversal (../) is rejected.');

                return self::FAILURE;
            }
            $manifest = $service->buildFromArchive(base_path($archiveArg));
        } elseif ($root === '') {
            $manifest = $this->buildDefaultManifest($service);
        } else {
            $manifest = $service->buildFromRoot($root, false);
        }

        File::put($output, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('Manifest written: '.$output);
        $this->line('entry_count='.($manifest['entry_count'] ?? 0));
        $this->line('validation_fail_count='.($manifest['validation_fail_count'] ?? 0));
        $this->line('valid='.(($manifest['valid'] ?? false) ? '1' : '0'));
        $this->line('root='.($manifest['root'] ?? ''));

        if (! ($manifest['valid'] ?? false)) {
            foreach ($manifest['entries'] ?? [] as $entry) {
                if (($entry['valid_content'] ?? false) !== true) {
                    $this->line('INVALID '.($entry['path'] ?? '').' '.json_encode($entry['validation_errors'] ?? []));
                }
            }

            return self::FAILURE;
        }

        $comparePath = (string) ($this->option('compare') ?: '');
        if ($comparePath !== '' && is_file($comparePath)) {
            $expected = json_decode((string) file_get_contents($comparePath), true);
            if (! is_array($expected)) {
                $this->error('Invalid compare manifest JSON');

                return self::FAILURE;
            }
            $compare = $service->compareManifests($expected, $manifest);
            $this->line('compare_fail_count='.($compare['fail_count'] ?? 0));
            foreach ($compare['mismatches'] ?? [] as $mismatch) {
                $this->line('MISMATCH '.json_encode($mismatch));
            }

            return ($compare['pass'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultManifest(AirlineAssetManifestService $service): array
    {
        $public = storage_path('app/public');
        $manifest = $service->buildFromRoot($public, false);
        $generic = public_path('images/airline-generic.svg');
        if (is_file($generic)) {
            $genericEntry = $service->buildFromRoot(public_path(), true);
            foreach ($genericEntry['entries'] as $entry) {
                if (($entry['relative_path'] ?? '') === 'images/airline-generic.svg') {
                    $manifest['entries'][] = $entry;
                    $manifest['entry_count'] = count($manifest['entries']);
                }
            }
        }
        $manifest['root'] = $public.' + '.public_path('images');

        return $manifest;
    }
}
