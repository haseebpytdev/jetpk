<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\RuntimeThemeManager;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use Illuminate\Console\Command;

class OtaClientThemeAuditCommand extends Command
{
    protected $signature = 'ota:client-theme-audit
                            {--client=haseeb-master : Client slug to audit theme resolution for}';

    protected $description = 'MC-8A read-only audit — client profile theme selection, resolution, and registry status';

    public function handle(
        ClientProfileResolver $profileResolver,
        RuntimeThemeManager $themeManager,
    ): int {
        $clientSlug = trim((string) $this->option('client'));

        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY client theme audit (MC-8A).');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $profile = $profileResolver->resolveBySlug($clientSlug);
        if ($profile === null) {
            $this->error("Client profile not found for slug: {$clientSlug}");

            return self::FAILURE;
        }

        $summary = $themeManager->summary($profile);

        $this->info('Client slug: '.$clientSlug);
        $this->newLine();

        $rows = [];
        foreach (['frontend', 'admin', 'staff'] as $area) {
            $areaSummary = $summary['areas'][$area];
            $rows[] = [
                $area,
                $areaSummary['selected'] ?? '(empty)',
                $areaSummary['resolved'],
                $areaSummary['used_fallback'] ? 'yes' : 'no',
                $areaSummary['registry_valid'] ? 'yes' : 'no',
                $areaSummary['on_disk'] ? 'yes' : 'no',
                $areaSummary['asset_base'],
            ];
        }

        $this->table(
            ['area', 'selected', 'resolved', 'fallback', 'registry_valid', 'on_disk', 'asset_base'],
            $rows,
        );

        if ($summary['warnings'] !== []) {
            $this->newLine();
            $this->warn('Theme warnings:');
            foreach ($summary['warnings'] as $warning) {
                $this->line('  - '.$warning);
            }
        }

        $this->newLine();
        $this->info('Client theme audit completed for '.$clientSlug.'.');

        return self::SUCCESS;
    }
}
