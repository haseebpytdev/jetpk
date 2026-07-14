<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GroupHomepageTileTargetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchUpsertGroupHomepageTilesRequest;
use App\Http\Requests\Admin\StoreGroupCategoryRequest;
use App\Http\Requests\Admin\StoreGroupHomepageTileRequest;
use App\Http\Requests\Admin\UpdateGroupHomepageTileRequest;
use App\Http\Requests\Admin\UpsertGroupHomepageTileRequest;
use App\Models\GroupCategory;
use App\Models\GroupHomepageTile;
use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventoryFacetService;
use App\Services\GroupTicketing\GroupInventorySyncService;
use App\Support\GroupTicketing\GroupHomepageTilePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Admin setup for group ticketing: derived homepage tiles, inventory sync, read-only API categories.
 */
class AdminGroupTicketingController extends Controller
{
    public function index(GroupInventoryFacetService $facetService): View
    {
        Gate::authorize('platform.admin');

        $lastSync = $facetService->lastInventorySyncAt();

        return view(client_view('group-ticketing.index', 'admin'), [
            'activeInventoryCount' => $facetService->totalActiveInventoryCount(),
            'categoryCount' => count($facetService->categoriesForAdmin()),
            'lastSyncAt' => $lastSync,
        ]);
    }

    public function tilesIndex(GroupHomepageTilePresenter $presenter): View
    {
        Gate::authorize('platform.admin');

        return view(client_view('group-ticketing.tiles.index', 'admin'), [
            'tiles' => $presenter->presentForAdmin(),
        ]);
    }

    public function tilesUpsert(UpsertGroupHomepageTileRequest $request): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $targetType = GroupHomepageTileTargetType::from($request->string('target_type')->toString());
        $targetValue = $targetType === GroupHomepageTileTargetType::Category
            ? $request->string('target_value')->toString()
            : null;

        $this->upsertHomepageTile(
            targetType: $targetType,
            targetValue: $targetValue,
            title: $request->filled('title') ? $request->string('title')->toString() : '',
            isActive: $request->boolean('is_active', true),
            sortOrder: (int) $request->input('sort_order', 0),
            imageFile: $request->hasFile('image') ? $request->file('image') : null,
        );

        return redirect()->route('admin.group-ticketing.tiles.index')->with('status', 'tile-saved');
    }

    public function tilesBatchUpsert(BatchUpsertGroupHomepageTilesRequest $request): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $tiles = $request->validated('tiles');
        $uploadedTiles = $request->file('tiles') ?? [];

        foreach ($tiles as $formKey => $tileData) {
            if (! is_array($tileData)) {
                continue;
            }

            [$targetType, $targetValue] = $this->resolveBatchTileTarget((string) $formKey, $tileData);

            $existing = GroupHomepageTile::query()->where([
                'target_type' => $targetType->value,
                'target_value' => $targetValue,
            ])->first();

            $imageFile = null;
            if (is_array($uploadedTiles[$formKey] ?? null)) {
                $candidate = $uploadedTiles[$formKey]['image'] ?? null;
                if ($candidate instanceof UploadedFile) {
                    $imageFile = $candidate;
                }
            }

            $this->upsertHomepageTile(
                targetType: $targetType,
                targetValue: $targetValue,
                title: filled($tileData['title'] ?? null) ? (string) $tileData['title'] : '',
                isActive: filter_var($tileData['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                sortOrder: (int) ($tileData['sort_order'] ?? $existing?->sort_order ?? 0),
                imageFile: $imageFile,
            );
        }

        return redirect()->route('admin.group-ticketing.tiles.index')->with('status', 'tiles-saved');
    }

    public function tilesCreate(): RedirectResponse
    {
        Gate::authorize('platform.admin');

        return redirect()->route('admin.group-ticketing.tiles.index');
    }

    public function tilesStore(StoreGroupHomepageTileRequest $request): RedirectResponse
    {
        Gate::authorize('platform.admin');

        return redirect()->route('admin.group-ticketing.tiles.index');
    }

    public function tilesEdit(GroupHomepageTile $groupHomepageTile): RedirectResponse
    {
        Gate::authorize('platform.admin');

        return redirect()->route('admin.group-ticketing.tiles.index');
    }

    public function tilesUpdate(UpdateGroupHomepageTileRequest $request, GroupHomepageTile $groupHomepageTile): RedirectResponse
    {
        Gate::authorize('platform.admin');

        return redirect()->route('admin.group-ticketing.tiles.index');
    }

    public function tilesDestroy(GroupHomepageTile $groupHomepageTile): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $this->deleteStoredTileImage($groupHomepageTile->image_path);
        $groupHomepageTile->delete();

        return redirect()->route('admin.group-ticketing.tiles.index')->with('status', 'tile-reset');
    }

    public function categoriesIndex(GroupInventoryFacetService $facetService): View
    {
        Gate::authorize('platform.admin');

        return view(client_view('group-ticketing.categories.index', 'admin'), [
            'categories' => $facetService->categoriesForAdmin(),
        ]);
    }

    public function categoriesStore(StoreGroupCategoryRequest $request): RedirectResponse
    {
        Gate::authorize('platform.admin');

        return redirect()->route('admin.group-ticketing.categories.index')
            ->with('info', 'Categories are synced automatically from group inventory.');
    }

    public function categoriesUpdate(StoreGroupCategoryRequest $request, GroupCategory $groupCategory): RedirectResponse
    {
        Gate::authorize('platform.admin');

        return redirect()->route('admin.group-ticketing.categories.index')
            ->with('info', 'Categories are synced automatically from group inventory.');
    }

    public function categoriesDestroy(GroupCategory $groupCategory): RedirectResponse
    {
        Gate::authorize('platform.admin');

        return redirect()->route('admin.group-ticketing.categories.index')
            ->with('info', 'Categories are synced automatically from group inventory.');
    }

    public function inventoryIndex(Request $request, GroupInventoryFacetService $facetService): View
    {
        Gate::authorize('platform.admin');

        $query = GroupInventory::query()->with('category')->orderByDesc('synced_at');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                    ->orWhere('sector', 'like', $term)
                    ->orWhere('public_id', 'like', $term);
            });
        }

        $inventories = $query->paginate(25)->withQueryString();

        return view(client_view('group-ticketing.inventory.index', 'admin'), [
            'inventories' => $inventories,
            'filters' => $request->only(['q']),
            'activeInventoryCount' => $facetService->totalActiveInventoryCount(),
            'lastSyncAt' => $facetService->lastInventorySyncAt(),
        ]);
    }

    public function inventorySync(GroupInventorySyncService $syncService): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $result = $syncService->sync();

        if ($result['skipped']) {
            return redirect()->route('admin.group-ticketing.inventory.index')
                ->with('warning', $result['message'] ?? 'Sync skipped.');
        }

        return redirect()->route('admin.group-ticketing.inventory.index')
            ->with('status', 'Synced '.$result['synced'].' package(s).');
    }

    private function upsertHomepageTile(
        GroupHomepageTileTargetType $targetType,
        ?string $targetValue,
        string $title,
        bool $isActive,
        int $sortOrder,
        ?UploadedFile $imageFile = null,
    ): GroupHomepageTile {
        $match = [
            'target_type' => $targetType->value,
            'target_value' => $targetValue,
        ];

        $existing = GroupHomepageTile::query()->where($match)->first();

        $payload = [
            'title' => $title,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ];

        if ($imageFile instanceof UploadedFile) {
            $newPath = $this->storeTileImage($imageFile);
            $payload['image_path'] = $newPath;

            if ($existing !== null && $existing->image_path !== $newPath) {
                $this->deleteStoredTileImage($existing->image_path);
            }
        }

        return GroupHomepageTile::query()->updateOrCreate($match, $payload);
    }

    /**
     * @param  array<string, mixed>  $tileData
     * @return array{0: GroupHomepageTileTargetType, 1: ?string}
     */
    private function resolveBatchTileTarget(string $formKey, array $tileData): array
    {
        $targetType = GroupHomepageTileTargetType::from((string) ($tileData['target_type'] ?? ''));

        if ($targetType === GroupHomepageTileTargetType::All || $formKey === 'all') {
            return [GroupHomepageTileTargetType::All, null];
        }

        $targetValue = trim((string) ($tileData['target_value'] ?? ''));
        if ($targetValue === '' && str_starts_with($formKey, 'category:')) {
            $targetValue = substr($formKey, strlen('category:'));
        }

        return [GroupHomepageTileTargetType::Category, $targetValue !== '' ? $targetValue : null];
    }

    private function storeTileImage(UploadedFile $file): string
    {
        return $file->store('group-homepage-tiles', 'public');
    }

    private function deleteStoredTileImage(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $normalized = ltrim($path, '/');
        if (! str_starts_with($normalized, 'group-homepage-tiles/')) {
            return;
        }

        try {
            Storage::disk('public')->delete($normalized);
        } catch (\Throwable) {
            // Non-critical cleanup — tile update must still succeed.
        }
    }
}
