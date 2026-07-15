<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkAirportParityAuditService;
use Illuminate\Console\Command;

class JetpkAirportDataParityAuditCommand extends Command
{
    protected $signature = 'jetpk:airport-data-parity-audit {--source= : Optional OurAirports CSV path}';

    protected $description = 'Read-only JetPK airport source vs database parity audit (no DB/cache writes).';

    public function handle(JetpkAirportParityAuditService $service): int
    {
        $result = $service->airportDataParityAudit($this->option('source'));

        $report = $result['report'];
        $source = $report['source'] ?? [];
        $db = $report['database'] ?? [];
        $count = $report['count_reconciliation'] ?? [];

        $this->line('JetPK airport data parity audit');
        $this->line('================================');
        $this->line('source_csv='.($source['source_path'] ?? ''));
        $this->line('source_sha256='.($source['source_sha256'] ?? ''));
        $this->line('physical_lines='.($source['physical_line_count'] ?? 0));
        $this->line('parsed_rows='.($source['parsed_row_count'] ?? 0));
        $this->line('eligible_before_dedup='.($source['eligible_rows_before_dedup'] ?? 0));
        $this->line('unique_eligible_iata='.($source['unique_eligible_iata_count'] ?? 0));
        $this->line('duplicates_removed='.($source['duplicates_removed'] ?? 0));
        $this->line('database_airports='.($db['database_airport_count'] ?? 0));
        $this->line('missing_source_iata='.($db['missing_source_iata_in_db_count'] ?? 0));
        $this->line('expected_post_import_total='.($db['expected_post_import_total'] ?? 0));
        $this->line('count_delta_vs_5461='.($count['delta_importable_vs_historical'] ?? $count['delta'] ?? 0));
        $this->line('raw_eligible_including_reserved='.($count['raw_eligible_including_reserved_rejected'] ?? ''));
        $this->line('json='.$result['path_json']);
        $this->line('md='.$result['path_md']);
        $this->line('fail_count='.$result['fail_count']);

        return $result['pass'] ? self::SUCCESS : self::FAILURE;
    }
}
