<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\HomepageFeaturedFare;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Agencies\HomepageSectionPresenter;
use App\Services\GroupTicketing\GroupInventoryFacetService;
use App\Support\Booking\AgentBookingContext;
use App\Support\Branding\SafeBrandingResolver;
use App\Support\GroupTicketing\GroupHomepageTilePresenter;
use App\Support\Client\Homepage\JetpkHomepageContextDiagnostic;
use App\Support\Client\Homepage\HomepageSectionOrderResolver;
use App\Support\Client\ClientPageKeys;
use App\Services\Client\ClientPageContentResolver;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected AgencyBrandingService $brandingService,
        protected HomepageSectionPresenter $homepageSections,
        protected MobileViewPreference $mobileViewPreference,
        protected GroupHomepageTilePresenter $groupHomepageTiles,
        protected GroupInventoryFacetService $groupInventoryFacets,
        protected JetpkHomepageContextDiagnostic $homepageDiagnostic,
    ) {}

    public function index(Request $request): View
    {
        $brandingPayload = SafeBrandingResolver::resolveForPublic($this->brandingService);
        $settings = $brandingPayload['settings'];
        $sections = $brandingPayload['sections'] ?? collect();

        $brand = config('ota-brand', []);

        $heroSection = $this->homepageSections->presentHero(
            $sections['hero'] ?? null,
            $settings,
            $brand,
        );

        $trustSection = $this->homepageSections->present($sections['trust_metrics'] ?? null, HomepageSectionPresenter::TRUST_METRICS);
        $featureSection = $this->homepageSections->present($sections['feature_cards'] ?? null, HomepageSectionPresenter::FEATURE_CARDS);
        $popularSection = $this->homepageSections->present($sections['popular_routes'] ?? null, HomepageSectionPresenter::POPULAR_ROUTES);
        $whySection = $this->homepageSections->present($sections['why_choose_us'] ?? null, HomepageSectionPresenter::WHY_CHOOSE_US);

        $recentFarePayload = $request->session()->get('home_recent_fares', []);
        $recentOffers = is_array($recentFarePayload['offers'] ?? null) ? $recentFarePayload['offers'] : [];
        $recentCriteria = is_array($recentFarePayload['criteria'] ?? null) ? $recentFarePayload['criteria'] : [];

        $featuredFareRules = $this->loadFeaturedFareRules();
        $dynamicFeaturedFares = array_values(array_filter(
            $featuredFareRules,
            fn (HomepageFeaturedFare $fare): bool => $fare->hasDisplayableSnapshot(),
        ));

        $viewData = [
            'contacts' => config('ota-routes.contacts', []),
            'defaultDepart' => '',
            'defaultOrigin' => '',
            'defaultDestination' => '',
            'defaultReturnDate' => '',
            'defaultTripType' => 'one_way',
            'minDate' => now()->format('Y-m-d'),
            'client' => config('ota-client', []),
            'publicBranding' => $brandingPayload,
            'agencySettings' => $settings,
            'heroSection' => $heroSection,
            'trustMetricsSection' => $trustSection,
            'featureCardsSection' => $featureSection,
            'popularRoutesSection' => $popularSection,
            'whyChooseUsSection' => $whySection,
            'recentFareOffers' => $recentOffers,
            'recentFareCriteria' => $recentCriteria,
            'dynamicFeaturedFares' => $dynamicFeaturedFares,
            'featuredFareRules' => $featuredFareRules,
            'groupHomepageTiles' => $this->groupHomepageTiles->presentForHome(),
            'groupFacets' => $this->groupInventoryFacets->all(),
            'agentBookingModeActive' => AgentBookingContext::isActive($request),
            'agentBookingAgencyName' => AgentBookingContext::agencyDisplayName($request) ?? '',
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.home', $viewData);
        }

        if ($this->shouldUseJetPakistanThemeHome()) {
            $this->homepageDiagnostic->logIfEnabled($request);
            $homepageContent = app(ClientPageContentResolver::class)->contentFor(ClientPageKeys::HOME);
            $viewData['homepageOrderedSections'] = app(HomepageSectionOrderResolver::class)
                ->orderedSections($homepageContent);

            return view(client_view('frontend.home', 'frontend'), $viewData);
        }

        if (is_v2_ui()) {
            return ui_view('frontend.home', $viewData);
        }

        return view(client_view('frontend.home', 'frontend'), $viewData);
    }

    /**
     * @return list<HomepageFeaturedFare>
     */
    protected function loadFeaturedFareRules(): array
    {
        if (! Schema::hasTable('homepage_featured_fares') || ! Schema::hasTable('agencies')) {
            return [];
        }

        $slug = (string) config('ota.default_agency_slug', '');
        if ($slug === '') {
            return [];
        }

        $agency = Agency::query()->where('slug', $slug)->first();
        if ($agency === null) {
            return [];
        }

        return HomepageFeaturedFare::query()
            ->where('agency_id', $agency->id)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    protected function shouldUseJetPakistanThemeHome(): bool
    {
        if (! is_client_preview()) {
            return false;
        }

        if (client_theme()->frontendTheme() !== 'jetpakistan') {
            return false;
        }

        return client_view_exists('frontend.home', 'frontend');
    }
}
