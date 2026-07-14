<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkPaletteConsumptionAuditCommand extends Command
{
    protected $signature = 'jetpk:palette-consumption-audit';

    protected $description = 'Scan admin theme for palette variable consumption and hardcoded brand colors';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $result = $service->paletteConsumptionAudit();
        $this->line('path='.$result['path'].' fail='.$result['fail']);

        return $result['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
