<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkAirportParityAuditService;
use Illuminate\Console\Command;

class JetpkAirportUiConsumptionAuditCommand extends Command
{
    protected $signature = 'jetpk:airport-ui-consumption-audit';

    protected $description = 'Read-only JetPK airport UI/autocomplete consumption audit.';

    public function handle(JetpkAirportParityAuditService $service): int
    {
        $result = $service->airportUiConsumptionAudit();

        $this->line('JetPK airport UI consumption audit');
        $this->line('===================================');
        foreach ($result['checks'] as $check) {
            $status = ($check['pass'] ?? false) ? 'PASS' : 'FAIL';
            $detail = trim((string) ($check['detail'] ?? ''));
            $this->line($status.'  '.($check['label'] ?? '').($detail !== '' ? ' ('.$detail.')' : ''));
        }
        $this->line('fail_count='.$result['fail_count']);

        return $result['pass'] ? self::SUCCESS : self::FAILURE;
    }
}
