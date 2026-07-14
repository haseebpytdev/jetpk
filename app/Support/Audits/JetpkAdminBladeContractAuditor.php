<?php

namespace App\Support\Audits;

/**
 * Static Blade contract checks for JetPK admin deep-page UI closure.
 */
final class JetpkAdminBladeContractAuditor
{
    /** @var list<string> */
    private const LEGACY_MARKERS = [
        'class="card"',
        'class="card ',
        'btn btn-primary',
        'btn btn-outline',
        'form-control',
        'form-select',
        'table table-',
        'class="page-title"',
        'row g-2 align-items',
        'page-header d-print-none',
    ];

    /** @var list<string> */
    private const JP_MARKERS = [
        'jp-card',
        'jp-btn',
        'jp-control',
        'jp-table',
        'jp-between',
        'jp-empty-state',
        'jp-page-title',
        'jp-label',
        'jp-alert',
    ];

    /**
     * @return array{pass: bool, legacy_markers: list<string>, jp_markers: list<string>, defects: list<string>}
     */
    public function auditView(string $viewName): array
    {
        $path = $this->viewPath($viewName);
        if ($path === null || ! is_file($path)) {
            return [
                'pass' => false,
                'legacy_markers' => [],
                'jp_markers' => [],
                'defects' => ['view file missing: '.$viewName],
            ];
        }

        $content = (string) file_get_contents($path);
        $legacyFound = [];
        foreach (self::LEGACY_MARKERS as $marker) {
            if (str_contains($content, $marker)) {
                $legacyFound[] = $marker;
            }
        }

        $jpFound = [];
        foreach (self::JP_MARKERS as $marker) {
            if (str_contains($content, $marker)) {
                $jpFound[] = $marker;
            }
        }

        $defects = [];
        if ($legacyFound !== [] && count($jpFound) < 2) {
            $defects[] = 'legacy bootstrap markup without sufficient jp-* classes';
        }

        return [
            'pass' => $defects === [],
            'legacy_markers' => $legacyFound,
            'jp_markers' => $jpFound,
            'defects' => $defects,
        ];
    }

    private function viewPath(string $viewName): ?string
    {
        $normalized = str_replace('.', '/', $viewName);
        $candidates = [
            resource_path('views/'.$normalized.'.blade.php'),
            resource_path('views/themes/admin/jetpakistan/'.$normalized.'.blade.php'),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
