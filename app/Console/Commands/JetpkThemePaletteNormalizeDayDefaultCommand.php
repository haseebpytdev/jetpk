<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Branding\JetpkThemePaletteService;
use Illuminate\Console\Command;

class JetpkThemePaletteNormalizeDayDefaultCommand extends Command
{
    protected $signature = 'jetpk:theme-palette-normalize-day-default {--dry-run : Report only; do not write settings}';

    protected $description = 'Normalize legacy JetPakistan Day primary to approved #63B32E when safe';

    public function handle(JetpkThemePaletteService $paletteService): int
    {
        if (! $paletteService->isJetpkScoped()) {
            $this->error('Not a JetPakistan deployment.');

            return self::FAILURE;
        }

        $agency = Agency::query()->orderBy('id')->first();
        if ($agency === null) {
            $this->error('No agency found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $plan = $paletteService->normalizeDayPrimaryDefault($agency, $dryRun);

        $this->line('action='.$plan['action']);
        $this->line('source='.$plan['source']);
        $this->line('current='.$plan['current']);
        $this->line('target='.$plan['target']);
        $this->line('customized='.($plan['customized'] ? '1' : '0'));
        $this->line('dry_run='.($dryRun ? '1' : '0'));

        if ($plan['action'] === 'preserve' && $plan['customized']) {
            if ($plan['current'] !== $plan['target']) {
                $this->warn('Day primary preserved (intentional admin customization; differs from approved default).');
            } else {
                $this->warn('Day primary preserved (intentional admin customization).');
            }
        } elseif ($plan['action'] === 'normalize' && $dryRun) {
            $this->info('Would normalize legacy Day primary to approved default.');
        } elseif ($plan['action'] === 'normalize') {
            $this->info('Day primary normalized to approved default.');
        } else {
            $this->info('No Day primary normalization required.');
        }

        return self::SUCCESS;
    }
}
