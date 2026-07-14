<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirlineArchiveAuditService;
use Illuminate\Console\Command;

class JetpkAirlineArchiveAuditCommand extends Command
{
    protected $signature = 'jetpk:airline-archive-audit {--archive=jetpk-airline-logos.tgz : Path to tarball relative to project root or absolute}';

    protected $description = 'Read-only audit of airline logo archive before extraction.';

    public function handle(AirlineArchiveAuditService $service): int
    {
        $archiveArg = (string) $this->option('archive');
        if (str_starts_with($archiveArg, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $archiveArg)) {
            $this->error('Absolute archive paths are rejected; use a path relative to project root.');

            return self::FAILURE;
        }
        if (str_contains($archiveArg, '../') || str_starts_with($archiveArg, '..')) {
            $this->error('Archive path traversal (../) is rejected.');

            return self::FAILURE;
        }

        $archive = base_path($archiveArg);
        $result = $service->audit($archive);
        $this->line('archive='.$archive);
        $this->line('entry_count='.($result['entry_count'] ?? 0));
        $this->line('fail_count='.($result['fail_count'] ?? 0));
        foreach ($result['issues'] ?? [] as $issue) {
            $path = (string) ($issue['path'] ?? '');
            $this->line('ISSUE path='.$path.' '.json_encode($issue));
        }

        return ($result['pass'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
