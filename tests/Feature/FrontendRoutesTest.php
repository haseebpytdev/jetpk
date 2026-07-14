<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class FrontendRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_responds(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Subject to airline confirmation.', false);
    }

    /** @see README — client demo navigation checklist */
    public function test_client_demo_navigation_primary_paths_respond(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        foreach ([
            '/',
            '/request-demo',
            '/flights/search',
            '/flights/results?from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0',
            '/booking/passengers?flight_id=fixture-offer-1&from=LHE&to=DXB&depart='.$depart,
        ] as $path) {
            $this->get($path)->assertOk();
        }

        $this->get('/booking/confirmation')->assertRedirect(route('flights.search'));

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        foreach ([
            '/admin',
            '/admin/bookings',
            '/admin/agents',
            '/admin/staff',
            '/admin/markups',
            '/admin/api-settings',
            '/admin/roles-permissions',
            '/admin/reports',
            '/admin/settings/branding',
            '/admin/go-live-checklist',
        ] as $path) {
            $this->get($path)->assertOk();
        }

        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff);
        $this->get('/staff')->assertOk();

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $this->actingAs($agent);
        $this->get('/agent')->assertOk();

        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => null,
        ]);
        $this->actingAs($customer);
        $this->get('/customer')->assertOk();

        $this->get('/flights/details/fixture-offer-1?from=LHE&to=DXB&depart='.$depart)->assertOk();
    }

    public function test_flight_search_and_results_flow(): void
    {
        $this->get('/flights/search')->assertOk();

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'NYC', 'LON');

        $this->get('/flights/results?from=NYC&to=LON&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();

        $this->get('/flights/details/fixture-offer-1?from=NYC&to=LON&depart='.$depart)
            ->assertOk();
    }

    public function test_dashboard_routes_respond(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);
        $this->get('/admin')->assertOk();

        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff);
        $this->get('/staff')->assertOk();

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $this->actingAs($agent);
        $this->get('/agent')->assertOk();

        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($customer);
        $this->get('/customer')->assertOk();
    }

    public function test_admin_section_routes_respond(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        foreach ([
            '/admin/bookings',
            '/admin/agents',
            '/admin/staff',
            '/admin/markups',
            '/admin/api-settings',
            '/admin/reports',
            '/admin/roles-permissions',
            '/admin/settings/branding',
            '/admin/go-live-checklist',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_admin_preview_query_routes_respond(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        Booking::factory()->for($agency)->create([
            'booking_reference' => 'OTA-99214',
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'route' => 'LHE → DXB',
            'airline' => 'Demo',
            'supplier' => 'duffel',
            'source_channel' => 'test',
        ]);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/admin/bookings?preview=OTA-99214')->assertOk()->assertSee('OTA-99214', false);
        $agent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => User::factory()->create(['current_agency_id' => $agency->id, 'account_type' => AccountType::Agent])->id,
            'code' => 'AGT-9921',
        ]);
        $staffUser = User::factory()->create(['current_agency_id' => $agency->id, 'account_type' => AccountType::Staff]);
        $staff = StaffProfile::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $staffUser->id,
        ]);

        $this->get('/admin/agents?preview='.$agent->id)->assertOk()->assertSee('AGT-9921', false);
        $this->get('/admin/staff?preview='.$staff->id)->assertOk()->assertSee('STF-'.$staff->id, false);
    }

    public function test_booking_passengers_post_redirects_to_review(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'test@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));
    }

    public function test_booking_review_renders_after_passenger_step(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'test@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $this->get(route('booking.review'))
            ->assertOk()
            ->assertSee('Review your booking', false);
    }

    public function test_booking_review_post_redirects_to_confirmation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'test@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $this->post('/booking/review', [
            'booking_method' => 'bank_transfer',
        ])->assertRedirect(route('booking.confirmation'));
    }

    public function test_public_route_aliases_redirect_to_canonical_paths(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->get('/password/forgot')
            ->assertRedirect('/forgot-password');

        $this->get('/booking-lookup')
            ->assertRedirect('/lookup-booking');

        $this->get('/flights')
            ->assertRedirect('/');
    }
}
