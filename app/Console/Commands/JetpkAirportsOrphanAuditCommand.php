<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirportOrphanService;
use Illuminate\Console\Command;

class JetpkAirportsOrphanAuditCommand extends Command
{
    protected $signature = 'jetpk:airports:orphan-audit';

    protected $description = 'Read-only audit of airport rows without a valid stored IATA code.';

    public function handle(AirportOrphanService $service): int
    {
        $report = $service->audit();
        $this->line('orphan_count='.($report['orphan_count'] ?? 0));
        foreach ($report['orphans'] ?? [] as $orphan) {
            $this->line(sprintf(
                'id=%s iata=%s icao=%s name=%s',
                $orphan['id'] ?? '',
                $orphan['iata_code'] ?? '',
                $orphan['icao_code'] ?? '',
                $orphan['name'] ?? '',
            ));
        }

        return self::SUCCESS;
    }
}
