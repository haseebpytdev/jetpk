<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkResultsSearchStyleParityAudit;
use Illuminate\Console\Command;

class JetpkResultsSearchStyleParityAuditCommand extends Command
{
    protected $signature = 'jetpk:results-search-style-parity-audit';

    protected $description = 'Audit Results search shell for forbidden visual overrides vs canonical Home search styling';

    public function handle(JetpkResultsSearchStyleParityAudit $audit): int
    {
        $result = $audit->run();
        $violations = $result['violations'];
        $fail = (int) $result['fail'];

        $this->line('bootstrap_btn_scoped='.(($result['bootstrap_scoped'] ?? false) ? '1' : '0'));
        $this->line('canonical_fail='.(int) ($result['canonical_fail'] ?? 0));
        $this->line('violations='.count($violations));
        $this->line('fail_count='.$fail);

        foreach ($violations as $violation) {
            $this->error('  '.$violation);
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
