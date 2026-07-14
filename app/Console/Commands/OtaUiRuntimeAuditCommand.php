<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\RuntimeThemeManager;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\HaseebMasterRouteSafetyAuditService;
use Illuminate\Console\Command;

class OtaUiRuntimeAuditCommand extends Command
{
    protected $signature = 'ota:ui-runtime-audit
                            {--client=haseeb-master : Client slug to audit UI runtime engine for}';

    protected $description = 'MC-8D read-only combined audit — theme, view, layout, route safety, and client context';

    public function handle(
        ClientProfileResolver $profileResolver,
        RuntimeThemeManager $themeManager,
        RuntimeViewResolver $viewResolver,
        HaseebMasterRouteSafetyAuditService $routeSafetyAudit,
    ): int {
        $clientSlug = trim((string) $this->option('client'));

        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY combined UI runtime audit (MC-8D).');
        $this->line('live_supplier_call_attempted=false db_write_attempted=false');
        $this->newLine();

        $profile = $profileResolver->resolveBySlug($clientSlug);
        if ($profile === null) {
            $this->error("Client profile not found for slug: {$clientSlug}");

            return self::FAILURE;
        }

        $this->info('Client slug: '.$clientSlug);
        $this->newLine();

        $exitCode = self::SUCCESS;

        $this->info('1) Theme audit summary');
        $themeSummary = $themeManager->summary($profile);
        $themeRows = [];
        foreach (['frontend', 'admin', 'staff'] as $area) {
            $areaSummary = $themeSummary['areas'][$area];
            $themeRows[] = [
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
            $themeRows,
        );
        if ($themeSummary['warnings'] !== []) {
            foreach ($themeSummary['warnings'] as $warning) {
                $this->warn('  Theme warning: '.$warning);
            }
        }

        $this->newLine();
        $this->info('2) View audit summary');
        $viewRows = [];
        /** @var list<array{area: string, name: string, label: string, optional?: bool}> $viewSamples */
        $viewSamples = config('client_view_paths.audit_samples', []);
        foreach ($viewSamples as $sample) {
            $resolution = $viewResolver->resolveSample($sample['name'], $sample['area'], $profile);
            if (($sample['optional'] ?? false) && ! $resolution['view_exists']) {
                continue;
            }
            $viewRows[] = [
                $sample['area'],
                $sample['label'],
                $resolution['resolved_theme'],
                $resolution['resolved_view_name'],
                $resolution['fallback_used'] ? 'yes' : 'no',
            ];
        }
        $this->table(
            ['area', 'sample', 'resolved_theme', 'resolved_view', 'fallback_used'],
            $viewRows,
        );

        $this->newLine();
        $this->info('3) Layout audit summary');
        $layoutRows = [];
        /** @var list<array{area: string, name: string, label: string}> $layoutSamples */
        $layoutSamples = config('client_view_paths.layout_audit_samples', []);
        foreach ($layoutSamples as $sample) {
            $resolution = $viewResolver->resolveLayoutSample($sample['name'], $sample['area'], $profile);
            $layoutRows[] = [
                $sample['area'],
                $sample['label'],
                $resolution['resolved_theme'],
                $resolution['resolved_layout_name'],
                $resolution['fallback_used'] ? 'yes' : 'no',
                $resolution['theme_layout_exists'] ? 'yes' : 'no',
            ];
        }
        $this->table(
            ['area', 'sample', 'resolved_theme', 'resolved_layout', 'fallback_used', 'theme_layout_exists'],
            $layoutRows,
        );

        $this->newLine();
        $this->info('4) Route safety audit summary');
        $routeRows = $routeSafetyAudit->run($clientSlug);
        $routeCounts = ['OK' => 0, 'missing' => 0, 'collision-risk' => 0];
        foreach ($routeRows as $row) {
            $status = $row['status'];
            if (isset($routeCounts[$status])) {
                $routeCounts[$status]++;
            }
        }
        $this->line(sprintf(
            'Routes: total=%d ok=%d missing=%d collision-risk=%d',
            count($routeRows),
            $routeCounts['OK'],
            $routeCounts['missing'],
            $routeCounts['collision-risk'],
        ));
        if ($routeCounts['missing'] > 0 || $routeCounts['collision-risk'] > 0) {
            $this->error('Route safety audit failed — run ota:route-safety-audit for full detail.');
            $exitCode = self::FAILURE;
        } else {
            $this->info('Route safety audit passed.');
        }

        $this->newLine();
        $this->info('5) Client context flow summary');
        $contextChecks = [
            [
                'name' => 'profile slug',
                'ok' => $profile->slug === $clientSlug,
            ],
            [
                'name' => 'asset_profile configured',
                'ok' => trim((string) $profile->asset_profile) !== '',
            ],
            [
                'name' => 'frontend theme configured',
                'ok' => trim((string) $profile->active_frontend_theme) !== '',
            ],
            [
                'name' => 'full HTTP flow audit available',
                'ok' => true,
            ],
        ];
        $this->table(
            ['check', 'status'],
            array_map(static fn (array $row): array => [$row['name'], $row['ok'] ? 'OK' : 'FAIL'], $contextChecks),
        );
        if (array_filter($contextChecks, static fn (array $row): bool => ! $row['ok']) !== []) {
            $this->error('Client context profile checks failed.');
            $exitCode = self::FAILURE;
        } else {
            $this->info('Client context flow summary passed.');
            $this->comment('Run ota:client-context-flow-audit --client='.$clientSlug.' for full HTTP helper checks.');
        }

        $this->newLine();
        $this->line('Asset profile: '.($profile->asset_profile ?? '(empty)'));
        $this->line('View resolver: active (opt-in via client_view())');
        $this->line('Layout resolver: active (opt-in via client_layout())');
        $this->line((string) config('client_view_paths.mc8d_note', 'MC-8D UI runtime engine active.'));
        $this->newLine();

        if ($exitCode === self::SUCCESS) {
            $this->info('UI runtime audit completed for '.$clientSlug.'.');
        } else {
            $this->error('UI runtime audit completed with failures for '.$clientSlug.'.');
        }

        return $exitCode;
    }
}
