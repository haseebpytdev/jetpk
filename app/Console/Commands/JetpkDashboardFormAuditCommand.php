<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Read-only audit — JetPK dashboard form structure (supplier form provider scoping).
 */
class JetpkDashboardFormAuditCommand extends Command
{
    protected $signature = 'jetpk:dashboard-form-audit';

    protected $description = 'Audit JetPK supplier form provider scoping, action bar placement, and form assets (read-only)';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY JetPK dashboard form audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $fail = 0;
        $warn = 0;
        $rows = [];

        $formPath = resource_path('views/dashboard/admin/api-settings/form.blade.php');
        $panelsDir = resource_path('views/dashboard/admin/api-settings/partials');
        $form = File::exists($formPath) ? File::get($formPath) : '';
        $panelFiles = File::isDirectory($panelsDir) ? File::allFiles($panelsDir) : [];
        $panelSource = $form;
        foreach ($panelFiles as $file) {
            $panelSource .= File::get($file->getPathname());
        }
        $cssPath = public_path('themes/admin/jetpakistan/css/dashboard.css');
        $jsPath = public_path('themes/admin/jetpakistan/js/supplier-form.js');

        $css = File::exists($cssPath) ? File::get($cssPath) : '';

        $checks = [
            ['supplier form exists', File::exists($formPath)],
            ['jp-is-hidden utility in CSS', File::exists($cssPath) && str_contains(File::get($cssPath), '.jp-is-hidden')],
            ['supplier-form.js exists', File::exists($jsPath)],
            ['provider panel partials', File::exists(resource_path('views/dashboard/admin/api-settings/partials/supplier-panels/sabre.blade.php'))],
            ['data-provider-panel markers', str_contains($panelSource, 'data-provider-panel')],
            ['unified connection status', str_contains($form, 'data-connection-status')],
            ['jp-action-bar (non-sticky)', str_contains($form, 'jp-action-bar')],
            ['advanced panel collapsed', str_contains($panelSource, 'jp-advanced-panel')],
            ['no bootstrap d-none in form tree', ! str_contains($panelSource, 'd-none')],
            ['create themed view', File::exists(resource_path('views/themes/admin/jetpakistan/api-settings/create.blade.php'))],
            ['edit themed view', File::exists(resource_path('views/themes/admin/jetpakistan/api-settings/edit.blade.php'))],
            ['form grid min-width 0', str_contains($css, 'min-width: 0') && str_contains($css, '.jp-field')],
            ['jp-control width 100%', str_contains($css, '.jp-control') && str_contains($css, 'width: 100%')],
            ['credentials grid gap 22px', str_contains($css, '--jp-form-gap') || str_contains($css, '22px')],
            ['sabre pcc row layout', str_contains($css, '[data-credential-field="pcc"]')],
            ['box-sizing border-box on controls', str_contains($css, 'box-sizing: border-box')],
        ];

        $brandingView = resource_path('views/themes/admin/jetpakistan/settings/branding.blade.php');
        $brandingSource = File::exists($brandingView) ? File::get($brandingView) : '';
        $brandingChecks = [
            ['branding page root scope', str_contains($brandingSource, 'jp-branding-page')],
            ['branding grid min-width 0', str_contains($css, '.jp-branding-page .jp-field') && str_contains($css, 'min-width: 0')],
            ['branding controls width 100%', str_contains($css, '.jp-branding-page .jp-control')],
            ['branding styled file inputs', str_contains($brandingSource, 'jp-file-control__input')],
            ['branding save action in form', str_contains($brandingSource, 'jp-branding-action-bar')],
            ['branding logo size slider', str_contains($brandingSource, 'data-jp-logo-size-slider')],
            ['no raw file input class jp-input', ! preg_match('/class="jp-input"\s+type="file"/', $brandingSource)],
        ];
        foreach ($brandingChecks as $item) {
            $checks[] = $item;
        }

        foreach ($checks as [$label, $ok]) {
            $rows[] = [$label, $ok ? 'pass' : 'fail'];
            if (! $ok) {
                $fail++;
            }
        }

        $forbidden = ['Parwaaz', 'YD Travel', 'YoursDomain'];
        foreach ($forbidden as $term) {
            if (str_contains($panelSource, $term)) {
                $rows[] = ["forbidden:{$term}", 'fail'];
                $fail++;
            }
        }

        if (str_contains(File::exists($cssPath) ? File::get($cssPath) : '', 'position: sticky') && str_contains(File::get($cssPath), '.jp-form-actions')) {
            $stickyBlock = preg_match('/\.jp-form-actions\s*\{[^}]*position:\s*sticky/s', File::get($cssPath));
            if ($stickyBlock) {
                $rows[] = ['sticky action bar disabled for supplier', 'warn'];
                $warn++;
            }
        }

        $this->table(['check', 'status'], $rows);
        $this->newLine();
        $this->line("pass=".(count($rows) - $fail - $warn)." warn={$warn} fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
