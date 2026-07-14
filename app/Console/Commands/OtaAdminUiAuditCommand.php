<?php

namespace App\Console\Commands;

use App\Support\Audits\AdminUiAuditService;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\OtaAuditReportWriter;
use Illuminate\Console\Command;

class OtaAdminUiAuditCommand extends Command
{
    protected $signature = 'ota:admin-ui-audit
                            {--export= : Optional path to write markdown appendix (local docs only)}';

    protected $description = 'Read-only audit of haseeb-master admin v1 UI Blade/CSS inventory (OTA-ADMIN-ADB-AUDIT-1).';

    public function handle(AdminUiAuditService $audit): int
    {
        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY admin v1 UI audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $snapshot = $audit->snapshot();

        $this->info('OTA Admin UI Audit (v1)');
        $this->newLine();

        foreach ([
            'admin_layout_files_count',
            'admin_blade_files_count',
            'admin_css_files_count',
            'inline_style_occurrences',
            'page_style_push_count',
            'route_count_admin',
        ] as $key) {
            $this->line($key.'='.($snapshot[$key] ?? 0));
        }

        $this->newLine();
        $this->line('button_class_patterns='.json_encode($snapshot['button_class_patterns'] ?? [], JSON_UNESCAPED_SLASHES));
        $this->line('card_class_patterns='.json_encode($snapshot['card_class_patterns'] ?? [], JSON_UNESCAPED_SLASHES));
        $this->line('table_class_patterns='.json_encode($snapshot['table_class_patterns'] ?? [], JSON_UNESCAPED_SLASHES));

        $fail = (int) ($snapshot['fail'] ?? 1);
        $this->newLine();
        $this->line('fail='.$fail);

        $export = trim((string) $this->option('export'));
        if ($export !== '') {
            $lines = [
                '# Admin UI Audit Command Snapshot',
                '',
                'Generated: '.now()->toIso8601String(),
                '',
                '```',
            ];
            foreach ([
                'admin_layout_files_count',
                'admin_blade_files_count',
                'admin_css_files_count',
                'inline_style_occurrences',
                'page_style_push_count',
                'route_count_admin',
                'fail',
            ] as $key) {
                $lines[] = $key.'='.($snapshot[$key] ?? 0);
            }
            $lines[] = 'button_class_patterns='.json_encode($snapshot['button_class_patterns'] ?? [], JSON_UNESCAPED_SLASHES);
            $lines[] = 'card_class_patterns='.json_encode($snapshot['card_class_patterns'] ?? [], JSON_UNESCAPED_SLASHES);
            $lines[] = 'table_class_patterns='.json_encode($snapshot['table_class_patterns'] ?? [], JSON_UNESCAPED_SLASHES);
            $lines[] = '```';

            OtaAuditReportWriter::write(base_path($export), $lines);
            $this->info('Snapshot written: '.$export);
        }

        if ($fail > 0) {
            $this->error('Admin UI audit completed with inspection failures.');

            return self::FAILURE;
        }

        $this->info('Admin UI audit passed.');

        return self::SUCCESS;
    }
}
