<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkCmsRouteSafetyAuditService;
use Illuminate\Console\Command;

class JetpkCmsRouteSafetyAuditCommand extends Command
{
    protected $signature = 'jetpk:cms-route-safety-audit {--profile=jetpk : Client profile slug}';

    protected $description = 'Read-only CMS route and slug safety audit for JetPK managed pages';

    public function handle(JetpkCmsRouteSafetyAuditService $service): int
    {
        $this->line('Classification: READ-ONLY CMS route safety audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $result = $service->run((string) $this->option('profile'));

        $this->table(['metric', 'count'], [
            ['route_collisions', (string) $result['route_collisions']],
            ['reserved_slug_violations', (string) $result['reserved_slug_violations']],
            ['duplicate_slugs', (string) $result['duplicate_slugs']],
            ['draft_exposure', (string) $result['draft_exposure']],
            ['broken_navigation_links', (string) $result['broken_navigation_links']],
            ['unsafe_external_links', (string) $result['unsafe_external_links']],
            ['disabled_pages_still_linked', (string) $result['disabled_pages_still_linked']],
            ['missing_page_destinations', (string) $result['missing_page_destinations']],
        ]);

        $this->newLine();
        $this->line('JSON: '.$result['path']);
        $this->line('fail='.($result['fail'] ?? 0));

        return ($result['fail'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
