<?php

namespace Tests\Feature\Ui;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agent;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Agents\AgentPermission;
use App\Support\Ui\MobileViewPreference;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class MobileViewPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function mobileUserAgentHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ];
    }

    public function test_home_defaults_to_desktop_layout_without_preference_cookie(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="public-nav-desktop"', $html);
        $this->assertStringContainsString('ota-public.css', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_mobile_user_agent_auto_renders_mobile_home_without_preference_cookie(): void
    {
        $html = $this->withHeaders(array_merge($this->mobileUserAgentHeaders(), [
            'Sec-CH-Viewport-Width' => '390',
        ]))
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringContainsString('ota-mobile-app.css', $html);
        $this->assertStringNotContainsString('data-testid="public-nav-desktop"', $html);
    }

    public function test_viewport_width_header_auto_renders_mobile_home_without_mobile_user_agent(): void
    {
        $html = $this->get('/?_ota_auto_shell=mobile')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringNotContainsString('jp-site-main', $html);
    }

    public function test_viewport_width_header_auto_renders_desktop_home_above_breakpoint(): void
    {
        $html = $this->withHeaders(['Sec-CH-Viewport-Width' => '1440'])
            ->get('/?_ota_auto_shell=desktop')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('jp-site-main', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_manual_desktop_preference_overrides_narrow_viewport_header(): void
    {
        $cookieName = (string) config('ota-mobile.cookie_name', 'ota_view_mode');

        $html = $this->withHeaders(['Sec-CH-Viewport-Width' => '390'])
            ->withCookie($cookieName, MobileViewPreference::MODE_DESKTOP)
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('jp-site-main', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_manual_mobile_preference_overrides_wide_viewport_header(): void
    {
        $cookieName = (string) config('ota-mobile.cookie_name', 'ota_view_mode');

        $html = $this->withHeaders(['Sec-CH-Viewport-Width' => '1440'])
            ->withCookie($cookieName, MobileViewPreference::MODE_MOBILE)
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringNotContainsString('jp-site-main', $html);
    }

    public function test_jetpk_desktop_shows_mobile_app_view_toggle_above_breakpoint(): void
    {
        $html = $this->withHeaders(['Sec-CH-Viewport-Width' => '1440'])
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="jp-desktop-mobile-app-toggle"', $html);
        $this->assertStringContainsString('jp-site-main', $html);
    }

    public function test_auto_shell_query_reconciles_mobile_without_cookie(): void
    {
        $html = $this->get('/?_ota_auto_shell=mobile')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_jetpk_mobile_theme_includes_jp_app_and_app_css_when_enabled(): void
    {
        config(['ota-mobile.app_theme' => 'jetpakistan-app']);

        $html = $this->withHeaders(array_merge($this->mobileUserAgentHeaders(), [
            'Sec-CH-Viewport-Width' => '390',
        ]))
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="ota-mobile-app jp-app', $html);
        $this->assertMatchesRegularExpression(
            '#themes/mobile/jetpakistan-app/css/app\.css\?v=\d+#',
            $html,
        );
    }

    public function test_mobile_home_renders_travellers_cabin_sheet_markup(): void
    {
        $html = $this->withHeaders($this->mobileUserAgentHeaders())
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-travellers-trigger', $html);
        $this->assertStringContainsString('data-testid="ota-mobile-travellers-sheet"', $html);
        $this->assertStringContainsString('data-travellers-adults-input', $html);
        $this->assertStringContainsString('data-travellers-children-input', $html);
        $this->assertStringContainsString('data-travellers-infants-input', $html);
        $this->assertStringContainsString('data-travellers-cabin-input', $html);
        $this->assertStringContainsString('name="cabin"', $html);
    }

    public function test_mobile_user_agent_login_renders_mobile_shell(): void
    {
        $html = $this->withHeaders($this->mobileUserAgentHeaders())
            ->get(route('login'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringContainsString('data-testid="ota-mobile-login"', $html);
        $this->assertStringContainsString('ota-mobile-app.css', $html);
        $this->assertStringNotContainsString('data-testid="public-nav-desktop"', $html);
    }

    public function test_mobile_user_agent_agent_registration_pages_render_mobile_shell(): void
    {
        foreach ([
            ['route' => route('agent.register'), 'testid' => 'ota-mobile-agent-registration-landing'],
            ['route' => route('agent.register.form'), 'testid' => 'ota-mobile-agent-registration-form'],
            ['route' => route('agent.register.submitted'), 'testid' => 'ota-mobile-agent-registration-submitted'],
        ] as $page) {
            $html = $this->withHeaders($this->mobileUserAgentHeaders())
                ->get($page['route'])
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html, $page['route']);
            $this->assertStringContainsString('data-testid="'.$page['testid'].'"', $html, $page['route']);
            $this->assertStringNotContainsString('data-testid="public-nav-desktop"', $html, $page['route']);
        }
    }

    public function test_desktop_view_manual_override_keeps_desktop_agent_registration_submitted_on_mobile_device(): void
    {
        $this->withHeaders($this->mobileUserAgentHeaders())
            ->get('/desktop-view')
            ->assertRedirect('/');

        $html = $this->withHeaders($this->mobileUserAgentHeaders())
            ->get(route('agent.register.submitted'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ota-agent-register-submitted', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-agent-registration-submitted"', $html);
    }

    public function test_desktop_view_manual_override_keeps_desktop_login_on_mobile_device(): void
    {
        $this->withHeaders($this->mobileUserAgentHeaders())
            ->get('/desktop-view')
            ->assertRedirect('/');

        $html = $this->withHeaders($this->mobileUserAgentHeaders())
            ->get(route('login'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="public-nav-desktop"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-login"', $html);
    }

    public function test_mobile_cookie_switches_auth_and_support_pages_to_mobile_shell(): void
    {
        $cookieName = (string) config('ota-mobile.cookie_name', 'ota_view_mode');

        foreach ([
            ['route' => route('register'), 'testid' => 'ota-mobile-register'],
            ['route' => route('password.request'), 'testid' => 'ota-mobile-forgot-password'],
            ['route' => route('booking.lookup'), 'testid' => 'ota-mobile-booking-lookup'],
            ['route' => route('support'), 'testid' => 'ota-mobile-support'],
            ['route' => route('about'), 'testid' => 'ota-mobile-about'],
        ] as $page) {
            $html = $this->withCookie($cookieName, MobileViewPreference::MODE_MOBILE)
                ->get($page['route'])
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html, $page['route']);
            $this->assertStringContainsString('data-testid="'.$page['testid'].'"', $html, $page['route']);
            $this->assertStringNotContainsString('data-testid="public-nav-desktop"', $html, $page['route']);
        }
    }

    public function test_request_demo_redirects_to_support_with_mobile_cookie(): void
    {
        $this->withCookie(
            (string) config('ota-mobile.cookie_name', 'ota_view_mode'),
            MobileViewPreference::MODE_MOBILE,
        )->get(route('request-demo'))->assertRedirect(route('support'));
    }

    public function test_desktop_view_manual_override_on_mobile_device_keeps_desktop_home(): void
    {
        $this->withHeaders($this->mobileUserAgentHeaders())
            ->get('/desktop-view')
            ->assertRedirect('/');

        $html = $this->withHeaders($this->mobileUserAgentHeaders())
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="public-nav-desktop"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_mobile_view_route_sets_preference_and_renders_mobile_home(): void
    {
        $this->get('/mobile-view')->assertRedirect('/');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_mobile_preview_route_sets_preference_and_renders_mobile_home(): void
    {
        $this->get('/mobile-app-preview')->assertRedirect('/');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringContainsString('ota-mobile-app.css', $html);
        $this->assertStringNotContainsString('data-testid="public-nav-desktop"', $html);
        $this->assertStringNotContainsString('ota-public.css', $html);
    }

    public function test_desktop_preview_route_restores_desktop_home(): void
    {
        $this->get('/mobile-app-preview')->assertRedirect('/');

        $this->get('/desktop-view')->assertRedirect('/');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="public-nav-desktop"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_post_desktop_preference_from_mobile_shell_restores_desktop_home(): void
    {
        $this->get('/mobile-app-preview')->assertRedirect('/');

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post(route('view-preference.desktop'), [
            'redirect' => url('/'),
        ])->assertRedirect('/');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="public-nav-desktop"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_unsafe_redirect_falls_back_to_home(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('view-preference.mobile'), [
            'redirect' => 'https://evil.example/phish',
        ])->assertRedirect('/');

        $this->post(route('view-preference.desktop'), [
            'redirect' => 'https://evil.example/phish',
        ])->assertRedirect('/');
    }

    public function test_flights_results_defaults_to_desktop_layout_without_preference_cookie(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('searchWithMeta')->andReturn([
                'offers' => [],
                'warnings' => [],
            ]);
        });

        $query = http_build_query([
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(14)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => '1',
            'children' => '0',
            'infants' => '0',
        ]);

        $html = $this->get('/flights/results?'.$query)->assertOk()->getContent();

        $this->assertStringContainsString('ota-results-pro', $html);
        $this->assertStringContainsString('data-testid="public-nav-desktop"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-results"', $html);
    }

    public function test_mobile_cookie_switches_flights_results_to_mobile_shell(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('searchWithMeta')->andReturn([
                'offers' => [],
                'warnings' => [],
            ]);
        });

        $query = http_build_query([
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(14)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => '1',
            'children' => '0',
            'infants' => '0',
        ]);

        $html = $this->withCookie(
            (string) config('ota-mobile.cookie_name', 'ota_view_mode'),
            MobileViewPreference::MODE_MOBILE,
        )->get('/flights/results?'.$query)->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-results"', $html);
        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringContainsString('ota-mobile-app.css', $html);
        $this->assertStringNotContainsString('ota-results-pro', $html);
        $this->assertStringNotContainsString('data-testid="public-nav-desktop"', $html);
    }

    public function test_booking_passengers_defaults_to_desktop_without_mobile_cookie(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $url = '/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID.'&from=LHE&to=DXB&depart='.$depart;

        $html = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('ota-checkout-page', $html);
        $this->assertStringContainsString('data-testid="public-nav-desktop"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-passengers"', $html);
    }

    public function test_mobile_cookie_switches_booking_passengers_to_mobile_shell(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $url = '/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID.'&from=LHE&to=DXB&depart='.$depart;

        $html = $this->withCookie(
            (string) config('ota-mobile.cookie_name', 'ota_view_mode'),
            MobileViewPreference::MODE_MOBILE,
        )->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-passengers"', $html);
        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringContainsString('ota-mobile-app.css', $html);
        $this->assertStringNotContainsString('ota-checkout-page', $html);
        $this->assertStringNotContainsString('data-testid="public-nav-desktop"', $html);
    }

    public function test_mobile_cookie_switches_booking_review_and_confirmation_to_mobile_shell(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'mobile-shell@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $cookieName = (string) config('ota-mobile.cookie_name', 'ota_view_mode');

        $reviewHtml = $this->withCookie($cookieName, MobileViewPreference::MODE_MOBILE)
            ->get(route('booking.review'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-testid="ota-mobile-review"', $reviewHtml);
        $this->assertStringNotContainsString('ota-review-page', $reviewHtml);

        $this->withCookie($cookieName, MobileViewPreference::MODE_MOBILE)
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $confirmHtml = $this->withCookie($cookieName, MobileViewPreference::MODE_MOBILE)
            ->get(route('booking.confirmation'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-testid="ota-mobile-confirmation"', $confirmHtml);
        $this->assertStringNotContainsString('ota-confirmation-page', $confirmHtml);
    }

    public function test_mobile_shell_uses_compact_desktop_link_control(): void
    {
        $this->get('/mobile-app-preview')->assertRedirect('/');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-app-desktop-toggle"', $html);
        $this->assertStringContainsString('ota-mobile-app__desktop-link-btn', $html);
        $this->assertStringNotContainsString('ota-mobile-app__view-toggle-btn', $html);
    }

    public function test_mobile_flight_details_renders_from_search_store(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $offer = PublicCheckoutTestDoubles::searchOfferPayload($depart, 'LHE', 'DXB');
        $searchId = app(FlightSearchResultStore::class)->store($criteria, [$offer], []);

        $html = $this->withCookie(
            (string) config('ota-mobile.cookie_name', 'ota_view_mode'),
            MobileViewPreference::MODE_MOBILE,
        )->get(route('flights.results.offer', [
            'search_id' => $searchId,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-flight-details"', $html);
        $this->assertStringContainsString('Flight Details', $html);
        $this->assertStringContainsString('LHE → DXB', $html);
        $this->assertStringContainsString('TestAir', $html);
        $this->assertStringContainsString('data-testid="ota-mobile-flight-details-select"', $html);
    }

    public function test_mobile_flight_details_expired_search_redirects_with_warning(): void
    {
        $response = $this->withCookie(
            (string) config('ota-mobile.cookie_name', 'ota_view_mode'),
            MobileViewPreference::MODE_MOBILE,
        )->get(route('flights.results.offer', [
            'search_id' => 'missing-search-id',
            'offer_id' => 'missing-offer-id',
        ]));

        $response->assertRedirect(route('home').'#ota-flight-search');
        $response->assertSessionHas('offer_warning', 'This fare is no longer available. Please refresh results and select again.');
        $response->assertSessionHasErrors('flight_id');
    }

    public function test_desktop_mode_redirects_mobile_flight_details_to_results(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $offer = PublicCheckoutTestDoubles::searchOfferPayload($depart, 'LHE', 'DXB');
        $searchId = app(FlightSearchResultStore::class)->store($criteria, [$offer], []);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('searchWithMeta')->andReturn([
                'offers' => [],
                'warnings' => [],
            ]);
        });

        $this->get(route('flights.results.offer', [
            'search_id' => $searchId,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
        ]))->assertRedirect(route('flights.results', [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]));
    }

    public function test_customer_dashboard_defaults_to_desktop_without_mobile_cookie(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $html = $this->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-dashboard-kpis"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-customer-dashboard"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-app-shell"', $html);
    }

    public function test_mobile_cookie_switches_customer_dashboard_to_mobile_shell(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $html = $this->actingAs($customer)
            ->withCookie(
                (string) config('ota-mobile.cookie_name', 'ota_view_mode'),
                MobileViewPreference::MODE_MOBILE,
            )
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-customer-dashboard"', $html);
        $this->assertStringContainsString('data-testid="ota-mobile-app-shell"', $html);
        $this->assertStringContainsString('ota-mobile-app.css', $html);
        $this->assertStringNotContainsString('data-testid="customer-dashboard-kpis"', $html);
    }

    public function test_agent_dashboard_defaults_to_desktop_without_mobile_cookie(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $html = $this->actingAs($agent)
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="agent-dashboard-kpis"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-agent-dashboard"', $html);
    }

    public function test_mobile_cookie_switches_agent_dashboard_to_mobile_shell(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $html = $this->actingAs($agent)
            ->withCookie(
                (string) config('ota-mobile.cookie_name', 'ota_view_mode'),
                MobileViewPreference::MODE_MOBILE,
            )
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-agent-dashboard"', $html);
        $this->assertStringContainsString('data-testid="ota-mobile-agent-wallet-pill"', $html);
        $this->assertStringNotContainsString('data-testid="agent-dashboard-kpis"', $html);
    }

    public function test_mobile_agent_staff_dashboard_hides_wallet_without_permission(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        $staff = User::query()->create([
            'name' => 'Limited Staff',
            'username' => 'limited-staff-mobile',
            'email' => 'limited-staff-mobile@agency.test',
            'password' => bcrypt('password'),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agent->agency_id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => [AgentPermission::BookingsView, AgentPermission::AgencyView],
            ],
        ]);

        $html = $this->actingAs($staff)
            ->withCookie(
                (string) config('ota-mobile.cookie_name', 'ota_view_mode'),
                MobileViewPreference::MODE_MOBILE,
            )
            ->get(route('agent.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-mobile-agent-dashboard"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-agent-wallet-pill"', $html);
        $this->assertStringNotContainsString('data-testid="ota-mobile-agent-wallet-quick"', $html);
    }
}
