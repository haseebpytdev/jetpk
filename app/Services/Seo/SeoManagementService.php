<?php

namespace App\Services\Seo;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPage;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Models\CmsPage;
use App\Services\Client\ClientGlobalContactResolver;
use App\Services\Client\ClientPageAdminContentResolver;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageSeoResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\PublicContent\PublicContentApiPresenter;
use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ReservedPublicPath;
use App\Support\Seo\SeoCanonicalValidator;
use App\Support\Seo\SeoManagedPageCatalog;
use App\Support\Seo\SeoRobotsHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Central SEO Management service — normalizes page SEO across managed, CMS, and custom sources.
 */
final class SeoManagementService
{
    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly ClientPageContentResolver $contentResolver,
        private readonly ClientPageAdminContentResolver $adminContentResolver,
        private readonly ClientPageSeoResolver $seoResolver,
        private readonly PublicContentApiPresenter $publicContent,
        private readonly ClientGlobalContactResolver $contactResolver,
        private readonly SeoCanonicalValidator $canonicalValidator,
        private readonly SeoVerificationResolver $verificationResolver,
        private readonly NextPublicCacheRevalidator $nextCache,
    ) {}

    /**
     * @return array<string, int>
     */
    public function overviewStats(): array
    {
        $pages = $this->listPages();

        return [
            'total' => count($pages),
            'indexable' => count(array_filter($pages, fn (array $p): bool => ($p['index'] ?? false) === true)),
            'noindex' => count(array_filter($pages, fn (array $p): bool => ($p['index'] ?? false) === false)),
            'missing_titles' => count(array_filter($pages, fn (array $p): bool => trim((string) ($p['title'] ?? '')) === '')),
            'missing_descriptions' => count(array_filter($pages, fn (array $p): bool => trim((string) ($p['description'] ?? '')) === '')),
            'missing_canonical' => count(array_filter($pages, fn (array $p): bool => trim((string) ($p['canonical'] ?? '')) === '')),
            'missing_og_image' => count(array_filter($pages, fn (array $p): bool => trim((string) ($p['og_image'] ?? '')) === '')),
            'sitemap_count' => count(array_filter($pages, fn (array $p): bool => ($p['sitemap_included'] ?? false) === true)),
            'attention' => count(array_filter($pages, fn (array $p): bool => in_array($p['health_status'] ?? '', ['warning', 'error'], true))),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPages(): array
    {
        $pages = [];

        foreach (SeoManagedPageCatalog::MANAGED_PAGES as $pageKey => $meta) {
            $pages[] = $this->normalizeManagedPage($pageKey, $meta['label'], $meta['path']);
        }

        if (Schema::hasTable('cms_pages')) {
            CmsPage::query()
                ->whereIn('status', [CmsPage::STATUS_ACTIVE, CmsPage::STATUS_DRAFT])
                ->orderBy('title')
                ->get()
                ->each(function (CmsPage $cmsPage) use (&$pages): void {
                    $pages[] = $this->normalizeCmsPage($cmsPage);
                });
        }

        if (Schema::hasTable('client_pages')) {
            $profile = $this->requireProfile();
            ClientPage::query()
                ->where('client_profile_id', $profile->id)
                ->where('enabled', true)
                ->orderBy('slug')
                ->get()
                ->each(function (ClientPage $clientPage) use (&$pages, $profile): void {
                    $slug = ClientManagedPageReservedSlugs::normalize((string) $clientPage->slug);
                    if ($slug === '' || ReservedPublicPath::isReservedFirstSegment($slug)) {
                        return;
                    }

                    $pageKey = ClientPageKeys::customKey($slug);
                    if ($this->contentResolver->contentFor($pageKey) === [] && empty($clientPage->seo_json)) {
                        return;
                    }

                    $pages[] = $this->normalizeCustomPage($clientPage, $pageKey);
                });
        }

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPage(string $sourceType, string $sourceId): array
    {
        return match ($sourceType) {
            SeoManagedPageCatalog::SOURCE_MANAGED => $this->getManagedPageEditor($sourceId),
            SeoManagedPageCatalog::SOURCE_CMS => $this->getCmsPageEditor((int) $sourceId),
            SeoManagedPageCatalog::SOURCE_CUSTOM => $this->getCustomPageEditor((int) $sourceId),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function saveManagedDraft(string $pageKey, array $payload, ?int $userId = null): ClientPageSetting
    {
        abort_unless(SeoManagedPageCatalog::isManagedPageKey($pageKey), 404);
        $this->assertCanonicalValid($payload['canonical'] ?? null);

        $profile = $this->requireProfile();
        $content = $this->adminContentResolver->formContentFor($profile, $pageKey);
        $content['seo'] = $this->mergeSeoPayload(
            is_array($content['seo'] ?? null) ? $content['seo'] : [],
            $payload,
        );

        return $this->contentResolver->saveDraft($profile, $pageKey, $content, $userId);
    }

    public function publishManaged(string $pageKey, ?int $userId = null): ?ClientPageSetting
    {
        abort_unless(SeoManagedPageCatalog::isManagedPageKey($pageKey), 404);

        $published = $this->contentResolver->publish($this->requireProfile(), $pageKey, $userId);
        $this->nextCache->revalidatePublishedPageSettings($pageKey);

        return $published;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function saveGlobalDraft(array $payload, ?int $userId = null): ClientPageSetting
    {
        $this->assertCanonicalDomainValid($payload['canonical_domain'] ?? null);

        $profile = $this->requireProfile();
        $content = $this->adminContentResolver->formContentFor($profile, ClientPageKeys::GLOBAL);
        $content['seo'] = $this->mergeGlobalSeoPayload(
            is_array($content['seo'] ?? null) ? $content['seo'] : [],
            $payload,
        );

        if (array_key_exists('verification_google', $payload) || array_key_exists('verification_bing', $payload)) {
            $verification = is_array($content['verification'] ?? null) ? $content['verification'] : [];
            if (array_key_exists('verification_google', $payload)) {
                $verification['google'] = trim((string) $payload['verification_google']);
            }
            if (array_key_exists('verification_bing', $payload)) {
                $verification['bing'] = trim((string) $payload['verification_bing']);
            }
            $content['verification'] = $verification;
        }

        return $this->contentResolver->saveDraft($profile, ClientPageKeys::GLOBAL, $content, $userId);
    }

    public function publishGlobal(?int $userId = null): ?ClientPageSetting
    {
        $published = $this->contentResolver->publish($this->requireProfile(), ClientPageKeys::GLOBAL, $userId);
        $this->nextCache->revalidateGlobal();

        return $published;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function updateCmsSeo(CmsPage $cmsPage, array $payload, ?int $userId = null): CmsPage
    {
        $this->assertCanonicalValid($payload['canonical'] ?? null);

        $cmsPage->update([
            'seo_title' => trim((string) ($payload['title'] ?? '')),
            'seo_description' => trim((string) ($payload['description'] ?? '')),
            'canonical_url' => trim((string) ($payload['canonical'] ?? '')),
            'robots' => filter_var($payload['index'] ?? true, FILTER_VALIDATE_BOOL)
                ? CmsPage::ROBOTS_INDEX
                : CmsPage::ROBOTS_NOINDEX,
            'updated_by' => $userId,
        ]);

        $fresh = $cmsPage->fresh() ?? $cmsPage;
        $this->nextCache->revalidateCmsPage((string) $fresh->slug);

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function saveCustomDraft(ClientPage $clientPage, array $payload, ?int $userId = null): ClientPageSetting
    {
        $this->assertCanonicalValid($payload['canonical'] ?? null);

        $profile = $this->requireProfile();
        abort_unless((int) $clientPage->client_profile_id === (int) $profile->id, 403);

        $pageKey = $clientPage->pageKey();
        $content = $this->adminContentResolver->formContentFor($profile, $pageKey);
        $content['seo'] = $this->mergeSeoPayload(
            is_array($content['seo'] ?? null) ? $content['seo'] : [],
            $payload,
        );

        return $this->contentResolver->saveDraft($profile, $pageKey, $content, $userId);
    }

    public function publishCustom(ClientPage $clientPage, ?int $userId = null): ?ClientPageSetting
    {
        $profile = $this->requireProfile();
        abort_unless((int) $clientPage->client_profile_id === (int) $profile->id, 403);

        $published = $this->contentResolver->publish($profile, $clientPage->pageKey(), $userId);
        $this->nextCache->revalidateCustomPage((string) $clientPage->slug);

        return $published;
    }

    /**
     * @return array<string, mixed>
     */
    public function globalSettings(): array
    {
        $profile = $this->requireProfile();
        $form = $this->adminContentResolver->formContentFor($profile, ClientPageKeys::GLOBAL);
        $published = $this->adminContentResolver->effectivePublicContent($profile, ClientPageKeys::GLOBAL);
        $seo = is_array($form['seo'] ?? null) ? $form['seo'] : [];
        $publishedSeo = is_array($published['seo'] ?? null) ? $published['seo'] : [];
        $meta = $this->adminContentResolver->editorMeta($profile, ClientPageKeys::GLOBAL);

        return [
            'brand_name' => trim((string) ($seo['brand_name'] ?? config('ota-brand.name', 'JetPakistan'))),
            'title' => trim((string) ($seo['title'] ?? '')),
            'description' => trim((string) ($seo['description'] ?? '')),
            'title_suffix' => trim((string) ($seo['title_suffix'] ?? '')),
            'canonical_domain' => trim((string) ($seo['canonical_domain'] ?? $this->canonicalValidator->canonicalBaseUrl())),
            'robots' => trim((string) ($seo['robots'] ?? 'index,follow')),
            'og_title' => trim((string) ($seo['og_title'] ?? '')),
            'og_description' => trim((string) ($seo['og_description'] ?? '')),
            'og_image' => trim((string) ($seo['og_image'] ?? '')),
            'published' => $meta['published'],
            'has_draft' => $meta['draft'],
            'published_seo' => $publishedSeo,
            'verification' => $this->verificationResolver->adminView(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function socialSettings(): array
    {
        $global = $this->globalSettings();

        return [
            'global' => [
                'og_title' => $global['og_title'],
                'og_description' => $global['og_description'],
                'og_image' => $global['og_image'],
            ],
            'pages' => collect($this->listPages())
                ->filter(fn (array $page): bool => trim((string) ($page['og_title'] ?? '')) !== ''
                    || trim((string) ($page['og_description'] ?? '')) !== ''
                    || trim((string) ($page['og_image'] ?? '')) !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schemaBusinessData(): array
    {
        $contact = $this->contactResolver->contact();
        $global = $this->globalSettings();

        return [
            'brand_name' => $global['brand_name'] ?: (string) config('ota-brand.name', 'JetPakistan'),
            'legal_name' => $contact['company_legal_name'],
            'phone' => $contact['phone'],
            'email' => $contact['email'],
            'office' => $contact['office'],
            'hours' => $contact['hours'],
            'website' => $contact['website'] ?: $this->canonicalValidator->canonicalBaseUrl(),
            'schema_types' => ['TravelAgency', 'WebSite'],
            'source' => 'client_global_contact',
            'note' => 'Business data is sourced from Global public settings contact fields. Edit contact details in Page settings → Global.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sitemapEntries(): array
    {
        $sitemapPaths = collect($this->publicContent->sitemapRoutes())->keyBy('path');
        $entries = [];

        foreach ($this->listPages() as $page) {
            if (($page['sitemap_eligible'] ?? false) !== true) {
                continue;
            }

            $path = (string) ($page['path'] ?? '');
            $included = ($page['sitemap_included'] ?? false) === true && $sitemapPaths->has($path);
            $entries[] = [
                'label' => $page['label'],
                'path' => $path,
                'public_url' => $page['public_url'],
                'source_type' => $page['source_type'],
                'canonical' => $page['canonical'],
                'indexable' => $page['index'],
                'included' => $included,
                'lastmod' => $sitemapPaths->get($path)['lastmod'] ?? null,
                'excluded_reason' => $included ? null : ($page['index'] ? 'Not in live sitemap feed' : 'Noindex page'),
            ];
        }

        foreach (SeoManagedPageCatalog::PROTECTED_ROUTES as $route) {
            $entries[] = [
                'label' => $route['label'],
                'path' => $route['path'],
                'public_url' => $this->canonicalValidator->canonicalBaseUrl().$route['path'],
                'source_type' => SeoManagedPageCatalog::SOURCE_PROTECTED,
                'canonical' => '',
                'indexable' => false,
                'included' => false,
                'lastmod' => null,
                'excluded_reason' => $route['reason'],
            ];
        }

        return $entries;
    }

    public function sitemapMeta(): array
    {
        $routes = $this->publicContent->sitemapRoutes();

        return [
            'sitemap_url' => $this->canonicalValidator->canonicalBaseUrl().'/sitemap.xml',
            'route_count' => count($routes),
            'api_route' => route('api.public.content.sitemap-routes'),
            'source' => 'laravel',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeManagedPage(string $pageKey, string $label, string $path): array
    {
        $profile = $this->clientContext->get();
        $meta = $profile !== null
            ? $this->adminContentResolver->editorMeta($profile, $pageKey)
            : ['draft' => false, 'published' => false, 'updated_at' => null];

        $fallbackTitle = $this->managedFallbackTitle($pageKey);
        $fallbackDescription = $this->managedFallbackDescription($pageKey);
        $resolved = $this->seoResolver->forPage($pageKey, $fallbackTitle, $fallbackDescription, null);
        $robots = SeoRobotsHelper::parse($resolved['robots'] ?? 'index,follow');
        $canonical = $this->canonicalValidator->normalize($resolved['canonical'] ?: null, $path);
        $draftSeo = $profile !== null ? $this->draftSeoSection($profile, $pageKey) : [];
        $explicitSeo = $profile !== null ? $this->publishedSeoSection($profile, $pageKey) : [];

        return $this->buildNormalizedRow([
            'source_type' => SeoManagedPageCatalog::SOURCE_MANAGED,
            'source_id' => $pageKey,
            'page_key' => $pageKey,
            'label' => $label,
            'path' => $path,
            'public_url' => $this->canonicalValidator->canonicalBaseUrl().$path,
            'title' => $resolved['title'],
            'description' => $resolved['description'],
            'canonical' => $canonical,
            'robots' => $robots['raw'],
            'index' => $robots['index'],
            'follow' => $robots['follow'],
            'og_title' => $resolved['og_title'] ?? $resolved['title'],
            'og_description' => $resolved['og_description'] ?? $resolved['description'],
            'og_image' => $resolved['og_image'],
            'sitemap_eligible' => true,
            'sitemap_included' => $robots['index'],
            'published' => (bool) ($meta['published'] ?? false),
            'has_draft' => (bool) ($meta['draft'] ?? false),
            'published_at' => null,
            'updated_at' => $meta['updated_at'] ?? null,
            'uses_fallback' => $explicitSeo === [],
            'has_unpublished_draft' => ($meta['draft'] ?? false) && ! empty($draftSeo),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCmsPage(CmsPage $cmsPage): array
    {
        $path = '/pages/'.$cmsPage->slug;
        $presented = $this->publicContent->cmsPage($cmsPage);
        $seo = is_array($presented['seo'] ?? null) ? $presented['seo'] : [];
        $robots = SeoRobotsHelper::parse(($cmsPage->robots === CmsPage::ROBOTS_NOINDEX ? 'noindex' : 'index').',follow');
        $canonical = $this->canonicalValidator->normalize($seo['canonical'] ?? null, $path);
        $indexable = $cmsPage->isActive() && $robots['index'];

        return $this->buildNormalizedRow([
            'source_type' => SeoManagedPageCatalog::SOURCE_CMS,
            'source_id' => (string) $cmsPage->id,
            'page_key' => 'cms:'.$cmsPage->slug,
            'label' => $cmsPage->title,
            'path' => $path,
            'public_url' => $canonical !== '' ? $canonical : $this->canonicalValidator->canonicalBaseUrl().$path,
            'title' => (string) ($seo['title'] ?? $cmsPage->title),
            'description' => (string) ($seo['description'] ?? ''),
            'canonical' => $canonical,
            'robots' => $robots['raw'],
            'index' => $robots['index'],
            'follow' => $robots['follow'],
            'og_title' => (string) ($seo['title'] ?? $cmsPage->title),
            'og_description' => (string) ($seo['description'] ?? ''),
            'og_image' => null,
            'sitemap_eligible' => $cmsPage->isActive(),
            'sitemap_included' => $indexable,
            'published' => $cmsPage->isActive(),
            'has_draft' => $cmsPage->status === CmsPage::STATUS_DRAFT,
            'published_at' => $cmsPage->published_at?->toIso8601String(),
            'updated_at' => $cmsPage->updated_at?->toIso8601String(),
            'uses_fallback' => trim((string) $cmsPage->seo_title) === '' || trim((string) $cmsPage->seo_description) === '',
            'has_unpublished_draft' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCustomPage(ClientPage $clientPage, string $pageKey): array
    {
        $path = '/'.ClientManagedPageReservedSlugs::normalize((string) $clientPage->slug);
        $profile = $this->requireProfile();
        $meta = $this->adminContentResolver->editorMeta($profile, $pageKey);
        $resolved = $this->seoResolver->forPage($pageKey, (string) ($clientPage->public_title ?? ''), '');
        $robots = SeoRobotsHelper::parse($resolved['robots'] ?? 'index,follow');
        $canonical = $this->canonicalValidator->normalize($resolved['canonical'] ?: null, $path);

        return $this->buildNormalizedRow([
            'source_type' => SeoManagedPageCatalog::SOURCE_CUSTOM,
            'source_id' => (string) $clientPage->id,
            'page_key' => $pageKey,
            'label' => (string) ($clientPage->public_title ?: $clientPage->internal_name),
            'path' => $path,
            'public_url' => $this->canonicalValidator->canonicalBaseUrl().$path,
            'title' => $resolved['title'],
            'description' => $resolved['description'],
            'canonical' => $canonical,
            'robots' => $robots['raw'],
            'index' => $robots['index'],
            'follow' => $robots['follow'],
            'og_title' => $resolved['og_title'] ?? $resolved['title'],
            'og_description' => $resolved['og_description'] ?? $resolved['description'],
            'og_image' => $resolved['og_image'],
            'sitemap_eligible' => true,
            'sitemap_included' => $robots['index'],
            'published' => (bool) ($meta['published'] ?? false),
            'has_draft' => (bool) ($meta['draft'] ?? false),
            'published_at' => null,
            'updated_at' => $meta['updated_at'] ?? $clientPage->updated_at?->toIso8601String(),
            'uses_fallback' => $this->publishedSeoSection($profile, $pageKey) === [] && empty($clientPage->seo_json),
            'has_unpublished_draft' => (bool) ($meta['draft'] ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildNormalizedRow(array $row): array
    {
        $warnings = app(SeoAuditService::class)->warningsForPage($row);
        $row['warnings'] = $warnings;
        $row['health_status'] = app(SeoAuditService::class)->healthStatus($warnings);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function getManagedPageEditor(string $pageKey): array
    {
        abort_unless(SeoManagedPageCatalog::isManagedPageKey($pageKey), 404);
        $profile = $this->requireProfile();
        $path = SeoManagedPageCatalog::managedPath($pageKey) ?? '/';
        $form = $this->adminContentResolver->formContentFor($profile, $pageKey);
        $seo = is_array($form['seo'] ?? null) ? $form['seo'] : [];
        $meta = $this->adminContentResolver->editorMeta($profile, $pageKey);
        $robots = SeoRobotsHelper::parse($seo['robots'] ?? 'index,follow');
        $resolved = $this->seoResolver->forPage($pageKey, $this->managedFallbackTitle($pageKey), $this->managedFallbackDescription($pageKey));

        return [
            'source_type' => SeoManagedPageCatalog::SOURCE_MANAGED,
            'source_id' => $pageKey,
            'page_key' => $pageKey,
            'label' => SeoManagedPageCatalog::managedLabel($pageKey),
            'path' => $path,
            'public_url' => $this->canonicalValidator->canonicalBaseUrl().$path,
            'form' => [
                'title' => $seo['title'] ?? '',
                'description' => $seo['description'] ?? '',
                'canonical' => $seo['canonical'] ?? '',
                'index' => $robots['index'],
                'follow' => $robots['follow'],
                'og_title' => $seo['og_title'] ?? '',
                'og_description' => $seo['og_description'] ?? '',
                'og_image' => $seo['og_image'] ?? '',
                'sitemap_eligible' => ($seo['sitemap_eligible'] ?? '1') !== '0',
            ],
            'resolved' => $resolved,
            'meta' => $meta,
            'preview_path' => $path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsPageEditor(int $id): array
    {
        $cmsPage = CmsPage::query()->findOrFail($id);
        $path = '/pages/'.$cmsPage->slug;
        $robots = SeoRobotsHelper::parse(($cmsPage->robots === CmsPage::ROBOTS_NOINDEX ? 'noindex' : 'index').',follow');

        return [
            'source_type' => SeoManagedPageCatalog::SOURCE_CMS,
            'source_id' => (string) $cmsPage->id,
            'page_key' => 'cms:'.$cmsPage->slug,
            'label' => $cmsPage->title,
            'path' => $path,
            'public_url' => $this->canonicalValidator->normalize($cmsPage->canonical_url, $path),
            'form' => [
                'title' => $cmsPage->seo_title ?? '',
                'description' => $cmsPage->seo_description ?? '',
                'canonical' => $cmsPage->canonical_url ?? '',
                'index' => $robots['index'],
                'follow' => $robots['follow'],
                'og_title' => $cmsPage->seo_title ?? '',
                'og_description' => $cmsPage->seo_description ?? '',
                'og_image' => '',
                'sitemap_eligible' => $cmsPage->isActive(),
            ],
            'meta' => [
                'published' => $cmsPage->isActive(),
                'has_draft' => $cmsPage->status === CmsPage::STATUS_DRAFT,
                'status' => $cmsPage->status,
            ],
            'preview_path' => $path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCustomPageEditor(int $id): array
    {
        $profile = $this->requireProfile();
        $clientPage = ClientPage::query()->where('client_profile_id', $profile->id)->findOrFail($id);
        $pageKey = $clientPage->pageKey();
        $path = '/'.ClientManagedPageReservedSlugs::normalize((string) $clientPage->slug);
        $form = $this->adminContentResolver->formContentFor($profile, $pageKey);
        $seo = is_array($form['seo'] ?? null) ? $form['seo'] : [];
        if ($seo === [] && is_array($clientPage->seo_json)) {
            $seo = $clientPage->seo_json;
        }
        $meta = $this->adminContentResolver->editorMeta($profile, $pageKey);
        $robots = SeoRobotsHelper::parse($seo['robots'] ?? 'index,follow');
        $resolved = $this->seoResolver->forPage($pageKey, (string) ($clientPage->public_title ?? ''), '');

        return [
            'source_type' => SeoManagedPageCatalog::SOURCE_CUSTOM,
            'source_id' => (string) $clientPage->id,
            'page_key' => $pageKey,
            'label' => (string) ($clientPage->public_title ?: $clientPage->internal_name),
            'path' => $path,
            'public_url' => $this->canonicalValidator->canonicalBaseUrl().$path,
            'form' => [
                'title' => $seo['title'] ?? '',
                'description' => $seo['description'] ?? '',
                'canonical' => $seo['canonical'] ?? '',
                'index' => $robots['index'],
                'follow' => $robots['follow'],
                'og_title' => $seo['og_title'] ?? '',
                'og_description' => $seo['og_description'] ?? '',
                'og_image' => $seo['og_image'] ?? '',
                'sitemap_eligible' => ($seo['sitemap_eligible'] ?? '1') !== '0',
            ],
            'resolved' => $resolved,
            'meta' => $meta,
            'preview_path' => $path,
        ];
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeSeoPayload(array $existing, array $payload): array
    {
        $index = filter_var($payload['index'] ?? true, FILTER_VALIDATE_BOOL);
        $follow = filter_var($payload['follow'] ?? true, FILTER_VALIDATE_BOOL);

        return array_merge($existing, [
            'title' => trim((string) ($payload['title'] ?? $existing['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? $existing['description'] ?? '')),
            'canonical' => trim((string) ($payload['canonical'] ?? $existing['canonical'] ?? '')),
            'robots' => SeoRobotsHelper::compose($index, $follow),
            'og_title' => trim((string) ($payload['og_title'] ?? $existing['og_title'] ?? '')),
            'og_description' => trim((string) ($payload['og_description'] ?? $existing['og_description'] ?? '')),
            'og_image' => trim((string) ($payload['og_image'] ?? $existing['og_image'] ?? '')),
            'sitemap_eligible' => filter_var($payload['sitemap_eligible'] ?? true, FILTER_VALIDATE_BOOL) ? '1' : '0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeGlobalSeoPayload(array $existing, array $payload): array
    {
        return array_merge($existing, array_filter([
            'brand_name' => trim((string) ($payload['brand_name'] ?? $existing['brand_name'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? $existing['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? $existing['description'] ?? '')),
            'title_suffix' => trim((string) ($payload['title_suffix'] ?? $existing['title_suffix'] ?? '')),
            'canonical_domain' => trim((string) ($payload['canonical_domain'] ?? $existing['canonical_domain'] ?? '')),
            'robots' => trim((string) ($payload['robots'] ?? $existing['robots'] ?? 'index,follow')),
            'og_title' => trim((string) ($payload['og_title'] ?? $existing['og_title'] ?? '')),
            'og_description' => trim((string) ($payload['og_description'] ?? $existing['og_description'] ?? '')),
            'og_image' => trim((string) ($payload['og_image'] ?? $existing['og_image'] ?? '')),
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedSeoSection(ClientProfile $profile, string $pageKey): array
    {
        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $seo = is_array($published?->content_json['seo'] ?? null) ? $published->content_json['seo'] : [];

        return array_filter($seo, fn (mixed $value): bool => trim((string) $value) !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function draftSeoSection(ClientProfile $profile, string $pageKey): array
    {
        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        return is_array($draft?->content_json['seo'] ?? null) ? $draft->content_json['seo'] : [];
    }

    private function managedFallbackTitle(string $pageKey): string
    {
        return match ($pageKey) {
            ClientPageKeys::HOME => 'JetPakistan | Affordable Flights, Umrah Packages & Tours',
            default => SeoManagedPageCatalog::managedLabel($pageKey).' | JetPakistan',
        };
    }

    private function managedFallbackDescription(string $pageKey): string
    {
        return match ($pageKey) {
            ClientPageKeys::HOME => 'Search and compare domestic and international flights from Pakistan, explore Umrah packages, and plan travel with JetPakistan.',
            default => 'Plan travel with JetPakistan — flights, Umrah packages, and support for travellers from Pakistan.',
        };
    }

    /**
     * @throws ValidationException
     */
    private function assertCanonicalValid(?string $canonical): void
    {
        $errors = $this->canonicalValidator->validationErrors($canonical);
        if ($errors !== []) {
            throw ValidationException::withMessages(['canonical' => $errors]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertCanonicalDomainValid(?string $domain): void
    {
        $domain = trim((string) $domain);
        if ($domain === '') {
            return;
        }

        if (! $this->canonicalValidator->isValid($domain) && ! $this->canonicalValidator->isValid(rtrim($domain, '/').'/')) {
            throw ValidationException::withMessages([
                'canonical_domain' => ['Canonical domain must use https://'.$this->canonicalValidator->canonicalHost()],
            ]);
        }
    }

    private function requireProfile(): ClientProfile
    {
        $profile = $this->clientContext->get();
        abort_if($profile === null, 404, 'Client profile not found.');

        return $profile;
    }
}
