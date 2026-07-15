<?php

namespace Tests\Feature;

use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class SupportDashboardAlertsTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_admin_dashboard_shows_support_alert_cards_with_counts(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->seedSupportScenario($agency, $staff);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="ota-support-alerts"', false)
            ->assertSee('data-testid="ota-support-alert-open"', false)
            ->assertSee('data-testid="ota-support-alert-unassigned"', false)
            ->assertSee('data-testid="ota-support-alert-public"', false)
            ->assertSee('data-testid="ota-support-alert-recent"', false)
            ->assertSee('Support alerts', false)
            ->assertSee(route('admin.support.tickets.index', ['queue' => 'active']), false)
            ->assertSee(route('admin.support.tickets.index', ['recent' => 7]), false);
    }

    public function test_staff_dashboard_shows_support_alert_cards(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->seedSupportScenario($agency, $staff);

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="staff-support-alerts"', false)
            ->assertSee('data-testid="staff-support-alert-open"', false)
            ->assertSee('data-testid="staff-support-alert-assigned-to-me"', false)
            ->assertSee('data-testid="staff-support-alert-unassigned"', false)
            ->assertSee('assigned_to_me=1', false)
            ->assertSee('queue=active', false);
    }

    public function test_staff_without_support_view_permission_sees_no_support_alerts(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [StaffPermission::BookingsView]],
        ])->save();

        $this->seedSupportScenario($agency, $staff);

        $this->actingAs($staff->fresh())->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="staff-support-alerts"', false)
            ->assertDontSee('Support alerts', false);
    }

    public function test_admin_support_index_filters_unassigned_active_tickets(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->seedSupportScenario($agency, $staff);

        $this->actingAs($admin)->get(route('admin.support.tickets.index', [
            'queue' => 'active',
            'assigned' => 'unassigned',
        ]))
            ->assertOk()
            ->assertSee('Unassigned active', false)
            ->assertSee('Public active', false)
            ->assertDontSee('Assigned active', false)
            ->assertDontSee('Closed ticket', false);
    }

    public function test_staff_support_index_filters_assigned_to_me(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->seedSupportScenario($agency, $staff);

        $this->actingAs($staff)->get(route('staff.support.tickets.index', [
            'queue' => 'active',
            'assigned_to_me' => 1,
        ]))
            ->assertOk()
            ->assertSee('Assigned active', false)
            ->assertDontSee('Unassigned active', false);
    }

    public function test_admin_support_index_filters_public_and_recent(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->seedSupportScenario($agency, $staff);

        $this->actingAs($admin)->get(route('admin.support.tickets.index', [
            'queue' => 'active',
            'source' => 'public',
        ]))
            ->assertOk()
            ->assertSee('Public active', false)
            ->assertDontSee('Unassigned active', false);

        $this->actingAs($admin)->get(route('admin.support.tickets.index', ['recent' => 7]))
            ->assertOk()
            ->assertSee('Public active', false)
            ->assertSee('Unassigned active', false)
            ->assertDontSee('Old active', false);
    }

    protected function seedSupportScenario(Agency $agency, User $staff): void
    {
        SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'ticket_reference' => 'SRV-UNASSIGNED-ACTIVE',
            'source' => 'customer',
            'subject' => 'Unassigned active',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'assigned_to_user_id' => null,
            'last_reply_at' => now(),
        ]);

        SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'ticket_reference' => 'SRV-PUBLIC-ACTIVE',
            'source' => 'public',
            'requester_name' => 'Guest',
            'requester_email' => 'guest@test.example',
            'subject' => 'Public active',
            'category' => 'other',
            'status' => SupportTicketStatus::Pending,
            'assigned_to_user_id' => null,
            'last_reply_at' => now(),
        ]);

        SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'ticket_reference' => 'SRV-ASSIGNED-ACTIVE',
            'source' => 'customer',
            'subject' => 'Assigned active',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'assigned_to_user_id' => $staff->id,
            'last_reply_at' => now(),
        ]);

        SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'ticket_reference' => 'SRV-CLOSED',
            'source' => 'customer',
            'subject' => 'Closed ticket',
            'category' => 'other',
            'status' => SupportTicketStatus::Closed,
            'assigned_to_user_id' => null,
            'last_reply_at' => now()->subDay(),
            'closed_at' => now(),
        ]);

        $old = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'ticket_reference' => 'SRV-OLD-ACTIVE',
            'source' => 'customer',
            'subject' => 'Old active',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'assigned_to_user_id' => null,
            'last_reply_at' => now()->subDays(10),
        ]);
        $old->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->saveQuietly();
    }
}
