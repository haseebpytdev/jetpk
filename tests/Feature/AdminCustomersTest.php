<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_admin_can_view_customer_list(): void
    {
        [$admin] = $this->agencyAdmin();

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Customer list', false);
    }

    public function test_customer_list_only_shows_account_type_customer(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$admin] = $this->agencyAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        User::factory()->create([
            'name' => 'Staff Only Person',
            'email' => 'staffonly@demo.test',
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Asif Customer')
            ->assertDontSee('Staff Only Person');
    }

    public function test_agency_admin_can_view_customer_profile(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$admin] = $this->agencyAdmin();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Asif Customer')
            ->assertSee($customer->email);
    }

    public function test_customer_profile_shows_seeded_bookings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$admin] = $this->agencyAdmin();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_reference' => 'CUST-TEST-001',
            'route' => 'LHE → DXB',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'bookings']))
            ->assertOk()
            ->assertSee('CUST-TEST-001')
            ->assertSee('LHE → DXB');
    }

    public function test_non_customer_id_cannot_be_shown_through_customer_show_route(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$admin] = $this->agencyAdmin();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.customers.show', ['customer' => $staff]))
            ->assertNotFound();
    }

    public function test_guest_booking_appears_in_guest_customers_segment(): void
    {
        [$admin] = $this->agencyAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'customer_id' => null,
            'booking_reference' => 'GUEST-001',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);
        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Usman',
            'last_name' => 'Guest',
            'is_lead_passenger' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['segment' => 'guests']))
            ->assertOk()
            ->assertSee('GU-'.$booking->id.'-Usman', false)
            ->assertSee('guest@example.test', false);
    }

    public function test_registered_customer_does_not_appear_in_guest_customers_segment(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$admin] = $this->agencyAdmin();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        Booking::factory()->for($agency)->create([
            'customer_id' => $customer->id,
            'booking_reference' => 'REG-001',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['segment' => 'guests']))
            ->assertOk()
            ->assertDontSee('REG-001', false);
    }

    public function test_registered_customers_segment_excludes_guest_bookings(): void
    {
        [$admin] = $this->agencyAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $guestBooking = Booking::factory()->for($agency)->create([
            'customer_id' => null,
            'booking_reference' => 'GUEST-ONLY-001',
        ]);
        BookingContact::query()->create([
            'booking_id' => $guestBooking->id,
            'email' => 'guestonly@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertDontSee('guestonly@example.test', false);
    }

    public function test_staff_cannot_access_admin_customer_routes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.customers.show', $customer))->assertForbidden();
    }

    /**
     * @return array{0: User}
     */
    protected function agencyAdmin(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill([
                'account_type' => AccountType::PlatformAdmin,
                'current_agency_id' => null,
            ])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
