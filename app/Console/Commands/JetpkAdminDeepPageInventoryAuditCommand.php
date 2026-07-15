<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkAdminDeepPageInventoryAuditService;
use Illuminate\Console\Command;

/**
 * Read-only JetPK admin deep-page route inventory for 9H-D closure.
 */
class JetpkAdminDeepPageInventoryAuditCommand extends Command
{
    protected $signature = 'jetpk:admin-deep-page-inventory-audit
                            {--client=jetpk : Client slug}
                            {--json : Print JSON path only}';

    protected $description = 'Enumerate all admin dashboard routes and classify deep-page UI status (read-only)';

    public function handle(JetpkAdminDeepPageInventoryAuditService $service): int
    {
        $slug = trim((string) $this->option('client'));
        $this->line('Classification: READ-ONLY JetPK admin deep-page inventory audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $result = $service->run($slug);
        $summary = $result['summary'];

        if ($this->option('json')) {
            $this->line($result['json_path']);

            return ((int) ($summary['blocked'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Admin deep-page inventory');
        $this->table(
            ['metric', 'value'],
            collect($summary)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->values()->all(),
        );
        $this->line('JSON: '.$result['json_path']);
        $this->line('Markdown: '.$result['md_path']);

        return ((int) ($summary['blocked'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
