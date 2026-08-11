<?php

namespace Tests\Feature\Ops;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupportTicketMessageVisibility;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Ops\OpsInboxService;
use App\Services\Support\SupportTicketService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JpOps08CrossPortalOpsInboxTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_admin_assign_staff_persists_inbox_and_work_queue(): void
    {
        [$admin, $staff, $booking] = $this->adminStaffBooking();

        app(BookingService::class)->assignStaff($booking, $staff, $admin);

        $staff->refresh();
        $inbox = app(OpsInboxService::class);
        $this->assertSame(1, $inbox->unreadCount($staff));

        $items = $inbox->listForUser($staff)['items'];
        $this->assertSame('booking.staff_assigned', $items[0]['event_type']);
        $this->assertSame($booking->booking_reference, $items[0]['entity_ref']);

        // Duplicate fan-out with same event_key must not double-count.
        app(BookingService::class)->assignStaff($booking->fresh(), $staff, $admin);
        $staff->refresh();
        $this->assertSame(1, $inbox->unreadCount($staff));

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.ops.work-queue'))
            ->assertOk()
            ->assertJsonFragment(['reference' => $booking->booking_reference]);

        $this->actingAs($staff)
            ->getJson(route('api.dashboard.ops.inbox'))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.unreadCount', 1);

        $notificationId = $items[0]['id'];
        $this->actingAs($staff)
            ->postJson(route('api.dashboard.ops.inbox.read'), ['ids' => [$notificationId]])
            ->assertOk()
            ->assertJsonPath('data.unreadCount', 0);

        $staff->refresh();
        $this->assertSame(0, $inbox->unreadCount($staff));
    }

    public function test_staff_internal_note_notifies_admin_activity_feed(): void
    {
        [$admin, $staff, $booking] = $this->adminStaffBooking();
        app(BookingService::class)->assignStaff($booking, $staff, $admin);

        app(BookingService::class)->addInternalNote($booking->fresh(), $staff, 'QA internal note JP-OPS-08', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'booking.note_added',
            'auditable_id' => $booking->id,
            'user_id' => $staff->id,
        ]);

        $admin->refresh();
        $this->assertGreaterThanOrEqual(1, app(OpsInboxService::class)->unreadCount($admin));

        $cursor = (int) AuditLog::query()->max('id');
        $this->actingAs($admin)
            ->getJson(route('api.dashboard.ops.events', ['since_id' => max(0, $cursor - 50)]))
            ->assertOk()
            ->assertJsonPath('data.transport', 'EVENT_POLLING');
    }

    public function test_customer_support_ticket_fans_out_and_two_way_inbox(): void
    {
        [$admin, $staff, $customer] = $this->adminStaffCustomer();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $tickets = app(SupportTicketService::class);
        $ticket = $tickets->createTicket($customer, $agency, [
            'subject' => 'JP-OPS-08 QA support',
            'category' => 'other',
            'body' => 'Customer message 1',
        ]);

        $admin->refresh();
        $this->assertGreaterThanOrEqual(1, app(OpsInboxService::class)->unreadCount($admin));

        $tickets->assign($ticket, $staff, $admin);
        $staff->refresh();
        $this->assertGreaterThanOrEqual(1, app(OpsInboxService::class)->unreadCount($staff));

        $tickets->reply($ticket->fresh(), $staff, 'Staff reply 1', SupportTicketMessageVisibility::CustomerVisible);
        $customer->refresh();
        $this->assertGreaterThanOrEqual(1, app(OpsInboxService::class)->unreadCount($customer));

        $beforeInternal = app(OpsInboxService::class)->unreadCount($customer);
        $tickets->reply($ticket->fresh(), $staff, 'Internal only', SupportTicketMessageVisibility::Internal);
        $customer->refresh();
        $this->assertSame($beforeInternal, app(OpsInboxService::class)->unreadCount($customer));

        $this->actingAs($customer)
            ->getJson(route('customer.notifications.index'))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('ok', true);
    }

    public function test_cross_role_rbac_denies_customer_ops_inbox_api(): void
    {
        [, , $customer] = $this->adminStaffCustomer();

        $this->actingAs($customer)
            ->getJson(route('api.dashboard.ops.inbox'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: Booking}
     */
    private function adminStaffBooking(): array
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($staff->id, ['role' => 'staff']);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'JP-OPS08-'.fake()->unique()->numberBetween(1000, 9999),
            'route' => 'LHE-KHI',
            'travel_date' => now()->addDays(10)->toDateString(),
        ]);

        return [$admin->fresh(), $staff, $booking];
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function adminStaffCustomer(): array
    {
        [$admin, $staff] = array_slice($this->adminStaffBooking(), 0, 2);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        return [$admin, $staff, $customer];
    }
}
