<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BookingOperationsLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function createAgencyBooking(User $admin, array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->for($agency)->create(array_merge([
            'booking_reference' => 'OTA-TEST-'.uniqid(),
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'route' => 'LHE → DXB',
        ], $overrides));
    }

    public function test_agency_admin_can_filter_bookings_by_status(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->createAgencyBooking($admin, ['status' => BookingStatus::Pending, 'booking_reference' => 'OTA-F-PEND']);
        $this->createAgencyBooking($admin, ['status' => BookingStatus::Confirmed, 'booking_reference' => 'OTA-F-CONF']);

        $this->actingAs($admin);
        $this->get('/admin/bookings?status=pending')->assertOk()->assertSee('OTA-F-PEND', false)->assertDontSee('OTA-F-CONF', false);
    }

    public function test_agency_admin_can_search_bookings_by_reference(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->createAgencyBooking($admin, ['booking_reference' => 'OTA-SEARCH-XYZ']);

        $this->actingAs($admin);
        $this->get('/admin/bookings?search=SEARCH-XYZ')->assertOk()->assertSee('OTA-SEARCH-XYZ', false);
    }

    public function test_agency_admin_can_view_booking_detail_for_own_agency(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = $this->createAgencyBooking($admin);

        $this->actingAs($admin);
        $this->get(route('admin.bookings.show', $booking))->assertOk()->assertSee($booking->booking_reference, false);
    }

    public function test_agency_admin_cannot_view_other_agency_booking(): void
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

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);
        $this->get(route('admin.bookings.show', $foreign))->assertForbidden();
    }

    public function test_agency_admin_can_change_pending_to_confirmed(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = $this->createAgencyBooking($admin, ['status' => BookingStatus::Pending]);

        $this->actingAs($admin);
        $this->patch(route('admin.bookings.status', $booking), [
            'status' => BookingStatus::Confirmed->value,
            'note' => 'Confirmed by test',
        ])->assertRedirect();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = $this->createAgencyBooking($admin, ['status' => BookingStatus::Pending]);

        $this->actingAs($admin);
        $this->from(route('admin.bookings.show', $booking));
        $this->patch(route('admin.bookings.status', $booking), [
            'status' => BookingStatus::Ticketed->value,
        ])->assertSessionHasErrors('status');
    }

    public function test_status_change_creates_status_log_and_audit_with_old_new_values(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
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

    public function test_agency_admin_can_add_internal_note(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
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
        $this->from(route('admin.bookings.show', $booking));
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
        $this->from(route('admin.bookings.show', $booking));
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

        $this->actingAs($staff)->get(route('staff.bookings.show', $booking))
            ->assertOk()
            ->assertSee('ota-booking-detail', false);
    }

    public function test_staff_can_access_staff_bookings_index(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->actingAs($staff);
        $this->get('/staff/bookings')->assertOk();
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
}
