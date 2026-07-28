<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardCmsPageResource;
use App\Http\Resources\Dashboard\DashboardCmsSectionResource;
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
