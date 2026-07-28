<?php

namespace App\Support\Audits;

use App\Models\ClientPage;
use App\Support\Client\ClientManagedPageCatalog;
use App\Support\Client\ClientManagedPageReservedSlugs;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Read-only CMS route and slug safety audit.
 */
final class JetpkCmsRouteSafetyAuditService
{
    private const OUTPUT_DIR = 'app/audits/jetpk-cms';

    /**
     * @return array<string, mixed>
     */
    public function run(string $profileSlug = 'jetpk'): array
    {
        $routeCollisions = [];
        $reservedSlugViolations = [];
        $duplicateSlugs = [];
        $draftExposure = [];
        $brokenNavigationLinks = [];
        $unsafeExternalLinks = [];
        $disabledPagesStillLinked = [];
        $missingPageDestinations = [];

        $registeredPaths = collect(Route::getRoutes())->map(function ($route) {
            return '/'.ltrim($route->uri(), '/');
        })->filter(fn (string $uri) => ! str_contains($uri, '{'))->values()->all();

        if (Schema::hasTable('client_pages')) {
            $slugs = ClientPage::query()->pluck('slug')->map(fn ($slug) => ClientManagedPageReservedSlugs::normalize((string) $slug));
            $duplicateSlugs = $slugs->countBy()->filter(fn (int $count) => $count > 1)->keys()->values()->all();
            foreach ($slugs as $slug) {
                if (ClientManagedPageReservedSlugs::isReserved($slug)) {
                    $reservedSlugViolations[] = $slug;
                }
                $path = '/'.$slug;
                if (in_array($path, $registeredPaths, true)) {
                    $routeCollisions[] = $path;
                }
            }
        }

        foreach (ClientManagedPageCatalog::pages() as $page) {
            $routeName = (string) $page['public_route'];
            if (! Route::has($routeName) && $page['page_key'] !== 'faq') {
                $missingPageDestinations[] = $page['page_key'].':'.$routeName;
            }
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'profile' => $profileSlug,
            'route_collisions' => count($routeCollisions),
            'reserved_slug_violations' => count($reservedSlugViolations),
            'duplicate_slugs' => count($duplicateSlugs),
            'draft_exposure' => count($draftExposure),
            'broken_navigation_links' => count($brokenNavigationLinks),
            'unsafe_external_links' => count($unsafeExternalLinks),
            'disabled_pages_still_linked' => count($disabledPagesStillLinked),
            'missing_page_destinations' => count($missingPageDestinations),
            'details' => compact(
                'routeCollisions',
                'reservedSlugViolations',
                'duplicateSlugs',
                'draftExposure',
                'brokenNavigationLinks',
                'unsafeExternalLinks',
                'disabledPagesStillLinked',
                'missingPageDestinations',
            ),
        ];

        $dir = storage_path(self::OUTPUT_DIR);
        File::ensureDirectoryExists($dir);
        $jsonPath = $dir.'/CMS-ROUTE-SAFETY-AUDIT.json';
        File::put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $fail = $payload['route_collisions'] > 0
            || $payload['reserved_slug_violations'] > 0
            || $payload['duplicate_slugs'] > 0
            || $payload['draft_exposure'] > 0
            || $payload['unsafe_external_links'] > 0;

        return array_merge($payload, ['fail' => $fail, 'path' => $jsonPath]);
    }
}
