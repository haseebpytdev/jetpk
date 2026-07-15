<?php

namespace Tests\Feature;

use App\Data\TicketingResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\TicketingAttempt;
use App\Models\User;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Suppliers\TicketingAdapters\DuffelSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class TicketingReadinessHardeningTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_ticketing_module_off_blocks_admin_issue_ticket_post(): void
    {
        $this->planModuleOff('ticketing');
        [$booking, $admin] = $this->eligibleDuffelBooking(withSupplierBooking: true);

        $this->mock(DuffelSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->never();
        });

        $this->actingAs($admin)
            ->post(route('admin.bookings.issue-ticket', $booking))
            ->assertForbidden();

        $this->assertSame(0, TicketingAttempt::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }

    public function test_ticketing_module_off_blocks_staff_issue_ticket_post(): void
    {
        $this->planModuleOff('ticketing');
        [$booking] = $this->eligibleDuffelBooking(withSupplierBooking: true);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('staff.bookings.issue-ticket', $booking))
            ->assertForbidden();

        $this->assertSame(0, TicketingAttempt::query()->where('booking_id', $booking->id)->count());
    }

    public function test_sabre_ticketing_env_false_blocks_ticketing_service_when_module_on(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', false);
        [$booking, $admin] = $this->eligibleSabreBooking(withSupplierBooking: true);

        $result = app(TicketingService::class)->issueTickets($booking->fresh(['latestSupplierBooking']), $admin);

        $this->assertFalse($result->success);
        $this->assertSame('ticketing_disabled_by_config', $result->error_code);
        $this->assertSame('Sabre ticketing is disabled in environment settings.', $result->error_message);
    }

    public function test_staff_without_ticketing_issue_cannot_issue_ticket(): void
    {
        [$booking] = $this->eligibleDuffelBooking(withSupplierBooking: true);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['meta' => ['staff_permissions' => [StaffPermission::BookingsView]]])->save();

        $this->actingAs($staff->fresh())
            ->post(route('staff.bookings.issue-ticket', $booking))
            ->assertForbidden();
    }

    public function test_staff_with_ticketing_issue_reaches_ticketing_service_layer(): void
    {
        [$booking] = $this->eligibleDuffelBooking(withSupplierBooking: true);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $this->bindSuccessfulDuffelTicketingStub();

        $this->actingAs($staff)
            ->post(route('staff.bookings.issue-ticket', $booking))
            ->assertRedirect();

        $this->assertDatabaseHas('ticketing_attempts', [
            'booking_id' => $booking->id,
            'status' => 'success',
        ]);
    }

    public function test_booking_without_pnr_cannot_issue_ticket(): void
    {
        [$booking, $admin] = $this->eligibleDuffelBooking(withSupplierBooking: true);
        $booking->update(['pnr' => null, 'supplier_reference' => null]);
        SupplierBooking::query()->where('booking_id', $booking->id)->delete();

        $this->actingAs($admin)
            ->post(route('admin.bookings.issue-ticket', $booking->fresh()))
            ->assertSessionHasErrors('ticketing');
    }

    public function test_already_ticketed_booking_cannot_issue_ticket_again(): void
    {
        [$booking, $admin] = $this->eligibleDuffelBooking(withSupplierBooking: true);
        $this->bindSuccessfulDuffelTicketingStub();

        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.bookings.issue-ticket', $booking->fresh()))
            ->assertSessionHasErrors('ticketing');

        $this->assertSame(1, TicketingAttempt::query()->where('booking_id', $booking->id)->count());
    }

    public function test_duffel_provider_module_off_blocks_ticketing_service(): void
    {
        $this->planModuleOff('duffel_supplier');
        [$booking, $admin] = $this->eligibleDuffelBooking(withSupplierBooking: true);

        $result = app(TicketingService::class)->issueTickets($booking->fresh(['latestSupplierBooking']), $admin);

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
    }

    public function test_admin_booking_page_shows_disabled_reason_when_ticketing_module_off(): void
    {
        $this->planModuleOff('ticketing');
        [$booking, $admin] = $this->eligibleSabreBooking(withSupplierBooking: true);
        $booking->update(['payment_status' => 'paid']);

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', ['booking' => $booking, 'tab' => 'ticketing']))
            ->assertOk()
            ->assertSee('Ticketing is disabled for this deployment.', false)
            ->assertSee('disabled', false)
            ->assertDontSee('btn btn-primary w-100 mb-2">Issue ticket', false);
    }

    public function test_admin_booking_page_shows_pnr_required_before_ticketing(): void
    {
        [$booking, $admin] = $this->eligibleSabreBooking(withSupplierBooking: false);

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', ['booking' => $booking, 'tab' => 'ticketing']))
            ->assertOk()
            ->assertSee('Create or attach PNR before ticketing.', false);
    }

    public function test_issue_ticket_cta_not_active_when_sabre_env_disabled(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', false);
        [$booking, $admin] = $this->eligibleSabreBooking(withSupplierBooking: true);
        $booking->update(['payment_status' => 'paid', 'status' => BookingStatus::Paid]);

        $state = app(AdminBookingSupplierActions::class)
            ->build($booking->fresh(['passengers', 'latestSupplierBooking']), false, true);

        $this->assertFalse($state['can_issue_ticket_live']);
        $this->assertStringContainsString(
            'sabre ticketing is disabled in environment settings',
            strtolower((string) $state['issue_ticket_disabled_reason']),
        );

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', ['booking' => $booking, 'tab' => 'ticketing']))
            ->assertOk()
            ->assertSee('Ticketing readiness checklist', false);
    }

    public function test_ticketing_service_never_calls_live_sabre_http(): void
    {
        Http::fake();
        [$booking, $admin] = $this->eligibleSabreBooking(withSupplierBooking: true);

        app(TicketingService::class)->issueTickets($booking->fresh(['latestSupplierBooking']), $admin);

        Http::assertNothingSent();
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function eligibleDuffelBooking(bool $withSupplierBooking = false): array
    {
        $admin = $this->platformAdmin();
        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $connection->id,
            ],
        ]);

        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);

        if ($withSupplierBooking) {
            $booking->update([
                'pnr' => 'PNR9D4',
                'supplier_reference' => 'REF9D4',
                'supplier_booking_status' => 'pending_ticketing',
            ]);
            SupplierBooking::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => SupplierProvider::Duffel->value,
                'supplier_reference' => 'REF9D4',
                'pnr' => 'PNR9D4',
                'status' => 'pending_ticketing',
            ]);
        }

        return [$booking->fresh(), $admin];
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function eligibleSabreBooking(bool $withSupplierBooking = false): array
    {
        $admin = $this->platformAdmin();
        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
            ],
        ]);

        if ($withSupplierBooking) {
            $booking->update([
                'pnr' => 'SBR9D4',
                'supplier_reference' => 'SREF9D4',
                'supplier_booking_status' => 'pending_ticketing',
            ]);
            SupplierBooking::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => SupplierProvider::Sabre->value,
                'supplier_reference' => 'SREF9D4',
                'pnr' => 'SBR9D4',
                'status' => 'pending_ticketing',
            ]);
        }

        return [$booking->fresh(), $admin];
    }

    protected function bindSuccessfulDuffelTicketingStub(): void
    {
        $this->mock(DuffelSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->andReturn(new TicketingResultData(
                success: true,
                status: 'issued',
                provider: SupplierProvider::Duffel->value,
                tickets: [['passenger_id' => 1, 'ticket_number' => 'TKT1', 'pnr' => 'PNR9D4']],
                safe_summary: ['stub' => true],
            ));
        });
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }
}
