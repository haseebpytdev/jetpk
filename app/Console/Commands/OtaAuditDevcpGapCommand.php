<?php

namespace App\Console\Commands;

use App\Support\Audits\OtaAuditReportWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class OtaAuditDevcpGapCommand extends Command
{
    protected $signature = 'ota:audit-devcp-gap {--export=docs/audits/OTA_DEV_CP_GAP_REPORT.md}';

    protected $description = 'Report Dev CP required sections vs implemented routes';

    public function handle(): int
    {
        $required = [
            'Overview' => 'dev.cp.index',
            'Companies (legacy redirect)' => 'dev.cp.companies.index',
            'Platform admin users' => 'dev.cp.users.index',
            'Module controls' => 'dev.cp.modules.index',
            'Security events' => 'dev.cp.security-events.index',
            'System health' => 'dev.cp.health',
            'Sabre status' => 'dev.cp.sabre',
            'Group ticketing' => 'dev.cp.group-ticketing',
            'Dashboard status' => 'dev.cp.dashboards',
            'Deployment status' => 'dev.cp.deployment',
            'Bootstrap command' => 'devcp:bootstrap-platform-admin',
            'Forced password (web)' => 'password.force',
            'Forced password (dev cp)' => 'dev.cp.password',
        ];

        $lines = [
            '# OTA Dev CP Gap Report',
            '',
            'Generated: '.now()->toIso8601String(),
            '',
            '| Section | Route/Command | Status | Classification |',
            '|---------|---------------|--------|----------------|',
        ];

        foreach ($required as $section => $key) {
            if (str_contains($key, ':')) {
                $status = class_exists(DevcpBootstrapPlatformAdminCommand::class) ? 'implemented' : 'missing';
            } else {
                $status = Route::has($key) ? 'implemented' : 'missing';
            }
            $class = $status === 'implemented' ? 'safe' : 'needs change';
            $lines[] = '| '.$section.' | `'.$key.'` | '.$status.' | '.$class.' |';
        }

        $export = (string) $this->option('export');
        OtaAuditReportWriter::write(base_path($export), $lines);
        $this->info('Report written: '.$export);

        return self::SUCCESS;
    }
}
