<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\RuntimeLayoutMigrationAuditService;
use Illuminate\Console\Command;

class OtaRuntimeLayoutMigrationAuditCommand extends Command
{
    protected $signature = 'ota:runtime-layout-migration-audit
                            {--client=haseeb-master : Client slug to audit layout migration for}';

    protected $description = 'MC-9A–9E read-only audit — runtime layout migration counts, safety checks, and HTTP probes';

    public function handle(
        ClientProfileResolver $profileResolver,
        RuntimeLayoutMigrationAuditService $auditService,
    ): int {
        $clientSlug = trim((string) $this->option('client'));

        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY runtime layout migration audit (MC-9A–9E).');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $profile = $profileResolver->resolveBySlug($clientSlug);
        if ($profile === null) {
            $this->error("Client profile not found for slug: {$clientSlug}");

            return self::FAILURE;
        }

        $this->info('Client slug: '.$clientSlug);
        $this->newLine();

        $result = $auditService->run($clientSlug);
        $counts = $result['counts'];

        $this->info('Migration counts');
        $this->table(
            ['metric', 'count'],
            [
                ['frontend migrated views', (string) $counts['frontend_migrated']],
                ['auth migrated views', (string) $counts['auth_migrated']],
                ['admin migrated views', (string) $counts['admin_migrated']],
                ['staff migrated views', (string) $counts['staff_migrated']],
                ['agent migrated views', (string) $counts['agent_migrated']],
                ['customer migrated views', (string) $counts['customer_migrated']],
                ['remaining layouts.frontend extends', (string) $counts['remaining_frontend']],
                ['remaining layouts.auth extends', (string) $counts['remaining_auth']],
                ['admin layouts.dashboard direct extends', (string) $counts['remaining_admin_dashboard']],
                ['staff layouts.dashboard direct extends', (string) $counts['remaining_staff_dashboard']],
                ['agent layouts.agent-portal direct extends', (string) $counts['remaining_agent_portal']],
                ['customer layouts.customer-account direct extends', (string) $counts['remaining_customer_account']],
                ['customer layouts.dashboard direct extends', (string) $counts['remaining_customer_dashboard']],
                ['deferred profile untouched count', (string) $counts['deferred_untouched']],
            ],
        );

        $this->newLine();
        $this->info('Migrated portal summary');
        $this->table(
            ['portal', 'migrated', 'remaining legacy extends'],
            [
                ['frontend', (string) $counts['frontend_migrated'], (string) $counts['remaining_frontend']],
                ['auth', (string) $counts['auth_migrated'], (string) $counts['remaining_auth']],
                ['admin', (string) $counts['admin_migrated'], (string) $counts['remaining_admin_dashboard']],
                ['staff', (string) $counts['staff_migrated'], (string) $counts['remaining_staff_dashboard']],
                ['agent', (string) $counts['agent_migrated'], (string) $counts['remaining_agent_portal']],
                [
                    'customer',
                    (string) $counts['customer_migrated'],
                    (string) ($counts['remaining_customer_account'] + $counts['remaining_customer_dashboard']),
                ],
            ],
        );

        $this->newLine();
        $this->info('Safety checks');
        $safety = $result['safety'];
        $this->table(
            ['check', 'status'],
            [
                ['Staff portal migration complete', $safety['remaining_staff_dashboard'] === 0 ? 'OK' : 'FAIL'],
                ['Agent portal migration complete', $safety['remaining_agent_portal'] === 0 ? 'OK' : 'FAIL'],
                ['Customer portal migration complete', $safety['remaining_customer_legacy'] === 0 ? 'OK' : 'FAIL'],
                ['profile/edit-dashboard untouched', $safety['profile_edit_dashboard_migrated'] ? 'FAIL' : 'OK'],
                ['profile/edit-agent untouched', $safety['profile_edit_agent_migrated'] ? 'FAIL' : 'OK'],
                ['profile/edit-frontend untouched', $safety['profile_edit_frontend_migrated'] ? 'FAIL' : 'OK'],
                ['Dev CP no @extends(client_layout)', $safety['dev_cp_has_layout_extends'] ? 'FAIL' : 'OK'],
                ['Supplier files untouched', $safety['supplier_has_client_layout'] ? 'FAIL' : 'OK'],
                ['Module gate files untouched', $safety['module_gate_has_client_layout'] ? 'FAIL' : 'OK'],
                ['Deferred path violations', (string) $counts['deferred_client_layout_violations']],
            ],
        );

        $this->newLine();
        $this->info('HTTP route probes');
        foreach ($result['http_checks'] as $check) {
            $this->line(sprintf(
                '  %s: expected=%s actual=%s status=%s',
                $check['name'],
                $check['expected'],
                $check['actual'],
                $check['ok'] ? 'OK' : 'FAIL',
            ));
        }
        $this->table(
            ['check', 'expected', 'actual', 'status'],
            array_map(static fn (array $row): array => [
                $row['name'],
                $row['expected'],
                $row['actual'],
                $row['ok'] ? 'OK' : 'FAIL',
            ], $result['http_checks']),
        );

        $this->newLine();
        $this->line((string) config('client_view_paths.mc9_note', 'MC-9 layout migration audit.'));

        if (! $safety['passed']) {
            $this->newLine();
            foreach ($safety['failures'] as $failure) {
                $this->error('  '.$failure);
            }
            $this->error('Runtime layout migration audit failed for '.$clientSlug.'.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Runtime layout migration audit passed for '.$clientSlug.'.');

        return self::SUCCESS;
    }
}
