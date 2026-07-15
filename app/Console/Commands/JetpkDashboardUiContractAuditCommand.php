<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkDashboardUiContractAuditCommand extends Command
{
    protected $signature = 'jetpk:dashboard-ui-contract-audit';

    protected $description = 'Verify JetPK admin dashboard UI contract CSS selectors';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $result = $service->dashboardUiContractAudit();
        $this->line('path='.$result['path'].' pass='.$result['pass'].' fail='.$result['fail']);

        return $result['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
