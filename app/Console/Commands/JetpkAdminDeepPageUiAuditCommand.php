<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkAdminDeepPageInventoryAuditService;
use Illuminate\Console\Command;

/**
 * JetPK admin deep-page UI contract audit (visible routes, blade contract gate).
 */
class JetpkAdminDeepPageUiAuditCommand extends Command
{
    protected $signature = 'jetpk:admin-deep-page-ui-audit
                            {--client=jetpk : Client slug}
                            {--json : Print JSON path only}';

    protected $description = 'Audit visible admin UI routes with blade contract checks and report blocked count';

    public function handle(JetpkAdminDeepPageInventoryAuditService $service): int
    {
        $slug = trim((string) $this->option('client'));
        $this->line('Classification: READ-ONLY JetPK admin deep-page UI contract audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $result = $service->run($slug);
        $summary = $result['summary'];
        $blocked = (int) ($summary['blocked'] ?? 0);
        $visible = (int) ($summary['visible_routes'] ?? 0);

        if ($this->option('json')) {
            $this->line($result['json_path']);

            return $blocked > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Admin deep-page UI audit');
        $this->table(
            ['metric', 'value'],
            [
                ['visible_routes', (string) $visible],
                ['blocked', (string) $blocked],
                ['legacy', (string) ($summary['legacy'] ?? 0)],
                ['legacy_fixed', (string) ($summary['legacy_fixed'] ?? 0)],
            ],
        );
        $this->line('JSON: '.$result['json_path']);
        $this->line('Markdown: '.$result['md_path']);
        $this->line('Matrix: '.$result['matrix_path']);

        if ($blocked > 0) {
            $this->error("Blocked visible UI routes: {$blocked}");

            return self::FAILURE;
        }

        $this->info('All visible UI routes pass UI contract gate.');

        return self::SUCCESS;
    }
}
