<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkLegacySettingsRouteAuditCommand extends Command
{
    protected $signature = 'jetpk:legacy-settings-route-audit';

    protected $description = 'Verify legacy homepage settings routes redirect for JetPK';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $result = $service->legacySettingsRouteAudit();
        $this->line('path='.$result['path'].' pass='.$result['pass'].' fail='.$result['fail']);

        return $result['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
