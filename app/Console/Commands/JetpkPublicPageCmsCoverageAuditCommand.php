<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPublicPageCmsCoverageAuditService;
use Illuminate\Console\Command;

class JetpkPublicPageCmsCoverageAuditCommand extends Command
{
    protected $signature = 'jetpk:public-page-cms-coverage-audit
                            {--profile=jetpk : Client profile slug}
                            {--base-url= : Override base URL for HTTP checks}';

    protected $description = 'Read-only audit of JetPK managed public page CMS ownership coverage';

    public function handle(JetpkPublicPageCmsCoverageAuditService $service): int
    {
        $this->line('Classification: READ-ONLY JetPK public page CMS coverage audit.');
        $this->line('db_write_attempted=false');
        $this->line('cms_mutation_attempted=false');
        $this->line('publish_attempted=false');
        $this->line('supplier_call_attempted=false');
        $this->newLine();

        $result = $service->run(
            (string) $this->option('profile'),
            $this->option('base-url') ? (string) $this->option('base-url') : null,
        );

        $this->table(
            ['page_key', 'http_status', 'ownership', 'status', 'hardcoded', 'cms_backed', 'published'],
            collect($result['pages'] ?? [])->map(fn (array $page) => [
                $page['page_key'],
                (string) ($page['http_status'] ?? 'n/a'),
                $page['ownership_type'],
                $page['status'],
                (string) $page['hardcoded_text_nodes'],
                (string) $page['cms_backed_text_nodes'],
                $page['published_exists'] ? 'yes' : 'no',
            ])->all(),
        );

        $this->newLine();
        $this->line('JSON: '.$result['path']);
        $this->line('Markdown: '.$result['md_path']);
        $this->line('fail='.($result['fail'] ?? 0));

        return ($result['fail'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
