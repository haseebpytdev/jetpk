<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use Illuminate\Console\Command;

class OtaClientViewAuditCommand extends Command
{
    protected $signature = 'ota:client-view-audit
                            {--client=haseeb-master : Client slug to audit view resolution for}';

    protected $description = 'MC-8B read-only audit — client theme view roots, sample resolution, and legacy fallback';

    public function handle(
        ClientProfileResolver $profileResolver,
        RuntimeViewResolver $viewResolver,
    ): int {
        $clientSlug = trim((string) $this->option('client'));

        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY client view resolution audit (MC-8B).');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $profile = $profileResolver->resolveBySlug($clientSlug);
        if ($profile === null) {
            $this->error("Client profile not found for slug: {$clientSlug}");

            return self::FAILURE;
        }

        $this->info('Client slug: '.$clientSlug);
        $this->newLine();

        $areaRows = [];
        foreach ($viewResolver->summary(null, $profile) as $areaSummary) {
            $areaRows[] = [
                $areaSummary['area'],
                $areaSummary['resolved_theme'],
                $areaSummary['theme_view_root'],
                $areaSummary['theme_root_exists'] ? 'yes' : 'no',
                $areaSummary['fallback_root'],
            ];
        }

        $this->info('Area view roots');
        $this->table(
            ['area', 'resolved_theme', 'theme_view_root', 'theme_root_exists', 'fallback_root'],
            $areaRows,
        );

        $this->newLine();
        $this->info('Sample view resolution');
        $sampleRows = [];

        /** @var list<array{area: string, name: string, label: string, optional?: bool}> $samples */
        $samples = config('client_view_paths.audit_samples', []);

        foreach ($samples as $sample) {
            $resolution = $viewResolver->resolveSample($sample['name'], $sample['area'], $profile);

            if (($sample['optional'] ?? false) && ! $resolution['view_exists']) {
                continue;
            }

            $sampleRows[] = [
                $sample['area'],
                $sample['label'],
                $resolution['resolved_theme'],
                $resolution['resolved_view_name'],
                $resolution['fallback_used'] ? 'yes' : 'no',
                $resolution['view_exists'] ? 'yes' : 'no',
            ];
        }

        $this->table(
            ['area', 'sample', 'resolved_theme', 'resolved_view', 'fallback_used', 'view_exists'],
            $sampleRows,
        );

        $this->newLine();
        $this->line((string) config('client_view_paths.mc8b_note', 'MC-8B resolver active; layouts not migrated yet.'));
        $this->newLine();
        $this->info('Client view audit completed for '.$clientSlug.'.');

        return self::SUCCESS;
    }
}
