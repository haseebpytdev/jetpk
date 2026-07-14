<?php

namespace App\Console\Commands;

use App\Support\Client\ClientPublicWebrootPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Read-only JetPK responsive UI hotfix audit (JETPK-RESPONSIVE-RESULTS-8A).
 */
class OtaJetpkResponsiveUiAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-responsive-ui-audit
                            {--client=jetpk : Client slug (informational)}';

    protected $description = 'Read-only audit — JetPK responsive results/search/OTP UI markers from phase 8A';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY JetPK responsive UI audit (8A).');
        $this->newLine();

        $ctx = ClientPublicWebrootPath::auditContext();
        $this->line('configured_public_webroot='.($ctx['configured_public_webroot'] !== '' ? $ctx['configured_public_webroot'] : '(not set)'));
        $this->line('laravel_public_path='.$ctx['laravel_public_path']);
        $this->line('resolved_asset_root='.$ctx['resolved_asset_root']);
        $this->newLine();

        $warn = 0;
        if ($ctx['using_configured'] && ! is_dir($ctx['laravel_public_path'].'/themes/frontend/jetpakistan')) {
            $this->warn('Laravel public path missing JetPK theme tree, but configured live webroot exists and is used for runtime asset checks.');
            $warn++;
        }

        $checks = [
            $this->checkThemeFile('public/themes/frontend/jetpakistan/css/results.css'),
            $this->checkThemeFile('public/themes/frontend/jetpakistan/css/flight-cards.css'),
            $this->checkThemeFile('public/themes/frontend/jetpakistan/css/jp-search.css'),
            $this->checkThemeFile('public/themes/frontend/jetpakistan/css/forms.css'),
            $this->checkThemeSelector('public/themes/frontend/jetpakistan/css/flight-cards.css', 'JETPK-RESPONSIVE-RESULTS-8A', 'responsive 8A CSS marker'),
            $this->checkThemeSelector('public/themes/frontend/jetpakistan/css/flight-cards.css', '.ota-branded-fares-panel__grid', 'branded fare grid selectors'),
            $this->checkSelector('resources/views/frontend/flights/partials/results-page.blade.php', 'collapseOtherBrandedFares', 'one-open branded fare tray helper'),
            $this->checkFile('app/Support/Suppliers/SupplierSourceVisibility.php'),
            $this->checkSelector('resources/views/frontend/flights/partials/results-page.blade.php', 'data-can-see-supplier-source', 'supplier source visibility guard'),
            $this->checkThemeSelector('public/themes/frontend/jetpakistan/js/forms.js', 'initOtpResendCountdown', 'OTP resend countdown'),
            $this->checkSelector('resources/views/themes/frontend/jetpakistan/components/search/flights-panel.blade.php', 'jp-search-submit-text">Search</span>', 'home search button label'),
            $this->checkThemeSelector('public/themes/frontend/jetpakistan/css/forms.css', ':focus:not(:focus-visible)', 'mouse-click focus ring fix'),
            $this->checkThemeSelector('public/themes/frontend/jetpakistan/css/jp-search.css', 'jp-airport-field', 'From/To airport field polish'),
        ];

        $fail = 0;
        $rows = [];
        foreach ($checks as $check) {
            if (! $check['ok']) {
                $fail++;
            }
            $rows[] = [
                'check' => $check['name'],
                'status' => $check['ok'] ? 'PASS' : 'FAIL',
                'detail' => $check['detail'],
            ];
        }

        $this->table(['Check', 'Status', 'Detail'], $rows);
        $this->newLine();
        $this->line('fail='.$fail.' warn='.$warn);

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkFile(string $relativePath): array
    {
        $path = base_path($relativePath);
        $exists = File::exists($path);

        return [
            'name' => 'file: '.$relativePath,
            'ok' => $exists,
            'detail' => $exists ? 'present' : 'MISSING',
        ];
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkThemeFile(string $publicRelative): array
    {
        $resolved = ClientPublicWebrootPath::publicRelativePath($publicRelative);
        $exists = ClientPublicWebrootPath::publicRelativeExists($publicRelative);

        return [
            'name' => 'file: '.$publicRelative,
            'ok' => $exists,
            'detail' => $exists ? 'present at '.$resolved : 'MISSING (checked: '.$resolved.')',
        ];
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkSelector(string $relativePath, string $needle, string $label): array
    {
        $path = base_path($relativePath);
        if (! File::exists($path)) {
            return [
                'name' => $label,
                'ok' => false,
                'detail' => 'file missing: '.$relativePath,
            ];
        }

        $contents = File::get($path);
        $found = str_contains($contents, $needle);

        return [
            'name' => $label,
            'ok' => $found,
            'detail' => $found ? 'found in '.$relativePath : 'not found in '.$relativePath,
        ];
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function checkThemeSelector(string $publicRelative, string $needle, string $label): array
    {
        $resolved = ClientPublicWebrootPath::publicRelativePath($publicRelative);
        $contents = ClientPublicWebrootPath::readPublicRelative($publicRelative);
        if ($contents === null) {
            return [
                'name' => $label,
                'ok' => false,
                'detail' => 'file missing: '.$publicRelative.' (checked: '.$resolved.')',
            ];
        }

        $found = str_contains($contents, $needle);

        return [
            'name' => $label,
            'ok' => $found,
            'detail' => $found ? 'found in '.$resolved : 'not found in '.$resolved,
        ];
    }
}
