<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirlineAssetArchiveBuildService;
use Illuminate\Console\Command;

class JetpkAirlineAssetArchiveBuildCommand extends Command
{
    protected $signature = 'jetpk:airline-asset-archive-build
                            {--output=jetpk-airline-logos.tgz : Output archive path relative to project root}
                            {--root= : Public root to package (default: storage/app/public)}';

    protected $description = 'Build jetpk-airline-logos.tgz from approved airline logo directories.';

    public function handle(AirlineAssetArchiveBuildService $builder): int
    {
        $outputArg = (string) $this->option('output');
        if (str_starts_with($outputArg, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $outputArg)) {
            $this->error('Absolute output paths are rejected; use a path relative to project root.');

            return self::FAILURE;
        }
        if (str_contains($outputArg, '../') || str_starts_with($outputArg, '..')) {
            $this->error('Output path traversal (../) is rejected.');

            return self::FAILURE;
        }

        $root = (string) ($this->option('root') ?: '');
        $publicRoot = $root !== '' ? $root : storage_path('app/public');
        $output = base_path($outputArg);

        $result = $builder->build($output, $publicRoot);
        $this->line('archive='.$result['archive']);
        $this->line('sha256='.$result['sha256']);
        $this->line('entry_count='.$result['entry_count']);
        $this->line('audit_fail_count='.($result['audit']['fail_count'] ?? 0));

        return ($result['pass'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
