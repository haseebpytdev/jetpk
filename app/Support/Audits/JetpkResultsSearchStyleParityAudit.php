<?php

namespace App\Support\Audits;

use Illuminate\Support\Facades\File;

/**
 * Read-only audit: Results search shell must not declare visual overrides
 * outside documented layout/context exceptions.
 */
class JetpkResultsSearchStyleParityAudit
{
    /** @var list<string> */
    private const SCOPED_FILES = [
        'css/results.css',
        'css/results-base.css',
        'css/jp-search.css',
    ];

    /** @var list<string> */
    private const FORBIDDEN_PROPERTIES = [
        'height',
        'min-height',
        'padding',
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        'border',
        'border-radius',
        'background',
        'background-color',
        'box-shadow',
        'line-height',
        'color',
        'font-size',
        'align-items',
        'justify-content',
    ];

    /** @var list<string> */
    private const ALLOWED_PROPERTIES = [
        'margin',
        'margin-top',
        'margin-bottom',
        'margin-left',
        'margin-right',
        'overflow',
        'opacity',
        'transform',
        'animation',
        'display',
        'content',
        'backdrop-filter',
        '-webkit-backdrop-filter',
    ];

    /** @var list<string> */
    private const SCOPE_PATTERNS = [
        '/\.jp-flights-results\s+\.jp-results-search-placement/',
        '/\.jp-results-search-placement\s+\.search/',
    ];

    /** Canonical partial both Home and Results must include. */
    private const CANONICAL_PARTIAL = 'components.search.home-flights-search';

    /**
     * @return array{fail: int, violations: list<string>}
     */
    public function runCanonicalReuse(): array
    {
        $violations = [];
        $canonicalPath = resource_path('views/themes/frontend/jetpakistan/components/search/home-flights-search.blade.php');

        if (! File::isFile($canonicalPath)) {
            $violations[] = 'missing canonical partial: home-flights-search.blade.php';
        } else {
            $canonical = (string) File::get($canonicalPath);
            if (! str_contains($canonical, 'components.search.search-shell')) {
                $violations[] = 'home-flights-search.blade.php must include search-shell partial chain';
            }
            if (! str_contains($canonical, 'date-field') && ! str_contains($canonical, 'search-shell')) {
                $violations[] = 'home-flights-search must delegate to search-shell (date-field + passenger-selector chain)';
            }
        }

        $heroPath = resource_path('views/themes/frontend/jetpakistan/sections/hero.blade.php');
        $resultsPath = resource_path('views/frontend/flights/partials/results-page.blade.php');

        foreach ([['hero', $heroPath], ['results-page', $resultsPath]] as [$label, $path]) {
            if (! File::isFile($path)) {
                $violations[] = "missing view: {$label}";
                continue;
            }
            $contents = (string) File::get($path);
            if (! str_contains($contents, self::CANONICAL_PARTIAL)) {
                $violations[] = "{$label} must @include ".self::CANONICAL_PARTIAL;
            }
            if (preg_match('/@include\([^)]*components\.search\.search-shell/', $contents)) {
                $violations[] = "{$label} must not include search-shell directly; use ".self::CANONICAL_PARTIAL;
            }
        }

        $results = (string) File::get($resultsPath);
        if (str_contains($results, 'jp-results-search') && ! str_contains($results, 'jp-results-search-placement')) {
            $violations[] = 'results-page still references obsolete jp-results-search wrapper class';
        }
        if (preg_match('/@if\s*\(\s*current_client_slug\(\)\s*===\s*[\'"]jetpk[\'"]\s*\)(.*?)@else/s', $results, $jetpkBranch)) {
            if (str_contains($jetpkBranch[1], 'ota-results-widget-wide')) {
                $violations[] = 'JetPK results search must not use ota-results-widget-wide wrapper';
            }
            if (! str_contains($jetpkBranch[1], 'jp-results-search-placement')) {
                $violations[] = 'JetPK results search must use jp-results-search-placement wrapper';
            }
        }
        if (substr_count($results, 'home-flights-search') < 1) {
            $violations[] = 'results-page missing home-flights-search include';
        }
        if (preg_match('/@include\([^)]*ota-hero-flight-search[^)]*jetpk/s', $results)) {
            $violations[] = 'results-page must not include ota-hero-flight-search for JetPK';
        }

        $flightsPanel = (string) File::get(resource_path('views/themes/frontend/jetpakistan/components/search/flights-panel.blade.php'));
        if (! str_contains($flightsPanel, 'components.search.date-field')) {
            $violations[] = 'flights-panel must include date-field partial for departure';
        }
        if (! str_contains($flightsPanel, 'components.search.passenger-selector')) {
            $violations[] = 'flights-panel must include passenger-selector partial for travellers';
        }
        if (! str_contains($flightsPanel, 'btn-search')) {
            $violations[] = 'flights-panel must render btn-search submit control';
        }

        return [
            'fail' => count($violations),
            'violations' => $violations,
        ];
    }

    /**
     * @return array{fail: int, violations: list<string>, bootstrap_scoped: bool, canonical_fail: int}
     */
    public function run(?string $frontendRoot = null): array
    {
        $root = $frontendRoot ?? public_path('themes/frontend/jetpakistan');
        $violations = [];
        $fail = 0;

        foreach (self::SCOPED_FILES as $relative) {
            $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! File::isFile($path)) {
                $violations[] = "missing file: {$relative}";
                $fail++;

                continue;
            }

            $violations = array_merge($violations, $this->scanFile($relative, (string) File::get($path)));
        }

        $resultsCss = (string) File::get($root.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'results.css');
        $bootstrapScoped = str_contains($resultsCss, '.btn:not(.btn-search):not(.btn-ghost)')
            && str_contains($resultsCss, '.btn-primary:not(.btn-search)');

        if (! $bootstrapScoped) {
            $violations[] = 'results.css must scope Bootstrap .btn subset away from search-shell (.btn-search/.btn-ghost)';
            $fail++;
        }

        $canonical = $this->runCanonicalReuse();
        $violations = array_merge($violations, $canonical['violations']);
        $fail = count($violations);

        if (! $bootstrapScoped) {
            $fail++;
        }

        return [
            'fail' => $fail,
            'violations' => $violations,
            'bootstrap_scoped' => $bootstrapScoped,
            'canonical_fail' => (int) $canonical['fail'],
        ];
    }

    /**
     * @return list<string>
     */
    private function scanFile(string $relative, string $contents): array
    {
        $violations = [];
        $lines = preg_split('/\R/', $contents) ?: [];
        $inResultsScope = false;
        $braceDepth = 0;
        $currentSelector = '';

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*')) {
                continue;
            }

            if (str_contains($trimmed, '{')) {
                $selectorPart = trim((string) preg_replace('/\{.*$/', '', $trimmed));
                if ($selectorPart !== '') {
                    $currentSelector = $selectorPart;
                }
                if ($selectorPart !== '' && $this->selectorInResultsSearchScope($selectorPart)) {
                    $inResultsScope = true;
                    $braceDepth = substr_count($trimmed, '{') - substr_count($trimmed, '}');
                } elseif ($inResultsScope) {
                    $braceDepth += substr_count($trimmed, '{') - substr_count($trimmed, '}');
                }
            } elseif ($inResultsScope) {
                $braceDepth += substr_count($trimmed, '{') - substr_count($trimmed, '}');
            }

            if ($inResultsScope && str_contains($trimmed, '}')) {
                if ($braceDepth <= 0) {
                    $inResultsScope = false;
                    $braceDepth = 0;
                    $currentSelector = '';
                }
            }

            if (! $inResultsScope || ! str_contains($trimmed, ':')) {
                continue;
            }

            if (str_contains($trimmed, '@media') || str_starts_with($trimmed, '@')) {
                continue;
            }

            if ($this->isLegacySuppressSelector($currentSelector)) {
                continue;
            }

            foreach (self::FORBIDDEN_PROPERTIES as $property) {
                if (! preg_match('/^\s*'.preg_quote($property, '/').'\s*:/', $trimmed)) {
                    continue;
                }

                if (in_array($property, self::ALLOWED_PROPERTIES, true)) {
                    continue;
                }

                if ($this->isDocumentedException($relative, $trimmed)) {
                    continue;
                }

                $violations[] = "{$relative}:".($index + 1)." forbidden {$property} in results search scope — {$trimmed}";
            }
        }

        return $violations;
    }

    private function selectorInResultsSearchScope(string $selector): bool
    {
        foreach (self::SCOPE_PATTERNS as $pattern) {
            if (preg_match($pattern, $selector)) {
                return true;
            }
        }

        return false;
    }

    private function isLegacySuppressSelector(string $selector): bool
    {
        return str_contains($selector, 'ota-hero-search');
    }

    private function isDocumentedException(string $relative, string $declaration): bool
    {
        if ($relative === 'css/jp-search.css' && str_contains($declaration, 'margin')) {
            return true;
        }

        if ($relative === 'css/results.css' && (
            str_contains($declaration, 'margin')
            || str_contains($declaration, 'overflow')
            || str_contains($declaration, 'display: none')
            || str_contains($declaration, 'content: none')
        )) {
            return true;
        }

        return false;
    }
}
