<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkPhase9hDAuditService;
use Illuminate\Console\Command;

class JetpkPhase9hDAuditRunnerCommand extends Command
{
    protected $signature = 'jetpk:phase-9h-d-audits
                            {--client=jetpk : Client slug for deep-page UI audit}';

    protected $description = 'Run all JetPK 9H-D read-only audit gates and write reports to storage/app/audits/jetpk-9h-d/';

    public function handle(JetpkPhase9hDAuditService $service): int
    {
        $slug = (string) $this->option('client');
        $audits = [
            'empty-value' => fn () => $service->emptyValueAudit(),
            'media-coverage' => fn () => $service->mediaCoverageAudit(),
            'palette' => fn () => $service->paletteConsumptionAudit(),
            'settings-nav' => fn () => $service->settingsNavigationAudit(),
            'legacy-settings' => fn () => $service->legacySettingsRouteAudit(),
            'supplier-cards' => fn () => $service->supplierCardSelectorAudit(),
            'email-architecture' => fn () => $service->emailTemplateArchitectureAudit(),
            'ui-contract' => fn () => $service->dashboardUiContractAudit(),
            'deep-page-ui' => fn () => $service->adminDeepPageUiAudit($slug),
            'homepage-customization' => fn () => app(\App\Support\Audits\JetpkHomepageCustomizationCoverageAuditService::class)->run(),
        ];

        $fail = 0;
        foreach ($audits as $name => $runner) {
            $result = $runner();
            $status = ($result['fail'] ?? 0) > 0 ? 'FAIL' : 'PASS';
            if ($status === 'FAIL') {
                $fail++;
            }
            $this->line("[{$status}] {$name} → ".($result['path'] ?? ''));
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
