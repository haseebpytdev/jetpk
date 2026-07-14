<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkHomepageCustomizationCoverageAuditService;
use Illuminate\Console\Command;

class JetpkHomepageCustomizationCoverageAuditCommand extends Command
{
    protected $signature = 'jetpk:homepage-customization-coverage-audit
                            {--json : Print JSON path only}';

    protected $description = 'Audit JetPK homepage section customization ownership and editor coverage';

    public function handle(JetpkHomepageCustomizationCoverageAuditService $service): int
    {
        $this->line('Classification: READ-ONLY homepage customization coverage audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $result = $service->run();

        if ($this->option('json')) {
            $this->line($result['path']);

            return ($result['fail'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Homepage customization coverage');
        $this->table(['metric', 'value'], [
            ['pass', (string) ($result['pass'] ?? 0)],
            ['fail', (string) ($result['fail'] ?? 0)],
        ]);
        $this->line('JSON: '.$result['path']);
        $this->line('Markdown: '.$result['md_path']);

        return ($result['fail'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
