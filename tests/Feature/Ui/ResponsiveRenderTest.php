<?php

namespace Tests\Feature\Ui;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

/**
 * Smoke render checks after responsive CSS/Blade changes (not visual regression).
 */
class ResponsiveRenderTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_guest_public_pages_render(): void
    {
        foreach ([
            '/',
            '/login',
            '/register',
            '/lookup-booking',
            '/support',
            '/about-us',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_customer_portal_pages_render(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $customer = User::query()->where('email', 'customer@ota.demo')->first();
        if ($customer === null) {
            $customer = User::factory()->create([
                'account_type' => AccountType::Customer,
                'current_agency_id' => null,
            ]);
        }

        $this->actingAs($customer);

        foreach ([
            '/customer',
            '/customer/bookings',
            '/customer/travelers',
            '/customer/support/tickets',
            '/profile',
        ] as $path) {
            $this->get($path)->assertOk();
        }

        $this->get('/customer/support')->assertRedirect(route('customer.support.tickets.index'));
    }

    public function test_guest_booking_flow_pages_render_with_checkout_fixture(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $passengersUrl = '/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&from=LHE&to=DXB&depart='.$depart;

        $this->get($passengersUrl)->assertOk();
        $this->get('/lookup-booking')->assertOk();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'responsive.render@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $this->get(route('booking.review'))->assertOk();
        $this->get(route('booking.confirmation'))->assertOk();
    }

    public function test_agent_portal_pages_render_for_admin_and_staff(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $admin = $scenario['adminA'];
        $staffWithBookings = $scenario['staff']['A1'];

        $adminPaths = [
            route('agent.dashboard'),
            route('agent.bookings.index'),
            route('agent.bookings.create'),
            route('agent.wallet.show'),
            route('agent.ledger.index'),
            route('agent.deposits.index'),
            route('agent.agency.show'),
            route('agent.agency.edit'),
            route('agent.staff.index'),
            route('agent.support.tickets.index'),
            route('agent.travelers.index'),
            route('profile.edit'),
        ];

        $this->actingAs($admin);
        foreach ($adminPaths as $path) {
            $this->get($path)->assertOk();
        }

        $this->actingAs($staffWithBookings);
        foreach ([
            route('agent.dashboard'),
            route('agent.bookings.index'),
            route('profile.edit'),
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_admin_portal_pages_render(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
        }
        $this->actingAs($admin->fresh());

        foreach ([
            '/admin',
            '/admin/bookings',
            '/admin/agents',
            '/admin/staff',
            '/admin/users',
            '/admin/customers',
            '/admin/markups',
            '/admin/commissions',
            '/admin/reports',
            '/admin/api-settings',
            '/admin/settings',
            '/admin/settings/payments',
            '/admin/support/tickets',
            '/admin/agent-deposits',
            '/profile',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_staff_portal_pages_render(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff);

        foreach ([
            '/staff',
            '/staff/bookings',
            '/staff/support/tickets',
            '/profile',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }
}
