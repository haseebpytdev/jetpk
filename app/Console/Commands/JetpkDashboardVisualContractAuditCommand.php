<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Verifies dashboard visual spacing contract primitives.
 */
class JetpkDashboardVisualContractAuditCommand extends Command
{
    protected $signature = 'jetpk:dashboard-visual-contract-audit';

    protected $description = 'Audit JetPK dashboard visual spacing and layout contract (read-only)';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY visual contract audit.');
        $css = File::exists(public_path('themes/admin/jetpakistan/css/dashboard.css'))
            ? File::get(public_path('themes/admin/jetpakistan/css/dashboard.css'))
            : '';
        $fail = 0;

        $required = [
            '--sp-4' => 'section spacing token',
            '--sp-6' => 'section gap token',
            'min-width: 0' => 'grid shrink guard',
            'box-sizing: border-box' => 'control box model',
            '.jp-form-grid' => 'form grid pattern',
            '.jp-page-editor__preview' => 'page builder preview pane',
            '.jp-branding-page' => 'branding page scope',
            '.jp-logo-size-control' => 'logo size control',
            '.jp-color-field__row' => 'color field alignment',
        ];

        foreach ($required as $needle => $label) {
            $ok = str_contains($css, $needle);
            $this->line(($ok ? 'pass' : 'fail').' '.$label);
            if (! $ok) {
                $fail++;
            }
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
