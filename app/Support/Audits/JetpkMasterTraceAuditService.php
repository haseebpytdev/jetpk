<?php

namespace App\Support\Audits;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Classifies master/legacy branding traces across the JetPK fork codebase.
 */
final class JetpkMasterTraceAuditService
{
    /** @var list<string> */
    public const SEARCH_TERMS = [
        'Parwaaz',
        'Parwaaz Travels',
        'YD Travel',
        'YoursDomain',
        'YoursDomain.com',
        'haseeb-master',
        'haseeb_master',
        'Master Client',
        'Asif Travels',
        'Hyatt Travel Solutions',
        'hayattravelsolutions',
        'ota.haseebasif.com',
        'sales@yoursdomain.com',
        'support@hayattravelsolutions.com',
        '+92 300 0000000',
        'placeholder 123',
        '{{ brand_name }}',
        '{{ client_name }}',
        '{{ company_name }}',
    ];

    /** @var list<string> */
    private const SCAN_ROOTS = [
        'app',
        'bootstrap',
        'config',
        'database',
        'public',
        'resources',
        'routes',
        'storage/app/email-previews',
        'tests',
        'tools',
        'docs',
    ];

    /** @var list<string> */
    private const SKIP_PATH_FRAGMENTS = [
        'vendor/',
        'node_modules/',
        '_jetpk-package-temp/',
        '_production_baselines/',
        '11k-s2-upload/',
        'storage/framework/',
        'storage/logs/',
        'storage/app/audits/',
        'storage/app/jetpk-theme-extract/',
    ];

    /** @var list<string> */
    private const FAIL_BUCKETS = [
        'visible_public_ui_leak',
        'visible_dashboard_leak',
        'visible_checkout_leak',
        'visible_email_leak',
        'visible_error_page_leak',
        'visible_devcp_leak',
        'visible_asset_leak',
        'database_branding_leak',
        'route_generation_risk',
        'root_mode_risk',
    ];

    /**
     * @return array{
     *     findings: list<array{path:string,line:int,term:string,bucket:string}>,
     *     fail_count: int,
     *     warn_count: int,
     *     by_bucket: array<string, int>
     * }
     */
    public function run(bool $includeEnv = true): array
    {
        $findings = [];
        $roots = $includeEnv ? array_merge(self::SCAN_ROOTS, ['.env', '.env.example']) : self::SCAN_ROOTS;

        foreach ($roots as $root) {
            $absolute = base_path($root);
            if (is_file($absolute)) {
                $this->scanFile($absolute, $findings);

                continue;
            }
            if (! is_dir($absolute)) {
                continue;
            }

            $finder = (new Finder)->files()->in($absolute);
            foreach ($finder as $file) {
                $this->scanFile($file->getRealPath() ?: '', $findings);
            }
        }

        $byBucket = [];
        foreach ($findings as $finding) {
            $byBucket[$finding['bucket']] = ($byBucket[$finding['bucket']] ?? 0) + 1;
        }

        $failCount = 0;
        $warnCount = 0;
        foreach ($findings as $finding) {
            if (in_array($finding['bucket'], self::FAIL_BUCKETS, true)) {
                $failCount++;
            } else {
                $warnCount++;
            }
        }

        return [
            'findings' => $findings,
            'fail_count' => $failCount,
            'warn_count' => $warnCount,
            'by_bucket' => $byBucket,
        ];
    }

    /**
     * @param  list<array{path:string,line:int,term:string,bucket:string}>  $findings
     */
    private function scanFile(string $path, array &$findings): void
    {
        if ($path === '' || ! is_file($path)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
        foreach (self::SKIP_PATH_FRAGMENTS as $skip) {
            if (str_contains($relative, $skip)) {
                return;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['php', 'js', 'css', 'json', 'md', 'xml', 'txt', 'html'], true)
            && ! str_ends_with($relative, '.env')
            && ! str_ends_with($relative, '.env.example')) {
            return;
        }

        $contents = File::get($path);
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];

        foreach (self::SEARCH_TERMS as $term) {
            foreach ($lines as $index => $line) {
                if (! str_contains($line, $term)) {
                    continue;
                }

                $findings[] = [
                    'path' => $relative,
                    'line' => $index + 1,
                    'term' => $term,
                    'bucket' => $this->classify($relative, $line, $term),
                ];
            }
        }
    }

    private function classify(string $relative, string $line, string $term): string
    {
        $lower = strtolower($relative.' '.$line);

        if (str_contains($relative, 'storage/logs/') || str_contains($relative, 'phpunit')) {
            return 'stale_log_or_cache_only';
        }

        if (str_contains($relative, 'tests/') || str_contains($relative, 'docs/audits/')) {
            return 'safe_test_or_doc_reference';
        }

        if (str_contains($relative, 'config/') && ! str_contains($lower, 'jetpakistan')) {
            if (in_array($term, ['haseeb-master', 'ota.haseebasif.com', 'YoursDomain', 'Master Client'], true)) {
                return 'safe_backend_compatibility_reference';
            }
        }

        if (str_contains($relative, 'public/themes/frontend/jetpakistan/css/') && (str_contains($lower, 'parwaaz') || str_contains($lower, 'master client'))) {
            return 'safe_backend_compatibility_reference';
        }

        if (str_contains($relative, 'booking.css') && str_contains($lower, 'parwaaz')) {
            return 'safe_backend_compatibility_reference';
        }

        if (str_contains($relative, 'resources/views/emails/themes/jetpakistan/')) {
            return 'visible_email_leak';
        }

        if (str_contains($relative, 'resources/views/developer/') || str_contains($relative, 'layouts/developer')) {
            return 'visible_devcp_leak';
        }

        if (str_contains($relative, 'frontend/booking/') || str_contains($relative, 'booking/')) {
            return 'visible_checkout_leak';
        }

        if (str_contains($relative, 'themes/admin/jetpakistan')
            || str_contains($relative, 'themes/staff/jetpakistan')
            || str_contains($relative, 'themes/agent/jetpakistan')
            || str_contains($relative, 'themes/customer/jetpakistan')
            || str_contains($relative, 'dashboard/')) {
            return 'visible_dashboard_leak';
        }

        if (str_contains($relative, 'themes/frontend/jetpakistan') || str_contains($relative, 'components/jp/')) {
            return 'visible_public_ui_leak';
        }

        if (str_contains($relative, 'public/themes/frontend/jetpakistan') || str_contains($relative, 'public/themes/admin/jetpakistan')) {
            return 'visible_asset_leak';
        }

        if (str_contains($relative, 'database/seeders') || str_contains($relative, 'database/migrations')) {
            return 'safe_backend_compatibility_reference';
        }

        if (str_contains($relative, 'client_helpers.php') || str_contains($relative, 'ClientPrefixedRouteRegistrar')) {
            return 'route_generation_risk';
        }

        if (str_contains($relative, 'docs/')) {
            return 'safe_test_or_doc_reference';
        }

        if (str_contains($relative, 'dashboard/admin/settings/') && str_contains($lower, 'placeholder')) {
            return 'safe_backend_compatibility_reference';
        }

        if (str_contains($relative, 'Console/Commands/Jetpk')) {
            return 'safe_backend_compatibility_reference';
        }

        if (preg_match('#/(jetpk|haseeb-master)/#', $line) === 1) {
            if (str_contains($relative, 'themes/frontend/jetpakistan')
                || str_contains($relative, 'themes/admin/jetpakistan')
                || str_contains($relative, 'components/jp/')
                || str_contains($relative, 'resources/views/emails/themes/jetpakistan/')) {
                return 'root_mode_risk';
            }

            return 'safe_backend_compatibility_reference';
        }

        if (str_contains($relative, 'app/Support/') || str_contains($relative, 'app/Services/Client/')) {
            return 'safe_backend_compatibility_reference';
        }

        return 'needs_manual_review';
    }
}
