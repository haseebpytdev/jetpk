<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use Illuminate\Console\Command;

class OtaClientLayoutAuditCommand extends Command
{
    protected $signature = 'ota:client-layout-audit
                            {--client=haseeb-master : Client slug to audit layout resolution for}';

    protected $description = 'MC-8D read-only audit — client theme layout resolution and legacy fallback';

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
        $this->line('Classification: READ-ONLY client layout resolution audit (MC-8D).');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $profile = $profileResolver->resolveBySlug($clientSlug);
        if ($profile === null) {
            $this->error("Client profile not found for slug: {$clientSlug}");

            return self::FAILURE;
        }

        $this->info('Client slug: '.$clientSlug);
        $this->newLine();

        $sampleRows = [];

        /** @var list<array{area: string, name: string, label: string}> $samples */
        $samples = config('client_view_paths.layout_audit_samples', []);

        foreach ($samples as $sample) {
            $resolution = $viewResolver->resolveLayoutSample($sample['name'], $sample['area'], $profile);

            $sampleRows[] = [
                $resolution['area'],
                $sample['label'],
                $resolution['selected_theme'] ?? '(empty)',
                $resolution['resolved_theme'],
                $resolution['requested_layout'],
                $resolution['resolved_layout_name'],
                $resolution['fallback_used'] ? 'yes' : 'no',
                $resolution['theme_layout_exists'] ? 'yes' : 'no',
                $resolution['legacy_layout_exists'] ? 'yes' : 'no',
            ];
        }

        $this->info('Sample layout resolution');
        $this->table(
            [
                'area',
                'sample',
                'selected_theme',
                'resolved_theme',
                'requested_layout',
                'resolved_layout',
                'fallback_used',
                'theme_layout_exists',
                'legacy_layout_exists',
            ],
            $sampleRows,
        );

        $this->newLine();
        $this->info('Resolved layout paths');
        foreach ($samples as $sample) {
            $resolution = $viewResolver->resolveLayoutSample($sample['name'], $sample['area'], $profile);
            $this->line(sprintf(
                '  %s: %s',
                $sample['label'],
                $resolution['resolved_layout_name'],
            ));
        }

        $this->newLine();
        $this->line((string) config('client_view_paths.mc8d_note', 'MC-8D layout resolver active.'));
        $this->newLine();
        $this->info('Client layout audit completed for '.$clientSlug.'.');

        return self::SUCCESS;
    }
}
