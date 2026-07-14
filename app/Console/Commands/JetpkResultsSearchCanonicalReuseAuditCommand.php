<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkResultsSearchStyleParityAudit;
use Illuminate\Console\Command;

class JetpkResultsSearchCanonicalReuseAuditCommand extends Command
{
    protected $signature = 'jetpk:results-search-canonical-reuse-audit';

    protected $description = 'Audit Home and Results both render the canonical home-flights-search partial';

    public function handle(JetpkResultsSearchStyleParityAudit $audit): int
    {
        $result = $audit->runCanonicalReuse();
        $violations = $result['violations'];
        $fail = (int) $result['fail'];

        $this->line('canonical_fail='.$fail);

        foreach ($violations as $violation) {
            $this->error('  '.$violation);
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
