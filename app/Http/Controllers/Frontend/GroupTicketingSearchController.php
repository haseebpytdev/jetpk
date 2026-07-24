<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\GroupTicketingSearchRequest;
use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventoryFacetService;
use App\Services\GroupTicketing\GroupInventoryFreshnessService;
use App\Services\GroupTicketing\GroupInventorySearchService;
use App\Services\Client\ClientPageRenderer;
use App\Support\Client\ClientPageKeys;
use App\Support\GroupTicketing\GroupInventoryCardPresenter;
use App\Support\GroupTicketing\GroupTicketingLivePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public group ticketing search, detail, and facet endpoints (separate from Sabre flight search).
 */
class GroupTicketingSearchController extends Controller
{
    public function __construct(
        protected GroupInventorySearchService $searchService,
        protected GroupInventoryFacetService $facetService,
        protected GroupInventoryCardPresenter $cardPresenter,
        protected GroupInventoryFreshnessService $freshnessService,
        protected ClientPageRenderer $pageRenderer,
    ) {}

    public function index(GroupTicketingSearchRequest $request): View
    {
        $inventoryFreshness = $this->freshnessService->ensureFreshForSearch();

        return $this->renderSearch($request, $inventoryFreshness, 1);
    }

    public function results(GroupTicketingSearchRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $inventoryFreshness = null;

        if ($page <= 1) {
            $this->freshnessService->clearSessionProviderConfirmed();
            $inventoryFreshness = $this->freshnessService->ensureFreshForSearch();
        }

        $bookable = $this->freshnessService->publicResultsAreBookable($inventoryFreshness, $page);
        $paginator = $bookable
            ? $this->searchService->searchPaginated($filters)
            : $this->emptyPaginator($filters);
        $results = $paginator->getCollection();
        $cards = $this->cardPresenter->presentMany($results, $bookable);

        $html = view('frontend.group-ticketing.partials.result-rows', [
            'results' => $results,
            'cards' => $cards,
        ])->render();

        $total = $paginator->total();
        $shown = min($paginator->currentPage() * $paginator->perPage(), $total);

        $payload = [
            'html' => $html,
            'total' => $total,
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
            'bookable' => $bookable,
            'count_label' => $this->countLabel($total, $shown, $bookable),
        ];

        $notice = is_array($inventoryFreshness) ? ($inventoryFreshness['user_notice'] ?? null) : null;
        if (! $bookable) {
            $notice = GroupTicketingLivePolicy::PUBLIC_SEARCH_UNAVAILABLE_MESSAGE;
        }

        if ($notice !== null && $notice !== '') {
            $payload['user_notice'] = $notice;
        }

        return response()->json($payload);
    }

    public function show(string $inventory): View
    {
        $item = $this->searchService->findByPublicId($inventory)
            ?? GroupInventory::query()->whereKey((int) $inventory)->first();

        if ($item === null || ! $item->is_active) {
            abort(404);
        }

        return view('frontend.group-ticketing.show', [
            'inventory' => $item,
            'card' => $this->cardPresenter->present($item, GroupTicketingLivePolicy::publicResultsMustBeProviderConfirmed() ? false : true),
        ]);
    }

    public function facets(): JsonResponse
    {
        return response()->json($this->facetService->all());
    }

    /**
     * @param  array<string, mixed>|null  $inventoryFreshness
     */
    private function renderSearch(GroupTicketingSearchRequest $request, ?array $inventoryFreshness, int $page): View
    {
        $filters = $request->filters();
        $facets = $this->facetService->all();
        $bookable = $this->freshnessService->publicResultsAreBookable($inventoryFreshness, $page);
        $paginator = $bookable
            ? $this->searchService->searchPaginated($filters)
            : $this->emptyPaginator($filters);
        $results = $paginator->getCollection();
        $cards = $this->cardPresenter->presentMany($results, $bookable);

        $total = $paginator->total();
        $shown = min($paginator->currentPage() * $paginator->perPage(), $total);
        $statusMessage = null;

        if (! $bookable) {
            $statusMessage = GroupTicketingLivePolicy::PUBLIC_SEARCH_UNAVAILABLE_MESSAGE;
        } elseif ($results->isEmpty()) {
            $statusMessage = $facets['sectors'] === [] && $facets['airlines'] === []
                ? 'Group ticketing inventory is not available yet. Please check back soon.'
                : 'No group tickets matched your search.';
        }

        return view(client_view('frontend.group-ticketing.search', 'frontend'), [
            'results' => $results,
            'cards' => $cards,
            'paginator' => $paginator,
            'filters' => $filters,
            'facets' => $facets,
            'sort' => $filters['sort'] ?? 'departure',
            'statusMessage' => $statusMessage,
            'inventoryFreshness' => $inventoryFreshness,
            'resultsBookable' => $bookable,
            'countLabel' => $this->countLabel($total, $shown, $bookable),
            'groupPageContent' => $this->pageRenderer->viewModel(ClientPageKeys::GROUP_SEARCH)['content'] ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function emptyPaginator(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? GroupInventorySearchService::DEFAULT_PER_PAGE)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return new LengthAwarePaginator(
            Collection::make(),
            0,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function countLabel(int $total, int $shown, bool $bookable): string
    {
        if (! $bookable) {
            return 'Live group availability is temporarily unavailable';
        }

        if ($total === 0) {
            return 'No group departures found';
        }

        return "Showing {$shown} of {$total} group departure".($total === 1 ? '' : 's');
    }
}
