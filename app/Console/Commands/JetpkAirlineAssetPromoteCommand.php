<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirlineAssetManifestService;
use App\Services\TravelData\AirlineAssetPromotionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class JetpkAirlineAssetPromoteCommand extends Command
{
    protected $signature = 'jetpk:airline-asset-promote
                            {--staging= : Staging root directory}
                            {--backup= : Backup root directory}
                            {--compare= : Canonical manifest JSON to compare before promotion}
                            {--rollback : Restore from backup instead of promoting}';

    protected $description = 'Atomically promote staged airline logo directories or rollback from backup.';

    public function handle(
        AirlineAssetPromotionService $promotion,
        AirlineAssetManifestService $manifests,
    ): int {
        $staging = (string) ($this->option('staging') ?: '');
        $backup = (string) ($this->option('backup') ?: '');
        $active = storage_path('app/public');
        $dirs = ['airline-logos', 'travel-assets/airlines/logos'];

        if ($this->option('rollback')) {
            if ($backup === '') {
                $this->error('--backup is required for rollback');

                return self::FAILURE;
            }
            $result = $promotion->rollback($active, $backup, $dirs);
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

            return ($result['rolled_back'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        if ($staging === '' || $backup === '') {
            $this->error('--staging and --backup are required');

            return self::FAILURE;
        }

        $expected = null;
        $comparePath = (string) ($this->option('compare') ?: '');
        if ($comparePath !== '' && is_file($comparePath)) {
            $expected = json_decode((string) file_get_contents($comparePath), true);
        }

        $result = $promotion->promote($staging, $active, $backup, $dirs, is_array($expected) ? $expected : null);
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return ($result['promoted'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
