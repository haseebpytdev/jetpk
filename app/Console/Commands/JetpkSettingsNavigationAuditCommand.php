<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkSettingsNavigationAuditCommand extends Command
{
    protected $signature = 'jetpk:settings-navigation-audit';

    protected $description = 'Verify Settings Hub required routes exist for JetPK';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $result = $service->settingsNavigationAudit();
        $this->line('path='.$result['path'].' pass='.$result['pass'].' fail='.$result['fail']);

        return $result['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
