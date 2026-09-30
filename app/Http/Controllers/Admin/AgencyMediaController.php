<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyMedia;
use App\Services\Agencies\AgencyBrandingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyMediaController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected AgencyBrandingService $brandingService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $agency = null;
        if ($user?->current_agency_id) {
            $agency = Agency::query()->find($user->current_agency_id);
        }
        if ($agency === null && $user?->isPlatformAdmin()) {
            $agency = Agency::query()->orderBy('id')->first();
        }
        abort_if($agency === null, 404);
        Gate::authorize('viewAny', [AgencyMedia::class, $agency]);

        $query = AgencyMedia::query()
            ->with('uploader')
            ->when(
                $user?->isPlatformAdmin() && ! $request->filled('agency_id'),
                fn ($q) => $q->where('agency_id', $agency->id),
                fn ($q) => $q->where('agency_id', $agency->id),
            );

        if ($request->filled('q')) {
            $q = '%'.trim((string) $request->string('q')).'%';
            $query->where(function ($builder) use ($q): void {
                $builder->where('file_name', 'like', $q)
                    ->orWhere('alt_text', 'like', $q)
                    ->orWhere('collection', 'like', $q);
            });
        }

        if ($request->filled('collection')) {
            $query->where('collection', (string) $request->string('collection'));
        }

        if ($request->filled('type')) {
            $type = (string) $request->string('type');
            if ($type === 'image') {
                $query->where('mime_type', 'like', 'image/%');
            }
        }

        $sort = (string) $request->string('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy('file_name'),
            'size' => $query->orderByDesc('size_bytes'),
            default => $query->latest('id'),
        };

        $mediaItems = $query->paginate(24)->withQueryString();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'agency_id' => (string) $agency->id,
                'assets' => $mediaItems->getCollection()->map(static fn (AgencyMedia $media): array => [
                    'id' => (string) $media->id,
                    'file_name' => (string) $media->file_name,
                    'alt_text' => (string) ($media->alt_text ?? ''),
                    'collection' => (string) ($media->collection ?? 'general'),
                    'mime_type' => (string) ($media->mime_type ?? ''),
                    'size_bytes' => (int) ($media->size_bytes ?? 0),
                    'url' => (string) ($media->file_path ?? ''),
                    'uploader' => $media->uploader?->name,
                    'created_at' => $media->created_at?->toIso8601String(),
                ])->values()->all(),
                'collections' => \App\Support\Client\ClientPageMediaConsumption::collections(),
                'meta' => [
                    'page' => $mediaItems->currentPage(),
                    'pageCount' => $mediaItems->lastPage(),
                    'pageSize' => $mediaItems->perPage(),
                    'total' => $mediaItems->total(),
                ],
                'filters' => [
                    'q' => (string) $request->string('q'),
                    'collection' => (string) $request->string('collection'),
                    'type' => (string) $request->string('type'),
                    'sort' => $sort,
                ],
            ]);
        }

        return view(client_view('settings.media', 'admin'), [
            'agency' => $agency,
            'mediaItems' => $mediaItems,
            'collections' => \App\Support\Client\ClientPageMediaConsumption::collections(),
            'filters' => [
                'q' => (string) $request->string('q'),
                'collection' => (string) $request->string('collection'),
                'type' => (string) $request->string('type'),
                'sort' => $sort,
                'view' => (string) $request->string('view', 'grid'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        Gate::authorize('create', [AgencyMedia::class, $agency]);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'collection' => ['nullable', 'string', 'max:50'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $this->brandingService->uploadMedia(
            $agency,
            $request->user(),
            $request->file('file'),
            $validated['collection'] ?? 'general',
            $validated['alt_text'] ?? null,
        );

        return back()->with('status', 'media-uploaded');
    }

    public function destroy(Request $request, AgencyMedia $agencyMedia): RedirectResponse
    {
        Gate::authorize('delete', $agencyMedia);
        $this->brandingService->deleteMedia($agencyMedia, $request->user());

        return back()->with('status', 'media-deleted');
    }
}
