<?php

namespace App\Console\Commands;

use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\HaseebMasterRouteSafetyAuditService;
use App\Support\Audits\HaseebMasterRouteSafetyCatalog;
use Illuminate\Console\Command;

class OtaRouteSafetyAuditCommand extends Command
{
    protected $signature = 'ota:route-safety-audit
                            {--client=haseeb-master : Default deployment client slug to audit}';

    protected $description = 'MC-5C read-only route safety audit for the default haseeb-master deployment (no supplier calls, no DB writes)';

    public function handle(HaseebMasterRouteSafetyAuditService $auditService): int
    {
        $clientSlug = trim((string) $this->option('client'));
        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY route registry + collision audit (MC-5C).');
        $this->line('live_supplier_call_attempted=false db_write_attempted=false');
        $this->newLine();

        $rows = $auditService->run($clientSlug);

        $this->table(
            ['route name', 'method', 'URI', 'status', 'notes'],
            array_map(static fn (array $row): array => [
                $row['name'],
                $row['method'],
                $row['uri'],
                $row['status'],
                $row['notes'],
            ], $rows),
        );

        $counts = [
            'OK' => 0,
            'missing' => 0,
            'collision-risk' => 0,
        ];
        foreach ($rows as $row) {
            $status = $row['status'];
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Audit summary: client=%s total=%d ok=%d missing=%d collision-risk=%d',
            $clientSlug,
            count($rows),
            $counts['OK'],
            $counts['missing'],
            $counts['collision-risk'],
        ));

        if ($clientSlug === HaseebMasterRouteSafetyCatalog::DEFAULT_CLIENT_SLUG) {
            $this->comment('Default deployment routes must work without /'.$clientSlug.' prefix.');
        }

        if ($counts['missing'] > 0 || $counts['collision-risk'] > 0) {
            $this->error('Route safety audit failed — review missing/collision-risk rows above.');

            return self::FAILURE;
        }

        $this->info('Route safety audit passed.');

        return self::SUCCESS;
    }
}
