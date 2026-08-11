<?php

namespace Tests\Feature\Ops;

use App\Enums\AccountType;
use App\Enums\SupportTicketMessageVisibility;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Ops\OpsInboxService;
use App\Services\Support\SupportTicketService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Orchestrated multi-role business simulation at the domain layer
 * (authoritative path used by portals; browser multi-context covers presentation separately).
 */
class JpOps08FullBusinessSimulationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_support_loop_customer_admin_staff_with_ordering_and_unread(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
        ]);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        $tickets = app(SupportTicketService::class);
        $inbox = app(OpsInboxService::class);

        $t0 = microtime(true);
        $ticket = $tickets->createTicket($customer, $agency, [
            'subject' => 'JP-OPS-08 full sim',
            'category' => 'other',
            'body' => 'Customer message 1',
        ]);
        $admin->refresh();
        $this->assertGreaterThanOrEqual(1, $inbox->unreadCount($admin));
        $latencyCreateMs = (int) round((microtime(true) - $t0) * 1000);
        $this->assertLessThanOrEqual(5000, $latencyCreateMs);

        $tickets->assign($ticket, $staff, $admin);
        $staff->refresh();
        $this->assertGreaterThanOrEqual(1, $inbox->unreadCount($staff));

        $tickets->reply($ticket->fresh(), $staff, 'Staff reply 1', SupportTicketMessageVisibility::CustomerVisible);
        $customer->refresh();
        $this->assertGreaterThanOrEqual(1, $inbox->unreadCount($customer));

        $tickets->reply($ticket->fresh(), $customer, 'Customer message 2', SupportTicketMessageVisibility::CustomerVisible);
        $staff->refresh();
        $this->assertGreaterThanOrEqual(1, $inbox->unreadCount($staff));

        $messageBodies = $ticket->fresh()->messages()->orderBy('id')->pluck('body')->all();
        $this->assertSame(
            ['Customer message 1', 'Staff reply 1', 'Customer message 2'],
            $messageBodies,
        );

        $auditActions = AuditLog::query()
            ->where('auditable_type', \App\Models\SupportTicket::class)
            ->where('auditable_id', $ticket->id)
            ->orderBy('id')
            ->pluck('action')
            ->all();
        $this->assertNotEmpty($auditActions);
        $this->assertContains('support.ticket_created', $auditActions);

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.ops.events', ['since_id' => 0, 'limit' => 100]))
            ->assertOk()
            ->assertJsonPath('data.transport', 'EVENT_POLLING');
    }
}
