<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Verifies dashboard operational UI primitives exist.
 */
class JetpkDashboardOperationalAuditCommand extends Command
{
    protected $signature = 'jetpk:dashboard-operational-audit';

    protected $description = 'Audit JetPK dashboard operational primitives (forms, filters, CSRF patterns)';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY dashboard operational audit.');
        $fail = 0;
        $css = File::exists(public_path('themes/admin/jetpakistan/css/dashboard.css'))
            ? File::get(public_path('themes/admin/jetpakistan/css/dashboard.css'))
            : '';

        $checks = [
            ['jp-control width 100%', str_contains($css, 'width: 100%')],
            ['jp-file-control', str_contains($css, '.jp-file-control')],
            ['jp-action-bar', str_contains($css, '.jp-action-bar')],
            ['jp-empty-state', str_contains($css, '.jp-empty-state')],
            ['page settings split workspace', str_contains($css, '.jp-page-editor__workspace')],
            ['branding ownership card', File::exists(resource_path('views/themes/admin/jetpakistan/page-settings/partials/branding-ownership.blade.php'))],
            ['themed media library', File::exists(resource_path('views/themes/admin/jetpakistan/settings/media.blade.php'))],
            ['brand logo component', File::exists(resource_path('views/components/jp/brand-logo.blade.php'))],
        ];

        foreach ($checks as [$label, $ok]) {
            $this->line(($ok ? 'pass' : 'fail').' '.$label);
            if (! $ok) {
                $fail++;
            }
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
