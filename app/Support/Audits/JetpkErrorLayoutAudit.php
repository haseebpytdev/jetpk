<?php

namespace App\Support\Audits;

use App\Support\Client\ClientErrorResponseResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * JetPK error page single-document checks (rendered HTML markers).
 */
final class JetpkErrorLayoutAudit
{
    /**
     * @return array{
     *     codes: array<string, array{renders: bool, themed: bool, issues: list<string>}>,
     *     error_404_renders: bool,
     *     error_500_renders: bool,
     *     single_header: bool,
     *     single_footer: bool,
     *     single_error_panel: bool,
     *     no_duplicate_brand_block: bool,
     *     shell_extends_frontend: bool,
     *     root_error_views_single_extends: bool,
     *     issues: list<string>,
     *     fail_count: int
     * }
     */
    public function run(): array
    {
        $issues = [];
        $failCount = 0;
        $codes = [];
        $resolver = app(ClientErrorResponseResolver::class);

        $shellPath = resource_path('views/themes/frontend/jetpakistan/errors/partials/shell.blade.php');
        $shellContent = File::exists($shellPath) ? (string) File::get($shellPath) : '';
        $shellExtendsFrontend = str_contains($shellContent, "@extends('themes.frontend.jetpakistan.layouts.frontend')");
        if ($shellExtendsFrontend) {
            $issues[] = 'Error shell must not extend frontend layout (duplicate chrome risk)';
            $failCount++;
        }

        $noDuplicateBrandBlock = ! str_contains($shellContent, 'jp-error-brand');
        if (! $noDuplicateBrandBlock) {
            $issues[] = 'Error shell still contains duplicate jp-error-brand block';
            $failCount++;
        }

        $rootErrorViewsSingleExtends = true;
        foreach (ClientErrorResponseResolver::SUPPORTED_CODES as $code) {
            $path = resource_path('views/errors/'.$code.'.blade.php');
            $source = File::exists($path) ? (string) File::get($path) : '';
            if ($source === '') {
                $issues[] = 'Missing generic errors/'.$code.'.blade.php';
                $failCount++;
                $rootErrorViewsSingleExtends = false;

                continue;
            }

            if (preg_match('/@extends\s*\(\s*\$/', $source) === 1 || str_contains($source, '@if ($clientErrorView')) {
                $issues[] = 'errors/'.$code.'.blade.php still uses conditional @extends dispatch';
                $failCount++;
                $rootErrorViewsSingleExtends = false;
            }

            if (substr_count($source, '@extends') !== 1) {
                $issues[] = 'errors/'.$code.'.blade.php must contain exactly one @extends directive';
                $failCount++;
                $rootErrorViewsSingleExtends = false;
            }
        }

        foreach (ClientErrorResponseResolver::SUPPORTED_CODES as $code) {
            $view = $resolver->resolveView($code);
            $themed = str_starts_with($view, 'themes.frontend.jetpakistan.errors.');
            $codeIssues = [];
            $renders = false;

            if (! View::exists($view)) {
                $codeIssues[] = 'Missing resolved view '.$view;
                $failCount++;
            } else {
                try {
                    $html = view($view, $code === '403' ? ['message' => 'Audit access restricted.'] : [])->render();
                    $renders = $html !== '';
                    $codeIssues = $themed
                        ? ClientErrorResponseResolver::themedDocumentIssues($html)
                        : ClientErrorResponseResolver::genericDocumentIssues($html);
                    if ($codeIssues !== []) {
                        $failCount += count($codeIssues);
                    }
                } catch (\Throwable $e) {
                    $codeIssues[] = 'Render failed: '.$e->getMessage();
                    $failCount++;
                }
            }

            foreach ($codeIssues as $codeIssue) {
                $issues[] = 'errors/'.$code.': '.$codeIssue;
            }

            $codes[$code] = [
                'renders' => $renders,
                'themed' => $themed,
                'issues' => $codeIssues,
            ];
        }

        $error404Renders = $codes['404']['renders'] ?? false;
        $error500Renders = $codes['500']['renders'] ?? false;

        $counts404 = ['header' => 0, 'footer' => 0, 'panel' => 0];
        if ($error404Renders && View::exists($resolver->resolveView('404'))) {
            $counts404 = ClientErrorResponseResolver::countDocumentMarkers(
                view($resolver->resolveView('404'))->render()
            );
        }

        return [
            'codes' => $codes,
            'error_404_renders' => $error404Renders,
            'error_500_renders' => $error500Renders,
            'single_header' => ($counts404['header'] ?? 0) === 1,
            'single_footer' => ($counts404['footer'] ?? 0) === 1,
            'single_error_panel' => ($counts404['panel'] ?? 0) === 1,
            'no_duplicate_brand_block' => $noDuplicateBrandBlock,
            'shell_extends_frontend' => $shellExtendsFrontend,
            'root_error_views_single_extends' => $rootErrorViewsSingleExtends,
            'issues' => $issues,
            'fail_count' => $failCount,
        ];
    }
}
