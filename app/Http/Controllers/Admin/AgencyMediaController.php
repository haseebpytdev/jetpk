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

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        Gate::authorize('viewAny', [AgencyMedia::class, $agency]);

        $query = $agency->media()->with('uploader');

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

        if ($this->wantsBackOfficeJson($request)) {
            $items = $query->paginate(24);

            return $this->backOfficeJson([
                'ok' => true,
                'media' => collect($items->items())->map(fn (AgencyMedia $item) => $this->presentMedia($item))->all(),
            ]);
        }

        return redirect()->route('admin.cms-pages.index');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        Gate::authorize('create', [AgencyMedia::class, $agency]);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'collection' => ['nullable', 'string', 'max:50'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->brandingService->uploadMedia(
            $agency,
            $request->user(),
            $request->file('file'),
            $validated['collection'] ?? 'general',
            $validated['alt_text'] ?? null,
        );

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'media' => $this->presentMedia($media),
            ]);
        }

        return back()->with('status', 'media-uploaded');
    }

    public function destroy(Request $request, AgencyMedia $agencyMedia): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $agencyMedia);
        $this->brandingService->deleteMedia($agencyMedia, $request->user());

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true]);
        }

        return back()->with('status', 'media-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentMedia(AgencyMedia $item): array
    {
        return [
            'id' => (string) $item->id,
            'file_name' => (string) $item->file_name,
            'collection' => (string) ($item->collection ?? ''),
            'alt_text' => (string) ($item->alt_text ?? ''),
            'mime_type' => (string) ($item->mime_type ?? ''),
            'url' => filled($item->file_path) ? asset('storage/'.$item->file_path) : null,
        ];
    }
}
