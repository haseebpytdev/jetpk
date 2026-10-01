<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardCmsPageResource;
use App\Http\Resources\Dashboard\DashboardCmsSectionResource;
use App\Models\Agency;
use App\Models\AgencyMedia;
use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardCmsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, facets: array<string, list<string>>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', CmsPage::class);

        $query = CmsPage::query()->withTrashed(false);
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(static fn (CmsPage $pageModel): array => DashboardCmsPageResource::fromModel($pageModel))
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pageCount' => $paginator->lastPage(),
            ],
            'filters' => $this->activeFilters($request),
            'facets' => [
                'statuses' => [CmsPage::STATUS_ACTIVE, CmsPage::STATUS_DRAFT, CmsPage::STATUS_ARCHIVED],
                'pageTypes' => ['support', 'privacy', 'terms', 'faq', 'contact', 'about'],
                'themeModes' => ['automatic'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        Gate::authorize('viewAny', CmsPage::class);
        $page = $this->resolvePage($id);
        if ($page === null) {
            return null;
        }
        Gate::authorize('view', $page);

        return DashboardCmsPageResource::fromModel($page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(User $user, string $id): array
    {
        Gate::authorize('viewAny', CmsPage::class);
        $page = $this->resolvePage($id);
        if ($page === null) {
            return [];
        }
        Gate::authorize('view', $page);

        return [DashboardCmsSectionResource::fromPage($page)];
    }

    /**
     * Live media library rows from AgencyMedia (CURRENT domain).
     *
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>}
     */
    public function paginateAssets(User $user, Request $request): array
    {
        Gate::authorize('viewAny', CmsPage::class);

        $agency = null;
        if ($user->current_agency_id) {
            $agency = Agency::query()->find($user->current_agency_id);
        }
        if ($agency === null && $user->isPlatformAdmin()) {
            $agency = Agency::query()->orderBy('id')->first();
        }

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));

        if ($agency === null) {
            return [
                'items' => [],
                'pagination' => [
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'total' => 0,
                    'pageCount' => 1,
                ],
                'filters' => [
                    'q' => (string) $request->query('q', ''),
                    'collection' => (string) $request->query('collection', ''),
                ],
            ];
        }

        Gate::authorize('viewAny', [AgencyMedia::class, $agency]);

        $query = AgencyMedia::query()
            ->with('uploader')
            ->where('agency_id', $agency->id);

        $search = trim((string) $request->query('q', $request->query('search', '')));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('file_name', 'like', $like)
                    ->orWhere('alt_text', 'like', $like)
                    ->orWhere('collection', 'like', $like);
            });
        }

        $collection = trim((string) $request->query('collection', ''));
        if ($collection !== '') {
            $query->where('collection', $collection);
        }

        $paginator = $query->latest('id')->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()->map(static function (AgencyMedia $media): array {
            $mime = (string) ($media->mime_type ?? 'image/jpeg');
            $fileType = match (true) {
                str_contains($mime, 'png') => 'image/png',
                str_contains($mime, 'webp') => 'image/webp',
                default => 'image/jpeg',
            };
            $alt = trim((string) ($media->alt_text ?? ''));
            $variant = [
                'width' => 0,
                'height' => 0,
                'aspectRatio' => '—',
                'placeholderLabel' => (string) $media->file_name,
            ];

            return [
                'id' => (string) $media->id,
                'internalName' => (string) $media->file_name,
                'category' => (string) ($media->collection ?: 'general'),
                'desktop' => $variant,
                'mobile' => $variant,
                'dayVariant' => null,
                'nightVariant' => null,
                'fileType' => $fileType,
                'altText' => $alt,
                'focalPointX' => 50,
                'focalPointY' => 50,
                'safeArea' => 'n/a',
                'approvalStatus' => 'approved',
                'usageCount' => 0,
                'createdDate' => $media->created_at?->toDateString() ?? '',
                'updatedDate' => $media->updated_at?->toDateString() ?? '',
                'authorId' => $media->uploader?->name ?? '—',
                'url' => $media->publicUrl() ?? '',
                'validation' => [
                    'valid' => $alt !== '',
                    'issues' => $alt === ''
                        ? [['code' => 'missing_alt', 'severity' => 'warning', 'message' => 'Alt text missing']]
                        : [],
                ],
            ];
        })->values()->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pageCount' => max(1, $paginator->lastPage()),
            ],
            'filters' => [
                'q' => $search,
                'collection' => $collection,
            ],
        ];
    }

    protected function resolvePage(string $id): ?CmsPage
    {
        if (preg_match('/^JP-CMS-PG-(\d+)$/i', $id, $matches) === 1) {
            return CmsPage::query()->whereKey((int) $matches[1])->first();
        }
        if (ctype_digit($id)) {
            return CmsPage::query()->whereKey((int) $id)->first();
        }

        return CmsPage::query()->where('slug', $id)->first();
    }

    /**
     * @param  Builder<CmsPage>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status !== '' && $status !== 'all') {
            $mapped = match ($status) {
                'published' => CmsPage::STATUS_ACTIVE,
                'draft' => CmsPage::STATUS_DRAFT,
                'archived' => CmsPage::STATUS_ARCHIVED,
                default => $status,
            };
            $query->where('status', $mapped);
        }
    }

    /**
     * @param  Builder<CmsPage>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'updatedAt');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'title' => $query->orderBy('title', $direction),
            'slug' => $query->orderBy('slug', $direction),
            'status' => $query->orderBy('status', $direction),
            default => $query->orderBy('updated_at', $direction),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(Request $request): array
    {
        return array_filter([
            'q' => $request->query('q', $request->query('search')),
            'status' => $request->query('status'),
            'pageType' => $request->query('pageType'),
            'theme' => $request->query('theme'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
