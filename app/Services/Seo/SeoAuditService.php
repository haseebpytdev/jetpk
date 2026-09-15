<?php

namespace App\Services\Seo;

use App\Support\Seo\SeoCanonicalValidator;
use App\Support\Seo\SeoManagedPageCatalog;
use App\Support\Seo\SeoRobotsHelper;

/**
 * Application SEO audit findings for SEO Management screens.
 */
final class SeoAuditService
{
    public function __construct(
        private readonly SeoCanonicalValidator $canonicalValidator,
    ) {}

    /**
     * @return list<array{severity: string, code: string, message: string, page_ref?: string}>
     */
    public function runAudit(): array
    {
        $service = app(SeoManagementService::class);
        $findings = [];

        $titles = [];
        $descriptions = [];

        foreach ($service->listPages() as $page) {
            foreach ($this->warningsForPage($page) as $warning) {
                $findings[] = array_merge($warning, [
                    'page_ref' => $this->pageRef($page),
                    'label' => $page['label'] ?? '',
                    'path' => $page['path'] ?? '',
                ]);
            }

            $title = strtolower(trim((string) ($page['title'] ?? '')));
            if ($title !== '') {
                $titles[$title][] = $page;
            }

            $description = strtolower(trim((string) ($page['description'] ?? '')));
            if ($description !== '') {
                $descriptions[$description][] = $page;
            }
        }

        foreach ($titles as $group) {
            if (count($group) > 1) {
                foreach ($group as $page) {
                    $findings[] = [
                        'severity' => 'warning',
                        'code' => 'duplicate_title',
                        'message' => 'Duplicate SEO title shared with other pages.',
                        'page_ref' => $this->pageRef($page),
                        'label' => $page['label'] ?? '',
                        'path' => $page['path'] ?? '',
                    ];
                }
            }
        }

        foreach ($descriptions as $group) {
            if (count($group) > 1) {
                foreach ($group as $page) {
                    $findings[] = [
                        'severity' => 'info',
                        'code' => 'duplicate_description',
                        'message' => 'Duplicate meta description shared with other pages.',
                        'page_ref' => $this->pageRef($page),
                        'label' => $page['label'] ?? '',
                        'path' => $page['path'] ?? '',
                    ];
                }
            }
        }

        foreach (SeoManagedPageCatalog::PROTECTED_ROUTES as $route) {
            if (($route['sitemap_eligible'] ?? false) === true) {
                continue;
            }

            $findings[] = [
                'severity' => 'good',
                'code' => 'protected_route_excluded',
                'message' => $route['path'].' is correctly excluded from sitemap.',
                'path' => $route['path'],
                'label' => $route['label'],
            ];
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return list<array{severity: string, code: string, message: string}>
     */
    public function warningsForPage(array $page): array
    {
        $warnings = [];
        $title = trim((string) ($page['title'] ?? ''));
        $description = trim((string) ($page['description'] ?? ''));
        $canonical = trim((string) ($page['canonical'] ?? ''));

        if ($title === '') {
            $warnings[] = ['severity' => 'error', 'code' => 'missing_title', 'message' => 'Missing SEO title.'];
        } elseif (strlen($title) < 20) {
            $warnings[] = ['severity' => 'warning', 'code' => 'title_too_short', 'message' => 'SEO title is shorter than 20 characters.'];
        } elseif (strlen($title) > 70) {
            $warnings[] = ['severity' => 'warning', 'code' => 'title_too_long', 'message' => 'SEO title exceeds 70 characters.'];
        }

        if ($description === '') {
            $warnings[] = ['severity' => 'error', 'code' => 'missing_description', 'message' => 'Missing meta description.'];
        } elseif (strlen($description) < 50) {
            $warnings[] = ['severity' => 'warning', 'code' => 'description_too_short', 'message' => 'Meta description is shorter than 50 characters.'];
        } elseif (strlen($description) > 160) {
            $warnings[] = ['severity' => 'warning', 'code' => 'description_too_long', 'message' => 'Meta description exceeds 160 characters.'];
        }

        if ($canonical === '') {
            $warnings[] = ['severity' => 'warning', 'code' => 'missing_canonical', 'message' => 'Missing canonical URL.'];
        } elseif (! $this->canonicalValidator->isValid($canonical)) {
            $warnings[] = ['severity' => 'error', 'code' => 'invalid_canonical', 'message' => 'Canonical URL must use HTTPS and '.$this->canonicalValidator->canonicalHost().'.'];
        }

        if (($page['uses_fallback'] ?? false) === true) {
            $warnings[] = ['severity' => 'info', 'code' => 'using_fallback', 'message' => 'Live SEO is using application fallback values.'];
        }

        if (($page['has_unpublished_draft'] ?? false) === true) {
            $warnings[] = ['severity' => 'info', 'code' => 'unpublished_draft', 'message' => 'Unpublished SEO draft exists.'];
        }

        if (($page['sitemap_included'] ?? false) === true && ($page['index'] ?? true) === false) {
            $warnings[] = ['severity' => 'error', 'code' => 'noindex_in_sitemap', 'message' => 'Noindex page must not appear in sitemap.'];
        }

        if (trim((string) ($page['og_image'] ?? '')) === '') {
            $warnings[] = ['severity' => 'info', 'code' => 'missing_og_image', 'message' => 'Missing Open Graph image.'];
        }

        if ($warnings === [] && $title !== '' && $description !== '') {
            $warnings[] = ['severity' => 'good', 'code' => 'healthy', 'message' => 'SEO basics look healthy.'];
        }

        return $warnings;
    }

    /**
     * @param  list<array{severity: string}>  $warnings
     */
    public function healthStatus(array $warnings): string
    {
        $severities = array_column($warnings, 'severity');
        if (in_array('error', $severities, true)) {
            return 'error';
        }
        if (in_array('warning', $severities, true)) {
            return 'warning';
        }
        if (in_array('info', $severities, true)) {
            return 'info';
        }

        return 'good';
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public function pageRef(array $page): string
    {
        return ((string) ($page['source_type'] ?? 'managed')).':'.((string) ($page['source_id'] ?? ''));
    }

    public function editUrl(array $finding): ?string
    {
        if (! isset($finding['page_ref'])) {
            return null;
        }

        [$sourceType, $sourceId] = array_pad(explode(':', (string) $finding['page_ref'], 2), 2, '');

        return route('admin.seo.pages.edit', [
            'sourceType' => $sourceType,
            'sourceId' => $sourceId,
        ]);
    }
}
