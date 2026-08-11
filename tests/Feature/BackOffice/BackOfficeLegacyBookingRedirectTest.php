<?php

namespace Tests\Feature\BackOffice;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BackOfficeLegacyBookingRedirectTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_legacy_admin_bookings_index_redirects_to_next_dashboard(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/bookings?status=pending')
            ->assertRedirect('/admin/dashboard/bookings?status=pending');
    }

    public function test_legacy_admin_bookings_preview_query_maps_to_dashboard_search(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        Booking::factory()->for($agency)->create([
            'booking_reference' => 'OTA-REDIRECT-01',
            'status' => BookingStatus::Pending,
        ]);

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/bookings?preview=OTA-REDIRECT-01')
            ->assertRedirect('/admin/dashboard/bookings?q=OTA-REDIRECT-01');
    }

    public function test_legacy_admin_booking_show_redirects_to_next_detail(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'booking_reference' => 'OTA-SHOW-REDIRECT',
            'status' => BookingStatus::Pending,
        ]);

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertRedirect('/admin/dashboard/bookings/OTA-SHOW-REDIRECT');
    }

    public function test_legacy_staff_bookings_index_redirects_to_staff_dashboard(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->get('/staff/bookings')
            ->assertRedirect('/staff/dashboard/bookings');
    }

    public function test_staff_cannot_preview_foreign_booking_via_legacy_route(): void
    {
        $other = Agency::query()->create([
            'name' => 'Other Travel',
            'slug' => 'other-travel-'.uniqid(),
            'timezone' => 'UTC',
        ]);

        Booking::factory()->for($other)->create([
            'booking_reference' => 'OTA-FOREIGN-REDIRECT',
            'status' => BookingStatus::Pending,
        ]);

        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff);

        $this->get('/staff/bookings?preview=OTA-FOREIGN-REDIRECT')->assertForbidden();
    }

    public function test_staff_cannot_access_admin_bookings(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff);

        $this->get('/admin/bookings')->assertForbidden();
    }

    public function test_platform_admin_can_access_legacy_bookings_redirect(): void
    {
        $platform = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($platform)
            ->get('/admin/bookings')
            ->assertRedirect('/admin/dashboard/bookings');
    }
}
