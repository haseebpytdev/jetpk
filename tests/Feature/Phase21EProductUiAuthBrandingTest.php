<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class Phase21EProductUiAuthBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function configuredBrandName(): string
    {
        return (string) config('ota-client.agency_name');
    }

    public function test_homepage_uses_configured_branding_fallback_text(): void
    {
        $brand = $this->configuredBrandName();
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee($brand, false)
            ->assertDontSee('white-label', false)
            ->assertDontSee('Client preview', false);
    }

    public function test_auth_pages_render_branded_layout(): void
    {
        $brand = $this->configuredBrandName();

        $this->get('/login')
            ->assertOk()
            ->assertSee('Welcome to '.$brand, false)
            ->assertSee('Log in | '.$brand, false);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Reset your password', false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('Register | '.$brand, false)
            ->assertSee('Book flights, track your booking requests, submit payment proof, and access travel documents from one place.', false);

        $this->get('/agent/register')
            ->assertOk()
            ->assertSee('Join the '.$brand.' Agent Network', false)
            ->assertSee('Submit application', false)
            ->assertSee('Admin review', false)
            ->assertSee('Receive activation link', false);
    }

    public function test_customer_registration_creates_customer_account_and_redirects_customer_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $response = $this->post('/register', [
            'first_name' => 'New',
            'last_name' => 'Customer',
            'email' => 'new.customer@example.com',
            'mobile' => '923001234567',
            'security_check' => '5',
            'terms' => '1',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/customer');

        $user = User::query()->where('email', 'new.customer@example.com')->firstOrFail();
        $this->assertSame(AccountType::Customer, $user->account_type);
    }

    public function test_agent_application_route_stores_pending_application_without_creating_agent_account(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $response = $this->post('/agent/register', [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali.agent@example.com',
            'mobile' => '+923009998887',
            'company_name' => 'Khan Travels',
            'business_type' => 'Travel agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Main Boulevard, Lahore',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('agent.register.submitted'));
        $this->assertDatabaseHas('agent_applications', [
            'email' => 'ali.agent@example.com',
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'ali.agent@example.com',
            'account_type' => AccountType::Agent->value,
        ]);
    }

    public function test_agent_application_submission_is_idempotent_by_email(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $payload = [
            'first_name' => 'Furqan',
            'last_name' => 'Applicant',
            'email' => 'Furqan.Agent@Example.com',
            'mobile' => '+923221111111',
            'company_name' => 'Furqan Travels',
            'business_type' => 'Travel agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Main Boulevard, Lahore',
            'terms' => '1',
        ];

        $this->post('/agent/register', $payload)
            ->assertRedirect(route('agent.register.submitted'));

        $this->post('/agent/register', [
            ...$payload,
            'email' => 'furqan.agent@example.com',
        ])
            ->assertRedirect(route('agent.register.submitted'))
            ->assertSessionHas('status');

        $this->assertSame(1, AgentApplication::query()
            ->whereRaw('LOWER(email) = ?', ['furqan.agent@example.com'])
            ->count());

        $this->assertDatabaseHas('agent_applications', [
            'email' => 'furqan.agent@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_agent_application_rejects_email_that_already_belongs_to_agent_account(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        User::factory()->create([
            'email' => 'existing.agent@example.com',
            'account_type' => AccountType::Agent,
        ]);

        $this->post('/agent/register', [
            'first_name' => 'Existing',
            'last_name' => 'Agent',
            'email' => 'existing.agent@example.com',
            'mobile' => '+923221111112',
            'company_name' => 'Existing Agent Travels',
            'business_type' => 'Travel agency',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'office_address' => 'Business Road, Karachi',
            'terms' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('agent_applications', [
            'email' => 'existing.agent@example.com',
        ]);
    }

    public function test_agent_registration_form_locks_submit_button_after_submit(): void
    {
        $this->get('/agent/register/apply')
            ->assertOk()
            ->assertSee('data-agent-registration-form', false)
            ->assertSee('data-agent-registration-submit', false)
            ->assertSee('Submitting application...', false);
    }

    public function test_staff_and_admin_public_registration_routes_are_not_available(): void
    {
        $this->get('/register/staff')->assertNotFound();
        $this->get('/register/admin')->assertNotFound();
    }

    public function test_admin_can_approve_agent_application_and_create_agent_profile(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $application = AgentApplication::query()->create([
            'first_name' => 'Agent',
            'last_name' => 'Applicant',
            'email' => 'agent.applicant@example.com',
            'mobile' => '+923221112233',
            'company_name' => 'Applicant Travels',
            'business_type' => 'Travel agency',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'office_address' => 'Shahrah-e-Faisal, Karachi',
            'status' => 'pending',
        ]);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.agent-applications.approve', $application), [
            'internal_note' => 'Looks good',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame($admin->id, $application->reviewed_by);

        $agentUser = User::query()->where('email', 'agent.applicant@example.com')->firstOrFail();
        $this->assertSame(AccountType::Agent, $agentUser->account_type);
        $this->assertDatabaseHas('agents', ['user_id' => $agentUser->id]);
        $this->assertInstanceOf(Agent::class, Agent::query()->where('user_id', $agentUser->id)->first());
    }

    public function test_navbar_information_architecture_matches_final_public_navigation(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertIsString($html);

        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('Booking', $html);
        $this->assertStringContainsString('Support', $html);
        $this->assertStringContainsString('Contact', $html);
        $this->assertStringContainsString('Login', $html);
        $this->assertStringContainsString('Sign Up', $html);
        $this->assertStringContainsString('Agent Registration', $html);
        $this->assertStringContainsString('Customer Login', $html);
        $this->assertStringContainsString('Agent Login', $html);
        $this->assertStringContainsString('Operator Login', $html);
        $this->assertStringContainsString('Signup', $html);
        $this->assertStringContainsString('Sign up', $html);
        $this->assertStringContainsString('Agents', $html);

        preg_match('/data-testid="public-nav-desktop"[^>]*>(.*?)<\/div>\s*<div class="ota-nav-actions"/s', $html, $desktop);
        $desktopNav = $desktop[1] ?? '';
        $this->assertStringNotContainsString('>Flights<', $desktopNav);
        $this->assertStringNotContainsString('Agent Network', $desktopNav);

        preg_match('/data-testid="public-nav-mobile"[^>]*>(.*?)<\/nav>/s', $html, $mobile);
        $mobileNav = $mobile[1] ?? '';
        $this->assertStringNotContainsString('>Flights<', $mobileNav);
        $this->assertStringNotContainsString('Agent Network', $mobileNav);
    }

    public function test_support_and_about_pages_render_distinct_content(): void
    {
        $this->get('/support')
            ->assertOk()
            ->assertSee('Help and Support Center', false)
            ->assertSee('Support request', false)
            ->assertSee('Submit support request', false)
            ->assertSee('Manage booking', false)
            ->assertSee('Office &amp; channels', false)
            ->assertDontSee('Send a message', false);

        $brand = $this->configuredBrandName();

        $this->get('/about-us')
            ->assertOk()
            ->assertSee('About us', false)
            ->assertSee('About '.$brand, false)
            ->assertDontSee('Help and Support Center', false);
    }

    public function test_agent_application_form_contains_review_notice(): void
    {
        $brand = $this->configuredBrandName();

        $this->get('/agent/register/apply')
            ->assertOk()
            ->assertSee('Agent applications are reviewed by '.$brand.'. After approval, you will receive an activation email.', false)
            ->assertSee('Submit Agent Application', false);
    }

    public function test_agent_application_submitted_page_confirms_no_instant_access(): void
    {
        $this->get('/agent/register/submitted')
            ->assertOk()
            ->assertSee('Application submitted', false)
            ->assertSee('You will receive login access only after approval.', false);
    }

    public function test_login_page_contains_all_user_type_access_wording(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Customer', false)
            ->assertSee('View bookings and documents', false)
            ->assertSee('Agent', false)
            ->assertSee('Manage requests and commissions', false)
            ->assertSee('Operator', false)
            ->assertSee('Admin and staff access', false)
            ->assertSee('Sign up', false)
            ->assertSee('Become our agent', false);
    }

    public function test_auth_and_signup_pages_do_not_show_demo_or_supplier_placeholders(): void
    {
        foreach (['/login', '/register', '/agent/register', '/agent/register/apply', '/agent/register/submitted'] as $path) {
            $response = $this->get($path)->assertOk();
            $response->assertDontSee('iati', false);
            $response->assertDontSee('demo', false);
            $response->assertDontSee('white-label', false);
            $response->assertDontSee('mock', false);
            $response->assertDontSee('placeholder', false);
        }
    }

    public function test_public_pages_do_not_contain_banned_or_technical_phrases(): void
    {
        foreach (['/', '/flights/search', '/support', '/about-us', '/agent/register', '/flights/results?from=LHE&to=DXB&depart=2026-06-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0'] as $path) {
            $response = $this->get($path)->assertOk();
            $response->assertDontSee('demo', false);
            $response->assertDontSee('white-label', false);
            $response->assertDontSee('sample data', false);
            $response->assertDontSee('provider readiness', false);
            $response->assertDontSee('provider capabilities', false);
            $response->assertDontSee('inventory preview', false);
            $response->assertDontSee('API-ready supplier', false);
        }

        foreach (['/', '/flights/search', '/support', '/about-us', '/agent/register'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('mock', false);
        }
    }

    public function test_flight_search_page_uses_standardized_heading_copy(): void
    {
        $brand = $this->configuredBrandName();

        $this->get('/flights/search')
            ->assertOk()
            ->assertSee('Book your next flight', false)
            ->assertSee('ota-flight-search', false)
            ->assertSee('Search routes, compare fares, and continue to booking review with '.$brand.' support.', false);
    }

    public function test_flight_results_and_review_use_consistent_cta_labels(): void
    {
        $depart = '2026-06-20';
        $searchOffer = PublicCheckoutTestDoubles::searchOfferPayload($depart);
        $this->mock(FlightSearchService::class, function ($mock) use ($searchOffer): void {
            $mock->shouldReceive('searchWithMeta')->andReturn(['offers' => [$searchOffer], 'warnings' => []]);
            $mock->shouldReceive('search')->andReturn([$searchOffer]);
        });

        $this->get('/flights/results?from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('Book Now', false);

        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, $depart);
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'cta.user@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $this->get('/booking/review')
            ->assertOk()
            ->assertSee('Continue to Step 5 (Confirm/payment)', false);
    }

    public function test_customer_register_page_support_text_is_not_duplicated(): void
    {
        $response = $this->get('/register')->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Need Help?'));
    }

    public function test_agent_landing_page_shows_card_timeline_workflow(): void
    {
        $this->get('/agent/register')
            ->assertOk()
            ->assertSee('How it works', false)
            ->assertSee('1. Submit application', false)
            ->assertSee('2. Admin review', false)
            ->assertSee('3. Receive activation link', false)
            ->assertSee('4. Start booking', false);
    }
}
