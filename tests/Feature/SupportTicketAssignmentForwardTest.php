<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\CommunicationLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Communication\NotificationRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class SupportTicketAssignmentForwardTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_admin_assign_notifies_staff_when_assignee_changes(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $ticket = $this->agencyTicket($agency);

        $this->actingAs($admin)->patch(route('admin.support.tickets.assign', $ticket), [
            'assigned_to_user_id' => $staff->id,
        ])->assertRedirect();

        $this->assertSame($staff->id, $ticket->fresh()->assigned_to_user_id);

        $this->assertGreaterThan(0, CommunicationLog::query()
            ->where('event', OtaNotificationEvent::SupportTicketAssigned->value)
            ->count());

        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $agency,
            OtaNotificationEvent::SupportTicketAssigned->value,
            $ticket->booking,
            $admin,
            [
                'ticket_assigned_staff_email' => $staff->email,
                'notify_buckets' => ['ticket_assigned_staff'],
            ],
        );

        $this->assertContains(strtolower($staff->email), $resolved['to']);
        $this->assertNotContains(strtolower($admin->email), $resolved['to']);
    }

    public function test_admin_assign_does_not_notify_on_unassign_or_unchanged_assignee(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $ticket = $this->agencyTicket($agency);
        $ticket->forceFill(['assigned_to_user_id' => $staff->id])->save();

        CommunicationLog::query()
            ->where('event', OtaNotificationEvent::SupportTicketAssigned->value)
            ->delete();

        $this->actingAs($admin)->patch(route('admin.support.tickets.assign', $ticket), [
            'assigned_to_user_id' => $staff->id,
        ])->assertRedirect();

        $this->assertSame(0, CommunicationLog::query()
            ->where('event', OtaNotificationEvent::SupportTicketAssigned->value)
            ->count());

        $this->actingAs($admin)->patch(route('admin.support.tickets.assign', $ticket), [
            'assigned_to_user_id' => '',
        ])->assertRedirect();

        $this->assertNull($ticket->fresh()->assigned_to_user_id);
        $this->assertSame(0, CommunicationLog::query()
            ->where('event', OtaNotificationEvent::SupportTicketAssigned->value)
            ->count());
    }

    public function test_admin_forward_makes_ticket_visible_to_target_agent_portal(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $admin = $this->platformAdminForAgency($scenario['agencyA']);
        $ticket = SupportTicket::query()->create([
            'agency_id' => $scenario['agencyA']->id,
            'ticket_reference' => 'SR-TEST-FWD-001',
            'source' => 'public',
            'requester_name' => 'Guest User',
            'requester_email' => 'guest@example.test',
            'subject' => 'Public ticket for agent forward',
            'category' => SupportTicketCategory::Other,
            'status' => SupportTicketStatus::Open,
            'last_reply_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.support.tickets.forward', $ticket), [
            'forwarded_to_agent_id' => $scenario['agentA']->id,
        ])->assertRedirect();

        $ticket = $ticket->fresh();
        $this->assertSame($scenario['agentA']->id, $ticket->forwarded_to_agent_id);
        $this->assertNotNull($ticket->forwarded_at);
        $this->assertSame($admin->id, $ticket->forwarded_by_user_id);

        $this->actingAs($scenario['adminA'])->get(route('agent.support.tickets.index'))
            ->assertOk()
            ->assertSee('Public ticket for agent forward', false);

        $this->actingAs($scenario['staff']['A9'])->get(route('agent.support.tickets.show', $ticket))
            ->assertOk();

        $this->actingAs($scenario['adminB'])->get(route('agent.support.tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_admin_forward_notifies_target_agent_recipients(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $admin = $this->platformAdminForAgency($scenario['agencyA']);
        $ticket = SupportTicket::query()->create([
            'agency_id' => $scenario['agencyA']->id,
            'source' => 'public',
            'requester_name' => 'Guest',
            'requester_email' => 'guest@example.test',
            'subject' => 'Notify forward recipients',
            'category' => SupportTicketCategory::Other,
            'status' => SupportTicketStatus::Open,
            'last_reply_at' => now(),
        ]);

        CommunicationLog::query()
            ->where('event', OtaNotificationEvent::SupportTicketForwarded->value)
            ->delete();

        $this->actingAs($admin)->patch(route('admin.support.tickets.forward', $ticket), [
            'forwarded_to_agent_id' => $scenario['agentA']->id,
        ])->assertRedirect();

        $this->assertGreaterThan(0, CommunicationLog::query()
            ->where('event', OtaNotificationEvent::SupportTicketForwarded->value)
            ->count());

        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $scenario['agencyA'],
            OtaNotificationEvent::SupportTicketForwarded->value,
            null,
            $admin,
            [
                'notify_buckets' => ['ticket_forwarded_agent'],
                'ticket_forwarded_agent_emails' => [
                    $scenario['adminA']->email,
                    $scenario['staff']['A9']->email,
                ],
            ],
        );

        $this->assertContains(strtolower($scenario['adminA']->email), $resolved['to']);
        $this->assertContains(strtolower($scenario['staff']['A9']->email), $resolved['to']);
    }

    public function test_admin_can_clear_forward_and_agent_loses_access(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $admin = $this->platformAdminForAgency($scenario['agencyA']);
        $ticket = SupportTicket::query()->create([
            'agency_id' => $scenario['agencyA']->id,
            'source' => 'public',
            'requester_name' => 'Guest',
            'requester_email' => 'guest@example.test',
            'subject' => 'Clear forward visibility',
            'category' => SupportTicketCategory::Other,
            'status' => SupportTicketStatus::Open,
            'forwarded_to_agent_id' => $scenario['agentA']->id,
            'forwarded_at' => now(),
            'forwarded_by_user_id' => $admin->id,
            'last_reply_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.support.tickets.forward', $ticket), [
            'forwarded_to_agent_id' => '',
        ])->assertRedirect();

        $ticket = $ticket->fresh();
        $this->assertNull($ticket->forwarded_to_agent_id);
        $this->assertNull($ticket->forwarded_at);
        $this->assertNull($ticket->forwarded_by_user_id);

        $this->actingAs($scenario['adminA'])->get(route('agent.support.tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_forward_rejects_agent_from_other_agency(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $admin = $this->platformAdminForAgency($scenario['agencyA']);
        $ticket = $this->agencyTicket($scenario['agencyA']);

        $this->actingAs($admin)->patch(route('admin.support.tickets.forward', $ticket), [
            'forwarded_to_agent_id' => $scenario['agentB']->id,
        ])->assertNotFound();
    }

    public function test_support_ticket_created_buckets_unchanged(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $agency,
            OtaNotificationEvent::SupportTicketCreated->value,
            null,
            $customer,
            [
                'ticket_creator_email' => $customer->email,
                'notify_buckets' => ['admin', 'ticket_assigned_staff', 'ticket_creator'],
            ],
        );

        $this->assertContains(strtolower($customer->email), $resolved['to']);
        $this->assertContains(strtolower($admin->email), $resolved['to']);
    }

    private function agencyTicket(Agency $agency): SupportTicket
    {
        return SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'source' => 'public',
            'requester_name' => 'Guest',
            'requester_email' => 'guest@example.test',
            'subject' => 'Agency support ticket',
            'category' => SupportTicketCategory::Other,
            'status' => SupportTicketStatus::Open,
            'last_reply_at' => now(),
        ]);
    }

    private function platformAdminForAgency(Agency $agency): User
    {
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        return $admin->fresh();
    }
}
