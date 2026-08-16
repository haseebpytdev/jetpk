<?php

namespace Tests\Feature;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Staff\SupportTicketController as StaffSupportTicketController;
use App\Models\Agency;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Dashboard\AgencyDashboardService;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ViewErrorBag;
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

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $alerts = app(AgencyDashboardService::class)->buildSupportAlerts($admin, 'admin');
        $keys = array_column($alerts, 'key');
        $this->assertContains('open', $keys);
        $this->assertContains('unassigned', $keys);
        $this->assertContains('public', $keys);
        $this->assertContains('recent', $keys);

        $testids = array_column($alerts, 'testid');
        $this->assertContains('ota-support-alert-open', $testids);
        $this->assertContains('ota-support-alert-unassigned', $testids);
        $this->assertContains('ota-support-alert-public', $testids);
        $this->assertContains('ota-support-alert-recent', $testids);

        $this->actingAs($admin)->get(route('admin.support.tickets.index', ['queue' => 'active']))
            ->assertRedirect('/admin/dashboard/support?queue=active');
        $this->actingAs($admin)->get(route('admin.support.tickets.index', ['recent' => 7]))
            ->assertRedirect('/admin/dashboard/support?recent=7');
    }

    public function test_staff_dashboard_shows_support_alert_cards(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->seedSupportScenario($agency, $staff);

        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();

        $alerts = app(AgencyDashboardService::class)->buildSupportAlerts($staff, 'staff');
        $testids = array_column($alerts, 'testid');
        $this->assertContains('staff-support-alert-open', $testids);
        $this->assertContains('staff-support-alert-assigned-to-me', $testids);
        $this->assertContains('staff-support-alert-unassigned', $testids);

        $params = collect($alerts)->pluck('route_params')->all();
        $flat = json_encode($params);
        $this->assertStringContainsString('assigned_to_me', (string) $flat);
        $this->assertStringContainsString('active', (string) $flat);

        $this->actingAs($staff)->get(route('staff.support.tickets.index', ['queue' => 'active']))
            ->assertRedirect('/staff/dashboard/support?queue=active');
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

        $staff = $staff->fresh();
        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();
        $this->assertSame([], app(AgencyDashboardService::class)->buildSupportAlerts($staff, 'staff'));
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
        ]))->assertRedirect('/admin/dashboard/support?queue=active&assigned=unassigned');

        $html = $this->adminSupportIndexHtml($admin, [
            'queue' => 'active',
            'assigned' => 'unassigned',
        ]);
        $this->assertStringContainsString('Unassigned active', $html);
        $this->assertStringContainsString('Public active', $html);
        $this->assertStringNotContainsString('Assigned active', $html);
        $this->assertStringNotContainsString('Closed ticket', $html);
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
        ]))->assertRedirect('/staff/dashboard/support?queue=active&assigned_to_me=1');

        $html = $this->staffSupportIndexHtml($staff, [
            'queue' => 'active',
            'assigned_to_me' => 1,
        ]);
        $this->assertStringContainsString('Assigned active', $html);
        $this->assertStringNotContainsString('Unassigned active', $html);
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
        ]))->assertRedirect('/admin/dashboard/support?queue=active&source=public');

        $publicHtml = $this->adminSupportIndexHtml($admin, [
            'queue' => 'active',
            'source' => 'public',
        ]);
        $this->assertStringContainsString('Public active', $publicHtml);
        $this->assertStringNotContainsString('Unassigned active', $publicHtml);

        $recentHtml = $this->adminSupportIndexHtml($admin, ['recent' => 7]);
        $this->assertStringContainsString('Public active', $recentHtml);
        $this->assertStringContainsString('Unassigned active', $recentHtml);
        $this->assertStringNotContainsString('Old active', $recentHtml);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminSupportIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        ViewFacade::share('errors', new ViewErrorBag);
        $request = Request::create('/admin/support/tickets', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AdminSupportTicketController::class)->index($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function staffSupportIndexHtml(User $staff, array $query = []): string
    {
        $this->actingAs($staff);
        ViewFacade::share('errors', new ViewErrorBag);
        $request = Request::create('/staff/support/tickets', 'GET', $query);
        $request->setUserResolver(fn () => $staff);

        return app(StaffSupportTicketController::class)->index($request)->render();
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
