<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkAirportParityAuditService;
use Illuminate\Console\Command;

class JetpkAirlineLogoCoverageAuditCommand extends Command
{
    protected $signature = 'jetpk:airline-logo-coverage-audit';

    protected $description = 'Read-only JetPK airline reference and logo coverage audit.';

    public function handle(JetpkAirportParityAuditService $service): int
    {
        $result = $service->airlineLogoCoverageAudit();

        $report = $result['report'];
        $dbMeta = $report['database_metadata'] ?? [];
        $fs = $report['filesystem_validation'] ?? [];

        $this->line('JetPK airline logo coverage audit');
        $this->line('=================================');
        $this->line('airline_rows='.($report['airline_database_row_count'] ?? 0));
        $this->line('rows_with_logo_path='.($dbMeta['rows_with_logo_path'] ?? 0).' (database metadata only)');
        $this->line('validated_files='.($fs['validated_file_count'] ?? 0));
        $this->line('generic_fallback='.($fs['generic_fallback'] ?? ''));
        $this->line('generic_fallback_valid='.(($fs['generic_fallback_valid'] ?? false) ? '1' : '0'));

        foreach ($fs['invalid_content_paths'] ?? [] as $invalid) {
            $this->line('INVALID '.($invalid['path'] ?? '').' '.json_encode($invalid['errors'] ?? []));
        }

        foreach ($report['required_canonical_codes'] ?? [] as $row) {
            $this->line('REQUIRED '.($row['iata'] ?? '').' '.($row['status'] ?? '').' '.($row['path'] ?? ''));
        }

        $this->line('json='.$result['path_json']);
        $this->line('md='.$result['path_md']);
        $this->line('fail_count='.$result['fail_count']);

        return $result['pass'] ? self::SUCCESS : self::FAILURE;
    }
}
