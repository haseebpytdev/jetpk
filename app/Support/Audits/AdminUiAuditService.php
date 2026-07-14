<?php

namespace App\Support\Audits;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

/**
 * Read-only admin v1 UI inventory for OTA-ADMIN-ADB-AUDIT-1.
 *
 * Scans Blade/CSS paths only — no DB, no HTTP, no writes.
 */
final class AdminUiAuditService
{
    /** @var list<string> */
    private const ADMIN_BLADE_ROOTS = [
        'resources/views/dashboard/admin',
    ];

    /** @var list<string> */
    private const ADMIN_LAYOUT_PATHS = [
        'resources/views/layouts/dashboard.blade.php',
        'resources/views/themes/admin/default-admin/layouts/dashboard.blade.php',
        'resources/views/layouts/partials/dashboard-sidebar-admin.blade.php',
    ];

    /** @var list<string> */
    private const ADMIN_CSS_PATHS = [
        'public/css/ota-design-system.css',
        'resources/css/dashboard.css',
    ];

    /** @var list<string> */
    private const ADMIN_CSS_GLOBS = [
        'public/vendor/tabler/css/*.css',
    ];

    /**
     * @return array{
     *     admin_layout_files_count: int,
     *     admin_blade_files_count: int,
     *     admin_css_files_count: int,
     *     inline_style_occurrences: int,
     *     page_style_push_count: int,
     *     button_class_patterns: array<string, int>,
     *     card_class_patterns: array<string, int>,
     *     table_class_patterns: array<string, int>,
     *     route_count_admin: int,
     *     fail: int,
     *     admin_blade_files: list<string>,
     *     admin_layout_files: list<string>,
     *     admin_css_files: list<string>,
     *     inline_style_files: array<string, int>,
     *     page_style_push_files: list<string>,
     * }
     */
    public function snapshot(): array
    {
        $fail = 0;

        $adminBladeFiles = $this->collectAdminBladeFiles();
        if ($adminBladeFiles === []) {
            $fail++;
        }

        $adminLayoutFiles = $this->collectExistingPaths(self::ADMIN_LAYOUT_PATHS);
        if ($adminLayoutFiles === []) {
            $fail++;
        }

        $adminCssFiles = $this->collectAdminCssFiles();
        if ($adminCssFiles === []) {
            $fail++;
        }

        $inlineStyleFiles = [];
        $inlineStyleTotal = 0;
        foreach ($adminBladeFiles as $relativePath) {
            $absolute = base_path($relativePath);
            if (! is_readable($absolute)) {
                continue;
            }
            $contents = (string) file_get_contents($absolute);
            $count = preg_match_all('/\bstyle\s*=\s*"/i', $contents) ?: 0;
            if ($count > 0) {
                $inlineStyleFiles[$relativePath] = $count;
                $inlineStyleTotal += $count;
            }
        }

        $layoutPath = base_path('resources/views/layouts/dashboard.blade.php');
        if (is_readable($layoutPath)) {
            $layoutContents = (string) file_get_contents($layoutPath);
            $layoutInline = preg_match_all('/\bstyle\s*=\s*"/i', $layoutContents) ?: 0;
            if ($layoutInline > 0) {
                $inlineStyleFiles['resources/views/layouts/dashboard.blade.php'] = $layoutInline;
                $inlineStyleTotal += $layoutInline;
            }
        }

        $pageStylePushFiles = [];
        foreach ($adminBladeFiles as $relativePath) {
            $absolute = base_path($relativePath);
            if (! is_readable($absolute)) {
                continue;
            }
            $contents = (string) file_get_contents($absolute);
            if (str_contains($contents, "@push('styles')") || str_contains($contents, '@push("styles")')) {
                $pageStylePushFiles[] = $relativePath;
            }
        }

        $bladeContents = $this->concatReadableFiles($adminBladeFiles);

        return [
            'admin_layout_files_count' => count($adminLayoutFiles),
            'admin_blade_files_count' => count($adminBladeFiles),
            'admin_css_files_count' => count($adminCssFiles),
            'inline_style_occurrences' => $inlineStyleTotal,
            'page_style_push_count' => count($pageStylePushFiles),
            'button_class_patterns' => $this->topPatterns($bladeContents, [
                'btn btn-outline-' => '/\bbtn\s+btn-outline-/i',
                'btn btn-sm' => '/\bbtn\s+btn-sm\b/i',
                'btn btn-primary' => '/\bbtn\s+btn-primary\b/i',
                'btn btn-danger' => '/\bbtn\s+btn-danger\b/i',
                'btn btn-secondary' => '/\bbtn\s+btn-secondary\b/i',
            ]),
            'card_class_patterns' => $this->topPatterns($bladeContents, [
                'card' => '/\bclass="[^"]*\bcard\b/i',
                'card-header' => '/\bcard-header\b/i',
                'card-body' => '/\bcard-body\b/i',
                'ota-dash-panel' => '/\bota-dash-panel\b/i',
                'ota-kpi-card' => '/\bota-kpi-card\b/i',
            ]),
            'table_class_patterns' => $this->topPatterns($bladeContents, [
                'table table-vcenter card-table' => '/\btable\s+table-vcenter\s+card-table\b/i',
                'table table-sm' => '/\btable\s+table-sm\b/i',
                'table-responsive' => '/\btable-responsive\b/i',
                'ota-admin-table' => '/\bota-admin-table\b/i',
                'ota-r-table-wrap' => '/\bota-r-table-wrap\b/i',
            ]),
            'route_count_admin' => $this->countAdminRoutes(),
            'fail' => $fail,
            'admin_blade_files' => $adminBladeFiles,
            'admin_layout_files' => $adminLayoutFiles,
            'admin_css_files' => $adminCssFiles,
            'inline_style_files' => $inlineStyleFiles,
            'page_style_push_files' => $pageStylePushFiles,
        ];
    }

    /**
     * @return list<string>
     */
    private function collectAdminBladeFiles(): array
    {
        $files = [];

        foreach (self::ADMIN_BLADE_ROOTS as $root) {
            $absoluteRoot = base_path($root);
            if (! is_dir($absoluteRoot)) {
                continue;
            }

            $finder = Finder::create()
                ->files()
                ->in($absoluteRoot)
                ->name('*.blade.php')
                ->sortByName();

            foreach ($finder as $file) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function collectExistingPaths(array $paths): array
    {
        $existing = [];
        foreach ($paths as $path) {
            if (is_readable(base_path($path))) {
                $existing[] = $path;
            }
        }

        return $existing;
    }

    /**
     * @return list<string>
     */
    private function collectAdminCssFiles(): array
    {
        $files = $this->collectExistingPaths(self::ADMIN_CSS_PATHS);

        foreach (self::ADMIN_CSS_GLOBS as $glob) {
            foreach (glob(base_path($glob)) ?: [] as $absolute) {
                if (! is_readable($absolute)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($absolute, strlen(base_path()) + 1));
                $files[] = $relative;
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * @param  list<string>  $relativePaths
     */
    private function concatReadableFiles(array $relativePaths): string
    {
        $buffer = '';
        foreach ($relativePaths as $relativePath) {
            $absolute = base_path($relativePath);
            if (is_readable($absolute)) {
                $buffer .= (string) file_get_contents($absolute);
            }
        }

        return $buffer;
    }

    /**
     * @param  array<string, string>  $patterns
     * @return array<string, int>
     */
    private function topPatterns(string $haystack, array $patterns): array
    {
        $counts = [];
        foreach ($patterns as $label => $pattern) {
            $counts[$label] = preg_match_all($pattern, $haystack) ?: 0;
        }

        arsort($counts);

        return $counts;
    }

    private function countAdminRoutes(): int
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = ltrim((string) $route->uri(), '/');
            if (str_starts_with($name, 'admin.') || str_starts_with($uri, 'admin')) {
                $count++;
            }
        }

        return $count;
    }
}
