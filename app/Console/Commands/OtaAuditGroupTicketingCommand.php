<?php

namespace App\Console\Commands;

use App\Services\Developer\DevCpMonitoringSnapshotService;
use App\Support\Audits\OtaAuditReportWriter;
use Illuminate\Console\Command;

class OtaAuditGroupTicketingCommand extends Command
{
    protected $signature = 'ota:audit-group-ticketing {--export=docs/audits/OTA_GROUP_TICKETING_STATUS_REPORT.md}';

    protected $description = 'Generate group ticketing status audit report';

    public function handle(DevCpMonitoringSnapshotService $monitoring): int
    {
        $snapshot = $monitoring->groupTicketingStatus();

        $lines = [
            '# OTA Group Ticketing Status Report',
            '',
            'Generated: '.now()->toIso8601String(),
            '',
            '| Metric | Value | Classification |',
            '|--------|-------|----------------|',
            '| Inventory rows | '.($snapshot['inventory_count'] ?? 0).' | safe |',
            '| Last sync | '.($snapshot['last_inventory_sync'] ?? 'unknown').' | needs manual verification |',
            '',
            '## Scheduled commands',
            '',
        ];

        foreach ($snapshot['scheduled_commands'] ?? [] as $cmd => $schedule) {
            $lines[] = '- `'.$cmd.'` — '.$schedule.' (safe)';
        }

        $lines[] = '';
        $lines[] = '## Status counts';
        $lines[] = '';
        foreach ($snapshot['status_counts'] ?? [] as $status => $count) {
            $lines[] = '- `'.$status.'`: '.$count;
        }

        $export = (string) $this->option('export');
        OtaAuditReportWriter::write(base_path($export), $lines);
        $this->info('Report written: '.$export);

        return self::SUCCESS;
    }
}
