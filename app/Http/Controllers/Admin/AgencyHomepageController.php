<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateHomepageSectionRequest;
use App\Models\Agency;
use App\Models\HomepageFeaturedFare;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Agencies\HomepageSectionPresenter;
use App\Support\Client\ClientPageKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyHomepageController extends Controller
{
    public function __construct(
        protected AgencyBrandingService $brandingService,
        protected HomepageSectionPresenter $presenter,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        if (function_exists('uses_jetpk_company_branding') && uses_jetpk_company_branding()) {
            return redirect()
                ->route('admin.page-settings.edit', ['pageKey' => ClientPageKeys::HOME])
                ->with('status', 'Homepage content is managed in Page Settings. Legacy Homepage Sections values are preserved for compatibility.');
        }

        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        Gate::authorize('view', $agency);
        $this->brandingService->getSettingsForAgency($agency);

        $sections = $agency->homepageSections()->orderBy('sort_order')->get();
        $structured = [];
        foreach (HomepageSectionPresenter::STRUCTURED_SECTION_KEYS as $key) {
            $structured[$key] = $this->presenter->presentForAdmin(
                $sections->firstWhere('section_key', $key),
                $key,
            );
        }

        $settings = $this->brandingService->getSettingsForAgency($agency);

        $featuredFares = HomepageFeaturedFare::query()
            ->where('agency_id', $agency->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('dashboard.admin.settings.homepage', [
            'agency' => $agency,
            'sections' => $sections,
            'structuredSections' => $structured,
            'heroSection' => $this->presenter->presentHeroForAdmin(
                $sections->firstWhere('section_key', HomepageSectionPresenter::HERO),
                $settings,
                config('ota-brand', []),
            ),
            'heroSectionModel' => $sections->firstWhere('section_key', HomepageSectionPresenter::HERO),
            'iconOptions' => HomepageSectionPresenter::ICON_CLASSES,
            'featuredFares' => $featuredFares,
            'featuredFareOffsetOptions' => HomepageFeaturedFare::ALLOWED_DATE_OFFSETS,
        ]);
    }

    public function update(UpdateHomepageSectionRequest $request, string $section): RedirectResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        Gate::authorize('update', $agency);

        $validated = $request->validated();
        $validated['is_enabled'] = $request->boolean('is_enabled');

        if ($this->presenter->isHeroSection($section)) {
            $validated['subtitle'] = $this->presenter->sanitizeHeroBodyForStorage($validated['subtitle'] ?? null);
            $validated['content'] = $this->presenter->buildHeroContentFromRequest($request->all());
        } elseif ($this->presenter->isStructuredSection($section)) {
            $validated['content'] = $this->presenter->buildContentFromRequest($section, $request->all());
        } elseif (($validated['content'] ?? '') !== '') {
            $decoded = json_decode((string) $validated['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                return back()->withErrors(['content' => 'Content must be valid JSON object/array.'])->withInput();
            }
            $validated['content'] = $decoded;
        } else {
            $validated['content'] = null;
        }

        if ($request->hasFile('image')) {
            $media = $this->brandingService->uploadMedia($agency, $request->user(), $request->file('image'), 'homepage', null);
            $validated['image_path'] = $media->file_path;
        }

        $this->brandingService->updateHomepageSection($agency, $request->user(), $section, $validated);

        $response = back()->with('status', 'homepage-section-updated');
        if ($section === HomepageSectionPresenter::FEATURE_CARDS) {
            return $response->withFragment('featured-fares');
        }

        return $response;
    }
}
