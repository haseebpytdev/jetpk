<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkSupplierCardSelectorAuditCommand extends Command
{
    protected $signature = 'jetpk:supplier-card-selector-audit';

    protected $description = 'Verify JetPK supplier provider card picker views exist';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $result = $service->supplierCardSelectorAudit();
        $this->line('path='.$result['path'].' pass='.$result['pass'].' fail='.$result['fail']);

        return $result['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
