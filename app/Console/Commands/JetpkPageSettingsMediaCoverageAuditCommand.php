<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkPageSettingsMediaCoverageAuditCommand extends Command
{
    protected $signature = 'jetpk:page-settings-media-coverage-audit';

    protected $description = 'Verify JetPK page settings media schema covers required homepage assets';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $result = $service->mediaCoverageAudit();
        $this->line('path='.$result['path'].' pass='.$result['pass'].' fail='.$result['fail']);

        return $result['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
