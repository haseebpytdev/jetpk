<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Http\Controllers\Staff\BookingController as StaffBookingController;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BookingOperationsLayerTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function createAgencyBooking(User $actor, array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->for($agency)->create(array_merge([
            'booking_reference' => 'OTA-TEST-'.uniqid(),
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'route' => 'LHE → DXB',
        ], $overrides));
    }

    public function test_platform_admin_can_filter_bookings_by_status(): void
    {
        $admin = $this->platformAdmin();
        $this->createAgencyBooking($admin, ['status' => BookingStatus::Pending, 'booking_reference' => 'OTA-F-PEND']);
        $this->createAgencyBooking($admin, ['status' => BookingStatus::Confirmed, 'booking_reference' => 'OTA-F-CONF']);

        $this->actingAs($admin)->get('/admin/bookings?status=pending')
            ->assertRedirect();

        $refs = collect($this->actingAs($admin)
            ->getJson(route('admin.bookings.data', ['status' => 'pending']))
            ->assertOk()
            ->json('rows'))
            ->pluck('booking_ref')
            ->all();

        $this->assertContains('OTA-F-PEND', $refs);
        $this->assertNotContains('OTA-F-CONF', $refs);
    }

    public function test_platform_admin_can_search_bookings_by_reference(): void
    {
        $admin = $this->platformAdmin();
        $this->createAgencyBooking($admin, ['booking_reference' => 'OTA-SEARCH-XYZ']);

        $this->actingAs($admin)->get('/admin/bookings?search=SEARCH-XYZ')
            ->assertRedirect();

        $refs = collect($this->actingAs($admin)
            ->getJson(route('admin.bookings.data', ['search' => 'SEARCH-XYZ']))
            ->assertOk()
            ->json('rows'))
            ->pluck('booking_ref')
            ->all();

        $this->assertContains('OTA-SEARCH-XYZ', $refs);
    }

    public function test_platform_admin_can_view_booking_detail_for_own_agency(): void
    {
        $admin = $this->platformAdmin();
        $booking = $this->createAgencyBooking($admin);

        $this->assertLegacyBookingShowRedirect($admin, $booking);
        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString($booking->booking_reference, $html);
    }

    public function test_agency_admin_policy_denies_other_agency_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $other = Agency::query()->create([
            'name' => 'Foreign Co',
            'slug' => 'foreign-'.uniqid(),
            'timezone' => 'UTC',
        ]);
        $foreign = Booking::factory()->for($other)->create([
            'booking_reference' => 'OTA-FOREIGN-DETAIL',
            'status' => BookingStatus::Pending,
        ]);

        $agencyAdmin = $this->legacyAgencyAdminFromSeed();
        $this->assertFalse(Gate::forUser($agencyAdmin)->allows('view', $foreign));

        $admin = $this->platformAdmin();
        $this->assertLegacyBookingShowRedirect($admin, $foreign);
        $html = $this->adminBookingShowHtml($admin, $foreign);
        $this->assertStringContainsString('OTA-FOREIGN-DETAIL', $html);
    }

    public function test_platform_admin_can_change_pending_to_confirmed(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = $this->createAgencyBooking($admin, ['status' => BookingStatus::Pending]);

        $this->actingAs($admin);
        $this->patch(route('admin.bookings.status', $booking), [
            'status' => BookingStatus::Confirmed->value,
            'note' => 'Confirmed by test',
        ])->assertRedirect();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_invalid_status_transition_is_rejected_for_staff(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = $this->createAgencyBooking($staff, ['status' => BookingStatus::Pending]);

        $this->actingAs($staff);
        $this->from('/staff/dashboard/bookings?id='.$booking->id);
        $this->patch(route('staff.bookings.status', $booking), [
            'status' => BookingStatus::Ticketed->value,
        ])->assertSessionHasErrors('status');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_status_change_creates_status_log_and_audit_with_old_new_values(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = $this->createAgencyBooking($admin, ['status' => BookingStatus::Pending]);

        $this->actingAs($admin);
        $this->patch(route('admin.bookings.status', $booking), [
            'status' => BookingStatus::Confirmed->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_status_logs', [
            'booking_id' => $booking->id,
            'to_status' => BookingStatus::Confirmed->value,
        ]);

        $audit = AuditLog::query()
            ->where('auditable_id', $booking->id)
            ->where('action', 'booking.status_changed')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertArrayHasKey('old_values', $audit->properties ?? []);
        $this->assertArrayHasKey('new_values', $audit->properties ?? []);
    }

    public function test_platform_admin_can_add_internal_note(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = $this->createAgencyBooking($admin);

        $this->actingAs($admin);
        $this->post(route('admin.bookings.notes', $booking), [
            'note' => 'Ops note from admin test',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_notes', [
            'booking_id' => $booking->id,
            'note' => 'Ops note from admin test',
        ]);
    }

    public function test_staff_can_add_note_to_own_agency_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = $this->createAgencyBooking($staff);

        $this->actingAs($staff);
        $this->post(route('staff.bookings.notes', $booking), [
            'note' => 'Staff visibility note',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_notes', [
            'booking_id' => $booking->id,
            'user_id' => $staff->id,
        ]);
    }

    public function test_agent_cannot_add_note_via_policy(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $booking = $this->createAgencyBooking($agent);

        $this->assertFalse(Gate::forUser($agent)->allows('addNote', $booking));
    }

    public function test_platform_admin_can_assign_staff(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $platformAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'email' => 'platform-admin-assign@ota.demo',
            'current_agency_id' => Agency::query()->where('slug', 'asif-travels')->firstOrFail()->id,
        ]);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = $this->createAgencyBooking($platformAdmin);

        $this->actingAs($platformAdmin);
        $this->patch(route('admin.bookings.assign-staff', $booking), [
            'staff_user_id' => $staff->id,
        ])->assertRedirect();

        $this->assertSame($staff->id, $booking->fresh()->assigned_staff_id);
        $this->assertNotNull($booking->fresh()->assigned_at);
    }

    public function test_assigning_staff_from_another_agency_is_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $platformAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'email' => 'platform-admin-reject@ota.demo',
            'current_agency_id' => Agency::query()->where('slug', 'asif-travels')->firstOrFail()->id,
        ]);
        $booking = $this->createAgencyBooking($platformAdmin);

        $otherAgency = Agency::query()->create([
            'name' => 'Remote',
            'slug' => 'remote-'.uniqid(),
            'timezone' => 'UTC',
        ]);
        $foreignStaff = User::factory()->staff()->create([
            'current_agency_id' => $otherAgency->id,
            'name' => 'Foreign Staff',
        ]);
        $foreignStaff->agencies()->attach($otherAgency->id, ['role' => 'staff']);

        $this->actingAs($platformAdmin);
        $this->from('/admin/dashboard/bookings?id='.$booking->id);
        $this->patch(route('admin.bookings.assign-staff', $booking), [
            'staff_user_id' => $foreignStaff->id,
        ])->assertSessionHasErrors('staff_user_id');
    }

    public function test_assigning_agency_admin_is_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $platformAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'email' => 'platform-admin-agency-reject@ota.demo',
            'current_agency_id' => Agency::query()->where('slug', 'asif-travels')->firstOrFail()->id,
        ]);
        $booking = $this->createAgencyBooking($platformAdmin);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agencyAdmin = User::factory()->agencyAdmin()->create([
            'current_agency_id' => $agency->id,
            'email' => 'agency-admin-assign-test@ota.demo',
        ]);
        $agencyAdmin->agencies()->attach($agency->id, ['role' => 'agency_admin']);

        $this->actingAs($platformAdmin);
        $this->from('/admin/dashboard/bookings?id='.$booking->id);
        $this->patch(route('admin.bookings.assign-staff', $booking), [
            'staff_user_id' => $agencyAdmin->id,
        ])->assertSessionHasErrors('staff_user_id');
    }

    public function test_staff_booking_show_renders_without_server_error(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = Booking::factory()->for(
            Agency::query()->where('slug', 'asif-travels')->firstOrFail()
        )->create(['status' => BookingStatus::Pending]);

        $response = $this->actingAs($staff)->get(route('staff.bookings.show', $booking));
        $response->assertRedirect();
        $this->assertStringContainsString('/staff/dashboard/bookings', (string) $response->headers->get('Location'));

        $html = $this->staffBookingShowHtml($staff, $booking);
        $this->assertStringContainsString('ota-booking-detail', $html);
    }

    public function test_staff_can_access_staff_bookings_index(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $response = $this->actingAs($staff)->get('/staff/bookings');
        $response->assertRedirect();
        $this->assertStringContainsString('/staff/dashboard/bookings', (string) $response->headers->get('Location'));

        $html = $this->staffBookingsIndexHtml($staff);
        $this->assertNotSame('', trim($html));
    }

    public function test_staff_cannot_access_other_agency_booking_detail(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $other = Agency::query()->create([
            'name' => 'Iso',
            'slug' => 'iso-'.uniqid(),
            'timezone' => 'UTC',
        ]);
        $foreign = Booking::factory()->for($other)->create(['status' => BookingStatus::Pending]);

        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff);
        $this->get(route('staff.bookings.show', $foreign))->assertForbidden();
    }

    protected function staffBookingShowHtml(User $staff, Booking $booking): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/bookings/'.$booking->id, 'GET');
        $request->setUserResolver(fn () => $staff);

        return app(StaffBookingController::class)->show($booking)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function staffBookingsIndexHtml(User $staff, array $query = []): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/bookings', 'GET', $query);
        $request->setUserResolver(fn () => $staff);

        return app(StaffBookingController::class)->index($request)->render();
    }
}
