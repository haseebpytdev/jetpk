<?php

namespace App\Console\Commands;

use App\Support\Audits\OtaAuditReportWriter;
use Illuminate\Console\Command;

class OtaAuditSecurityHardeningCommand extends Command
{
    protected $signature = 'ota:audit-security-hardening {--export=docs/audits/OTA_SECURITY_HARDENING_REPORT.md}';

    protected $description = 'Generate security hardening audit report';

    public function handle(): int
    {
        $roots = [
            base_path('app'),
            base_path('resources/views'),
            base_path('routes'),
            base_path('config'),
        ];

        $patterns = [
            'raw_blade' => ['regex' => '/\{\!!/', 'classification' => 'needs manual verification', 'note' => 'Unescaped Blade output'],
            'where_raw' => ['regex' => '/whereRaw\s*\(/', 'classification' => 'needs manual verification', 'note' => 'Raw WHERE clause'],
            'db_raw' => ['regex' => '/DB::raw\s*\(/', 'classification' => 'needs manual verification', 'note' => 'Raw SQL expression'],
            'request_all' => ['regex' => '/\$request->all\s*\(\)|request\(\)->all\s*\(\)/', 'classification' => 'needs change', 'note' => 'Mass assignment risk — prefer validated()'],
            'force_fill' => ['regex' => '/forceFill\s*\(/', 'classification' => 'needs manual verification', 'note' => 'Ensure guarded attributes'],
        ];

        $findings = [];
        foreach ($patterns as $key => $meta) {
            foreach (OtaAuditReportWriter::scanPattern($roots, $meta['regex']) as $match) {
                $findings[] = [
                    'file' => $match['file'],
                    'line' => $match['line'],
                    'pattern' => $key,
                    'classification' => $meta['classification'],
                    'note' => $meta['note'],
                ];
            }
        }

        $lines = [
            '# OTA Security Hardening Report',
            '',
            'Generated: '.now()->toIso8601String(),
            '',
            '## Summary',
            '',
            '| Classification | Count |',
            '|----------------|------:|',
        ];

        foreach (['safe', 'needs change', 'unsafe', 'needs manual verification'] as $class) {
            $count = count(array_filter($findings, fn (array $f): bool => $f['classification'] === $class));
            $lines[] = '| '.$class.' | '.$count.' |';
        }

        $lines[] = '';
        $lines[] = '## Config hotspots';
        $lines[] = '';
        $lines[] = '| Item | Value | Classification |';
        $lines[] = '|------|-------|----------------|';
        $lines[] = '| password reset expire (minutes) | '.config('auth.passwords.users.expire').' | safe |';
        $lines[] = '| password reset throttle (seconds) | '.config('auth.passwords.users.throttle').' | safe |';
        $lines[] = '| APP_DEBUG | '.(config('app.debug') ? 'true' : 'false').' | '.(config('app.debug') ? 'unsafe' : 'safe').' |';
        $lines[] = '| CORS config file | '.(is_file(config_path('cors.php')) ? 'present' : 'absent (Laravel default)').' | needs manual verification |';

        $lines[] = '';
        $lines[] = '## Code findings';
        $lines[] = '';
        $lines = array_merge($lines, OtaAuditReportWriter::findingsTable($findings));

        $export = (string) $this->option('export');
        OtaAuditReportWriter::write(base_path($export), $lines);
        $this->info('Report written: '.$export);

        return self::SUCCESS;
    }
}
