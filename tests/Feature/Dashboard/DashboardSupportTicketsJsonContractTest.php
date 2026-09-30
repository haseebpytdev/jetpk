<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSupportTicketsJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_support_tickets_as_json(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.support.tickets.index', [], absolute: false).'?format=json');

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertIsArray($response->json('tickets'));
        $this->assertIsArray($response->json('meta'));
        $this->assertSame(10, (int) $response->json('meta.pageSize'));
        $this->assertSame(10, (int) $response->json('meta.per_page'));
    }

    public function test_admin_support_ticket_show_includes_thread_and_catalogs(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $agency = Agency::factory()->create();
        $ticket = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'subject' => 'QA Support Thread Ticket',
            'status' => SupportTicketStatus::Open,
            'created_by_user_id' => $admin->id,
            'requester_name' => 'QA Requester',
            'requester_email' => 'qa.support.thread@example.test',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.support.tickets.show', $ticket).'?format=json');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ticket.id', (string) $ticket->id);
        $this->assertIsArray($response->json('ticket.messages'));
        $this->assertIsArray($response->json('assignees'));
        $this->assertIsArray($response->json('agents'));
        $this->assertIsArray($response->json('statuses'));
    }
}
