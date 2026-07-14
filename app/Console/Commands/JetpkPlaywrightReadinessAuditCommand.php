<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Verifies local environment is ready for Playwright dashboard QA.
 */
class JetpkPlaywrightReadinessAuditCommand extends Command
{
    protected $signature = 'jetpk:playwright-readiness-audit';

    protected $description = 'Audit Playwright QA readiness (fixtures, config, runner)';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY Playwright readiness audit.');
        $fail = 0;

        $checks = [
            ['playwright.jetpk-9h-b.config.ts', File::exists(base_path('playwright.jetpk-9h-b.config.ts'))],
            ['tests/playwright/jetpk-9h-b', is_dir(base_path('tests/playwright/jetpk-9h-b'))],
            ['runner script', File::exists(base_path('scripts/run-jetpk-dashboard-qa.ps1'))],
            ['fixtures command', Artisan::all()['jetpk:playwright-fixtures'] ?? false],
            ['node_modules', is_dir(base_path('node_modules'))],
        ];

        foreach ($checks as [$label, $ok]) {
            $this->line(($ok ? 'pass' : 'warn').' '.$label);
            if (! $ok && $label !== 'node_modules') {
                $fail++;
            }
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
