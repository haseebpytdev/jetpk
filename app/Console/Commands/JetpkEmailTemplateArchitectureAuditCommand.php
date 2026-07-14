<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkEmailTemplateArchitectureAuditCommand extends Command
{
    protected $signature = 'jetpk:email-template-architecture-audit';

    protected $description = 'Audit JetPK universal email shell and template registry coverage';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $result = $service->emailTemplateArchitectureAudit();
        $this->line('path='.$result['path'].' pass='.$result['pass'].' fail='.$result['fail']);

        return $result['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
