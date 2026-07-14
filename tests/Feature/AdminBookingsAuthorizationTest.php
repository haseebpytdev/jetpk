<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_admin_can_access_admin_bookings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/admin/bookings')
            ->assertOk()
            ->assertSee('data-bookings-page', false)
            ->assertSee('data-bookings-kpis', false)
            ->assertSee('data-bookings-tabs', false)
            ->assertSee('data-bookings-filter-bar', false)
            ->assertSee('data-bookings-list', false)
            ->assertSee('data-bookings-preview', false);
        $this->getJson('/admin/bookings/data')->assertOk()->assertJsonStructure(['rows', 'pagination']);
        $this->getJson('/admin/bookings/suggestions?q=OT')->assertOk()->assertJsonStructure(['suggestions']);
    }

    public function test_staff_cannot_access_admin_bookings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff);

        $this->get('/admin/bookings')->assertForbidden();
        $this->get('/admin/bookings/data')->assertForbidden();
        $this->get('/admin/bookings/suggestions?q=OT')->assertForbidden();
    }

    public function test_agent_cannot_access_admin_bookings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $this->actingAs($agent);

        $this->get('/admin/bookings')->assertForbidden();
    }

    public function test_agency_admin_can_access_admin_agents_section(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/admin/agents')->assertOk();
    }

    public function test_guest_redirected_from_admin_bookings(): void
    {
        $this->get('/admin/bookings')->assertRedirect(route('login'));
    }

    public function test_public_homepage_still_accessible(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_agency_admin_sees_only_own_agency_bookings(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $aurora = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $other = Agency::query()->create([
            'name' => 'Other Travel',
            'slug' => 'other-travel-'.uniqid(),
            'timezone' => 'UTC',
        ]);

        Booking::factory()->for($aurora)->create([
            'booking_reference' => 'OTA-AURORA-ONLY',
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'route' => 'LHE → DXB',
        ]);
        Booking::factory()->for($other)->create([
            'booking_reference' => 'OTA-OTHER-ONLY',
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'route' => 'KHI → DXB',
        ]);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/admin/bookings')->assertOk()->assertSee('OTA-AURORA-ONLY', false)->assertDontSee('OTA-OTHER-ONLY', false);
    }

    public function test_agency_admin_cannot_preview_other_agency_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $aurora = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $other = Agency::query()->create([
            'name' => 'Other Travel',
            'slug' => 'other-travel-'.uniqid(),
            'timezone' => 'UTC',
        ]);

        $foreign = Booking::factory()->for($other)->create([
            'booking_reference' => 'OTA-FOREIGN',
            'status' => BookingStatus::Pending,
        ]);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/admin/bookings?preview=OTA-FOREIGN')->assertForbidden();
        $this->get('/admin/bookings?preview='.$foreign->id)->assertForbidden();
    }

    public function test_platform_admin_can_see_bookings_from_multiple_agencies(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $aurora = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $other = Agency::query()->create([
            'name' => 'Other Travel',
            'slug' => 'other-travel-'.uniqid(),
            'timezone' => 'UTC',
        ]);

        Booking::factory()->for($aurora)->create([
            'booking_reference' => 'OTA-PLAT-A',
            'status' => BookingStatus::Pending,
        ]);
        Booking::factory()->for($other)->create([
            'booking_reference' => 'OTA-PLAT-B',
            'status' => BookingStatus::Pending,
        ]);

        $platform = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $this->actingAs($platform);

        $this->get('/admin/bookings')->assertOk()->assertSee('OTA-PLAT-A', false)->assertSee('OTA-PLAT-B', false);
    }
}
