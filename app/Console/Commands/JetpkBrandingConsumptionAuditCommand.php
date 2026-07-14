<?php

namespace App\Console\Commands;

use App\Support\Branding\JetpkCompanyBrandingResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Audits JetPK Company Branding ownership and consumer mapping.
 */
class JetpkBrandingConsumptionAuditCommand extends Command
{
    protected $signature = 'jetpk:branding-consumption-audit';

    protected $description = 'Audit JetPK Company Branding canonical ownership and logo consumers (read-only)';

    public function handle(JetpkCompanyBrandingResolver $branding): int
    {
        $this->line('Classification: READ-ONLY JetPK branding consumption audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $fail = 0;
        $warn = 0;

        if (! $branding->isJetpkDeployment()) {
            $this->warn('Not a JetPK single-client deployment — audit is informational only.');
        }

        $this->line('canonical_owner=admin.settings.branding.edit');
        $this->line('company_name='.$branding->companyName());
        $this->line('logo_url='.($branding->logoUrl() ?? 'fallback-wordmark'));
        $this->line('favicon_url='.($branding->faviconUrl() ?? 'none'));
        $this->newLine();

        $rows = [];
        foreach ($branding->consumptionMatrix() as $row) {
            $viewExists = File::exists(resource_path('views/'.$row['view']));
            $status = $viewExists ? 'pass' : 'warn';
            if (! $viewExists) {
                $warn++;
            }
            $rows[] = [$row['consumer'], $row['view'], $row['resolver'], $row['status'], $status];
        }

        $this->table(['consumer', 'view', 'resolver', 'ownership', 'check'], $rows);

        $header = resource_path('views/themes/frontend/jetpakistan/partials/header.blade.php');
        if (File::exists($header) && str_contains(File::get($header), 'is_client_preview() ? client_branding()->logoUrl()')) {
            $this->error('Header still gates logo behind is_client_preview()');
            $fail++;
        } else {
            $this->line('header_preview_gate=removed');
        }

        $globalMedia = resource_path('views/themes/admin/jetpakistan/page-settings/partials/branding-ownership.blade.php');
        if (! File::exists($globalMedia)) {
            $this->error('Missing Page Settings branding ownership card');
            $fail++;
        }

        $forbidden = ['Parwaaz', 'YD Travel', 'haseeb-master'];
        foreach ($forbidden as $needle) {
            if (str_contains(File::get($header), $needle)) {
                $this->error("Forbidden brand leak in header: {$needle}");
                $fail++;
            }
        }

        $resolverChecks = [
            ['headerLogoHeight() method', method_exists($branding, 'headerLogoHeight')],
            ['header logo height default 24-72', $branding->headerLogoHeight() >= 24 && $branding->headerLogoHeight() <= 72],
            ['frontend layout injects logo height CSS', str_contains(File::get(resource_path('views/themes/frontend/jetpakistan/layouts/frontend.blade.php')), '--jp-header-logo-height')],
            ['public header uses CSS variable', str_contains(File::get(public_path('themes/frontend/jetpakistan/css/theme.css')), '--jp-header-logo-height')],
        ];
        foreach ($resolverChecks as [$label, $ok]) {
            $this->line(($ok ? 'pass' : 'fail').' '.$label);
            if (! $ok) {
                $fail++;
            }
        }

        $paletteCss = $branding->publicCssVariables();
        $palettePass = array_key_exists('--brand', $paletteCss) || array_key_exists('--jp-header-logo-height', $paletteCss);
        $this->line(($palettePass ? 'pass' : 'fail').' company branding palette CSS variables');
        if (! $palettePass) {
            $fail++;
        }

        $brandingView = resource_path('views/themes/admin/jetpakistan/settings/branding.blade.php');
        if (File::exists($brandingView)) {
            $brandingSource = File::get($brandingView);
            $brandingChecks = [
                ['branding page scoped root', str_contains($brandingSource, 'jp-branding-page')],
                ['logo size slider field', str_contains($brandingSource, 'header_logo_height')],
                ['styled file controls', str_contains($brandingSource, 'jp-file-control') && ! preg_match('/class="jp-input"\s+type="file"/', $brandingSource)],
                ['save action inside form', str_contains($brandingSource, 'jp-branding-action-bar')],
            ];
            foreach ($brandingChecks as [$label, $ok]) {
                $this->line(($ok ? 'pass' : 'fail').' '.$label);
                if (! $ok) {
                    $fail++;
                }
            }
        }

        $heroView = resource_path('views/themes/frontend/jetpakistan/sections/hero.blade.php');
        $themeCss = File::exists(public_path('themes/frontend/jetpakistan/css/theme.css'))
            ? File::get(public_path('themes/frontend/jetpakistan/css/theme.css'))
            : '';
        if (File::exists($heroView)) {
            $heroSource = File::get($heroView);
            $heroChecks = [
                ['hero image mode skips route network', str_contains($heroSource, '@unless ($jpHeroBg)')],
                ['hero readability layer', str_contains($heroSource, 'hero-readability')],
                ['lighter hero image gradient', str_contains($themeCss, 'hero.hero--has-image') && str_contains($themeCss, 'transparent 72%')],
            ];
            foreach ($heroChecks as [$label, $ok]) {
                $this->line(($ok ? 'pass' : 'fail').' '.$label);
                if (! $ok) {
                    $fail++;
                }
            }
        }

        $this->newLine();
        $this->line("pass=".count($rows)." warn={$warn} fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
