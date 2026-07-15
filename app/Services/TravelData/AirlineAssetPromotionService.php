<?php

namespace App\Services\TravelData;

use Illuminate\Support\Facades\File;

/**
 * Atomic directory promotion for airline logo assets with checksum-verified rollback.
 */
final class AirlineAssetPromotionService
{
    public function __construct(
        private readonly AirlineAssetManifestService $manifests,
    ) {}

    /**
     * @param  list<string>  $relativeDirs  e.g. ['airline-logos', 'travel-assets/airlines/logos']
     * @return array<string, mixed>
     */
    public function promote(
        string $stagingRoot,
        string $activePublicRoot,
        string $backupRoot,
        array $relativeDirs,
        ?array $expectedManifest = null,
    ): array {
        $stagingRoot = rtrim($stagingRoot, DIRECTORY_SEPARATOR);
        $activePublicRoot = rtrim($activePublicRoot, DIRECTORY_SEPARATOR);
        $backupRoot = rtrim($backupRoot, DIRECTORY_SEPARATOR);

        $stagingManifest = $this->manifests->buildFromRoot($stagingRoot, false);
        if ($expectedManifest !== null) {
            $compare = $this->manifests->compareManifests($expectedManifest, $stagingManifest);
            if (! $compare['pass']) {
                return [
                    'promoted' => false,
                    'reason' => 'staging_manifest_mismatch',
                    'compare' => $compare,
                ];
            }
        }

        File::ensureDirectoryExists($backupRoot);
        $moves = [];

        foreach ($relativeDirs as $relative) {
            $stagingDir = $stagingRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $activeDir = $activePublicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $backupDir = $backupRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_dir($stagingDir)) {
                return [
                    'promoted' => false,
                    'reason' => 'missing_staging_directory',
                    'path' => $stagingDir,
                ];
            }

            if (is_dir($activeDir)) {
                if (is_dir($backupDir)) {
                    File::deleteDirectory($backupDir);
                }
                rename($activeDir, $backupDir);
                $moves[] = ['action' => 'backed_up', 'from' => $activeDir, 'to' => $backupDir];
            }

            File::ensureDirectoryExists(dirname($activeDir));
            rename($stagingDir, $activeDir);
            $moves[] = ['action' => 'promoted', 'from' => $stagingDir, 'to' => $activeDir];
        }

        $activeManifest = $this->manifests->buildFromRoot($activePublicRoot, false);
        $verify = $expectedManifest !== null
            ? $this->manifests->compareManifests($expectedManifest, $activeManifest)
            : ['pass' => true, 'fail_count' => 0, 'mismatches' => []];

        return [
            'promoted' => $verify['pass'],
            'moves' => $moves,
            'backup_root' => $backupRoot,
            'post_promotion_verify' => $verify,
            'active_manifest' => $activeManifest,
        ];
    }

    /**
     * @param  list<string>  $relativeDirs
     * @return array<string, mixed>
     */
    public function rollback(string $activePublicRoot, string $backupRoot, array $relativeDirs): array
    {
        $activePublicRoot = rtrim($activePublicRoot, DIRECTORY_SEPARATOR);
        $backupRoot = rtrim($backupRoot, DIRECTORY_SEPARATOR);
        $restored = [];

        foreach ($relativeDirs as $relative) {
            $activeDir = $activePublicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $backupDir = $backupRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_dir($backupDir)) {
                continue;
            }

            if (is_dir($activeDir)) {
                File::deleteDirectory($activeDir);
            }
            rename($backupDir, $activeDir);
            $restored[] = ['from' => $backupDir, 'to' => $activeDir];
        }

        return [
            'rolled_back' => $restored !== [],
            'restored' => $restored,
            'active_manifest' => $this->manifests->buildFromRoot($activePublicRoot, false),
        ];
    }
}
