<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkPageSettingsEmptyValueAuditCommand extends Command
{
    protected $signature = 'jetpk:page-settings-empty-value-audit';

    protected $description = 'Verify Page Settings empty-string and false persistence semantics (read-only)';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $result = $service->emptyValueAudit();
        $this->line('path='.$result['path'].' fail='.$result['fail']);

        return $result['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
