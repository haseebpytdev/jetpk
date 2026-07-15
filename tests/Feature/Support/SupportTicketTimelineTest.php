<?php

namespace Tests\Feature\Support;

use App\Enums\AccountType;
use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Support\Support\SupportTicketTimelineBuilder;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class SupportTicketTimelineTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_builder_includes_core_steps_for_new_ticket(): void
    {
        $ticket = $this->baseTicket();

        $steps = app(SupportTicketTimelineBuilder::class)->build($ticket, SupportTicketTimelineBuilder::AUDIENCE_INTERNAL);
        $keys = array_column($steps, 'key');

        $this->assertSame(
            ['submitted', 'received', 'in_progress', 'resolved', 'closed'],
            $keys,
        );
        $this->assertSame('completed', $steps[0]['state']);
        $this->assertSame('pending', $steps[array_search('in_progress', $keys, true)]['state']);
    }

    public function test_builder_adds_assigned_and_forwarded_steps_with_forwarded_at(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $scenario['agencyA']->id,
            'name' => 'Timeline Staff User',
        ]);
        $forwardedAt = now()->subHour();

        $ticket = SupportTicket::query()->create([
            'agency_id' => $scenario['agencyA']->id,
            'ticket_reference' => 'SR-TL-001',
            'source' => 'public',
            'requester_name' => 'Guest',
            'requester_email' => 'guest@example.test',
            'subject' => 'Timeline forward test',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'assigned_to_user_id' => $staff->id,
            'forwarded_to_agent_id' => $scenario['agentA']->id,
            'forwarded_at' => $forwardedAt,
            'last_reply_at' => now(),
        ]);

        $ticket->load(['assignedTo', 'forwardedToAgent.user']);
        $steps = app(SupportTicketTimelineBuilder::class)->build($ticket);
        $forwarded = collect($steps)->firstWhere('key', 'forwarded');

        $this->assertNotNull($forwarded);
        $this->assertSame('completed', $forwarded['state']);
        $this->assertSame($forwardedAt->format('j M Y, H:i'), $forwarded['at']);
        $this->assertStringContainsString($scenario['agentA']->code, (string) $forwarded['detail']);
        $this->assertNull(collect($steps)->firstWhere('key', 'assigned')['at']);
    }

    public function test_builder_marks_in_progress_active_after_staff_reply(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
        ]);

        $ticket = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $customer->id,
            'subject' => 'Reply signal',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'assigned_to_user_id' => $staff->id,
            'last_reply_at' => now(),
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'visibility' => SupportTicketMessageVisibility::CustomerVisible,
            'body' => 'Initial',
        ]);
        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'visibility' => SupportTicketMessageVisibility::CustomerVisible,
            'body' => 'Staff reply',
        ]);

        $ticket->load('messages');
        $inProgress = collect(app(SupportTicketTimelineBuilder::class)->build($ticket))
            ->firstWhere('key', 'in_progress');

        $this->assertSame('active', $inProgress['state']);
    }

    public function test_customer_show_does_not_leak_staff_or_agent_names(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $scenario['agencyA']->id,
            'name' => 'Secret Staff Name',
        ]);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $scenario['agencyA']->id,
            'email_verified_at' => now(),
        ]);

        $ticket = SupportTicket::query()->create([
            'agency_id' => $scenario['agencyA']->id,
            'created_by_user_id' => $customer->id,
            'ticket_reference' => 'SR-TL-CUST',
            'subject' => 'Customer privacy timeline',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'assigned_to_user_id' => $staff->id,
            'forwarded_to_agent_id' => $scenario['agentA']->id,
            'forwarded_at' => now(),
            'last_reply_at' => now(),
        ]);

        $staffName = 'Secret Staff Name';
        $agentCode = (string) $scenario['agentA']->code;

        $this->actingAs($customer)->get(route('customer.support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-testid="support-ticket-timeline"', false)
            ->assertSee('Ticket progress', false)
            ->assertSee('Assigned to support', false)
            ->assertDontSee($staffName, false)
            ->assertDontSee($agentCode, false);
    }

    public function test_admin_show_renders_timeline_with_assignee_detail(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $admin = $this->platformAdmin();

        $ticket = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $staff->id,
            'subject' => 'Admin timeline',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'assigned_to_user_id' => $staff->id,
            'last_reply_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-testid="support-ticket-timeline"', false)
            ->assertSee((string) $staff->name, false);
    }

    public function test_resolved_step_uses_closed_at(): void
    {
        $closedAt = now()->subMinutes(5);
        $ticket = $this->baseTicket();
        $ticket->forceFill([
            'status' => SupportTicketStatus::Resolved,
            'closed_at' => $closedAt,
        ])->save();

        $resolved = collect(app(SupportTicketTimelineBuilder::class)->build($ticket))
            ->firstWhere('key', 'resolved');

        $this->assertSame('completed', $resolved['state']);
        $this->assertSame($closedAt->format('j M Y, H:i'), $resolved['at']);
    }

    private function baseTicket(): SupportTicket
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);

        return SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $customer->id,
            'ticket_reference' => 'SR-TL-BASE',
            'subject' => 'Base ticket',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'last_reply_at' => now(),
        ]);
    }
}
