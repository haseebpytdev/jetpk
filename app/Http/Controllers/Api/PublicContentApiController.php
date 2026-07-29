<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientPage;
use App\Models\CmsPage;
use App\Services\Client\ClientPageContentResolver;
use App\Services\PublicContent\PublicContentApiPresenter;
use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ClientPageKeys;
use App\Support\Security\TurnstileVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicContentApiController extends Controller
{
    public function __construct(
        private readonly PublicContentApiPresenter $presenter,
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    public function csrfToken(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }

    public function turnstileConfig(): JsonResponse
    {
        $enabled = TurnstileVerifier::isEnabled();
        $siteKey = $enabled ? trim((string) config('services.turnstile.site_key', '')) : '';

        return response()->json([
            'enabled' => $enabled,
            'site_key' => $enabled && $siteKey !== '' ? $siteKey : null,
            'response_field' => TurnstileVerifier::RESPONSE_FIELD,
        ]);
    }

    public function siteContact(): JsonResponse
    {
        return response()->json($this->presenter->siteContact());
    }

    public function supportCategories(): JsonResponse
    {
        return response()->json([
            'categories' => $this->presenter->supportCategories(),
        ]);
    }

    public function managedPage(string $pageKey): JsonResponse
    {
        if (! in_array($pageKey, $this->presenter->allowedManagedPageKeys(), true)) {
            abort(404);
        }

        return response()->json($this->presenter->managedPage($pageKey));
    }

    public function cmsPage(string $slug): JsonResponse
    {
        $page = CmsPage::query()->where('slug', $slug)->firstOrFail();

        if (! $page->isActive()) {
            abort(404);
        }

        return response()->json($this->presenter->cmsPage($page));
    }

    public function customPage(string $slug): JsonResponse
    {
        $slug = ClientManagedPageReservedSlugs::normalize($slug);

        if (ClientManagedPageReservedSlugs::isReserved($slug)) {
            abort(404);
        }

        $page = ClientPage::query()
            ->where('slug', $slug)
            ->where('enabled', true)
            ->firstOrFail();

        $pageKey = ClientPageKeys::customKey($slug);
        $published = $this->contentResolver->contentFor($pageKey);
        if ($published === []) {
            abort(404);
        }

        return response()->json($this->presenter->customClientPage($page, $pageKey));
    }

    public function publicConfig(): JsonResponse
    {
        return response()->json($this->presenter->publicConfig());
    }

    public function sitemapRoutes(): JsonResponse
    {
        return response()->json([
            'routes' => $this->presenter->sitemapRoutes(),
            'source' => 'laravel',
        ]);
    }
}
