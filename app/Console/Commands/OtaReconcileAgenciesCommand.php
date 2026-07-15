<?php

namespace App\Console\Commands;

use App\Services\Agencies\AgencyReconciliationService;
use Illuminate\Console\Command;

class OtaReconcileAgenciesCommand extends Command
{
    protected $signature = 'ota:reconcile-agencies
                            {--dry-run : Show planned repairs without writing}
                            {--force : Apply repairs (ignored when --dry-run is set)}';

    protected $description = 'Diagnose and safely repair approved agent-application agency/owner linkages';

    public function handle(AgencyReconciliationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! (bool) $this->option('force');

        if ($dryRun) {
            $this->info('Dry run — no database changes will be made.');
        } else {
            $this->warn('Applying reconciliation changes.');
        }

        $result = $service->reconcile($dryRun);

        if ($result['rows'] === []) {
            $this->info('No repairable agency linkage issues found.');

            return self::SUCCESS;
        }

        foreach ($result['rows'] as $row) {
            $this->newLine();
            $this->line(sprintf(
                '[%s] %s #%s (%s)',
                strtoupper((string) ($row['action'] ?? 'inspect')),
                (string) ($row['type'] ?? 'record'),
                (string) ($row['application_id'] ?? $row['user_id'] ?? '?'),
                (string) ($row['email'] ?? '')
            ));
            $this->line('  Issues: '.implode(', ', (array) ($row['issues'] ?? [])));
            if (isset($row['canonical_agent_id'])) {
                $this->line('  Canonical agent: #'.(string) $row['canonical_agent_id']);
            }
            if (! empty($row['duplicate_active_agent_ids'])) {
                $this->line('  Duplicate active agents: '.implode(', ', array_map('strval', (array) $row['duplicate_active_agent_ids'])));
            }
            if (isset($row['agency_name'])) {
                $this->line('  Agency: '.(string) $row['agency_name'].' (#'.(string) ($row['agency_id'] ?? '—').')');
            }
            if (isset($row['before'], $row['after'])) {
                $this->line('  Before user agency: #'.(string) ($row['old_user_agency_id'] ?? '—'));
                $this->line('  After user agency: #'.(string) ($row['new_user_agency_id'] ?? '—'));
                $this->line('  Before canonical agent agency: #'.(string) ($row['old_canonical_agent_agency_id'] ?? '—'));
                $this->line('  After canonical agent agency: #'.(string) ($row['new_canonical_agent_agency_id'] ?? '—'));
                if (! empty($row['old_duplicate_agent_ids'])) {
                    $this->line('  Old duplicate agent ids: '.implode(', ', array_map('strval', (array) $row['old_duplicate_agent_ids'])));
                }
                if (array_key_exists('duplicate_rows_deactivated', $row)) {
                    $this->line('  Duplicate rows deactivated: '.(string) $row['duplicate_rows_deactivated']);
                }
                $this->line('  After issues: '.implode(', ', (array) ($row['after']['issues'] ?? [])) ?: 'none');
            } elseif (isset($row['after'])) {
                $this->line('  After issues: '.implode(', ', (array) ($row['after']['issues'] ?? [])) ?: 'none');
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: %s %d item(s), skipped %d.',
            $dryRun ? 'would repair' : 'repaired',
            (int) $result['repaired'],
            (int) $result['skipped']
        ));

        if ($dryRun) {
            $this->comment('Re-run with --force to apply these repairs.');
        }

        return self::SUCCESS;
    }
}
