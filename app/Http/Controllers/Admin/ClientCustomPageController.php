<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientPage;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ClientPageKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClientCustomPageController extends Controller
{
    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    public function index(): View
    {
        Gate::authorize('client.page-settings.manage');
        $profile = $this->clientContext->get();
        $pages = ClientPage::query()
            ->when($profile, fn ($q) => $q->where('client_profile_id', $profile->id))
            ->orderBy('public_title')
            ->get();

        return view('themes.admin.jetpakistan.page-settings.custom-pages.index', compact('pages'));
    }

    public function create(): View
    {
        Gate::authorize('client.page-settings.manage');

        return view('themes.admin.jetpakistan.page-settings.custom-pages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        $profile = $this->clientContext->get();
        abort_if($profile === null, 404);

        $validated = $request->validate([
            'internal_name' => ['required', 'string', 'max:120'],
            'public_title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:120'],
            'nav_label' => ['nullable', 'string', 'max:120'],
        ]);

        $slug = ClientManagedPageReservedSlugs::normalize($validated['slug']);
        if (! ClientManagedPageReservedSlugs::isValidFormat($slug) || ClientManagedPageReservedSlugs::isReserved($slug)) {
            return back()->withErrors(['slug' => 'Slug is invalid or reserved.'])->withInput();
        }

        $page = ClientPage::query()->create([
            'client_profile_id' => $profile->id,
            'slug' => $slug,
            'internal_name' => $validated['internal_name'],
            'public_title' => $validated['public_title'],
            'nav_label' => $validated['nav_label'] ?? $validated['public_title'],
            'enabled' => true,
            'show_header' => true,
            'show_footer' => true,
        ]);

        $pageKey = ClientPageKeys::customKey($slug);
        $this->contentResolver->saveDraft($profile, $pageKey, [
            'identity' => [
                'title' => $validated['public_title'],
                'slug' => $slug,
                'nav_label' => $validated['nav_label'] ?? $validated['public_title'],
            ],
            'sections' => ['items' => []],
            'seo' => [
                'title' => $validated['public_title'],
                'description' => '',
                'robots' => 'index,follow',
            ],
        ], $request->user()?->id);

        return redirect()
            ->route('admin.page-settings.edit', ['pageKey' => $pageKey])
            ->with('status', 'Custom page created. Save draft and publish when ready.');
    }
}
