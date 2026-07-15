<?php

namespace App\Console\Commands;

use App\Services\Developer\DevCpMonitoringSnapshotService;
use App\Support\Audits\OtaAuditReportWriter;
use Illuminate\Console\Command;

class OtaAuditDashboardsCommand extends Command
{
    protected $signature = 'ota:audit-dashboards {--export=docs/audits/OTA_DASHBOARDS_STATUS_REPORT.md}';

    protected $description = 'Generate portal dashboard module status report';

    public function handle(DevCpMonitoringSnapshotService $monitoring): int
    {
        $snapshot = $monitoring->dashboardsStatus();

        $lines = [
            '# OTA Dashboards Status Report',
            '',
            'Generated: '.now()->toIso8601String(),
            '',
            '| Portal | Module | Enabled | Route guarded | Backend enforced | Classification |',
            '|--------|--------|---------|---------------|------------------|----------------|',
        ];

        foreach ($snapshot['portals'] ?? [] as $portal) {
            $class = ($portal['effective_enabled'] ?? false) ? 'safe' : 'needs manual verification';
            $lines[] = sprintf(
                '| %s | `%s` | %s | %s | %s | %s |',
                $portal['label'],
                $portal['module_key'],
                ($portal['effective_enabled'] ?? false) ? 'yes' : 'no',
                ($portal['route_guarded'] ?? false) ? 'yes' : 'no',
                ($portal['backend_enforced'] ?? false) ? 'yes' : 'no',
                $class,
            );
        }

        $export = (string) $this->option('export');
        OtaAuditReportWriter::write(base_path($export), $lines);
        $this->info('Report written: '.$export);

        return self::SUCCESS;
    }
}
