<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Communication\NotificationRecipientResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_customer_can_create_support_ticket(): void
    {
        [$customer, $booking] = $this->customerUser();

        $this->actingAs($customer)->post(route('customer.support.tickets.store'), [
            'subject' => 'Payment question',
            'category' => 'payment',
            'body' => 'I need help with my payment.',
            'booking_id' => $booking->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'created_by_user_id' => $customer->id,
            'subject' => 'Payment question',
        ]);
    }

    public function test_customer_can_view_only_own_tickets(): void
    {
        [$customer, $ticket] = $this->customerTicket();

        $this->actingAs($customer)->get(route('customer.support.tickets.index'))->assertOk();
        $this->actingAs($customer)->get(route('customer.support.tickets.show', $ticket))->assertOk();
    }

    public function test_customer_cannot_view_another_customers_ticket(): void
    {
        [, $ticket] = $this->customerTicket();
        [$other] = $this->customerUser();

        $this->actingAs($other)->get(route('customer.support.tickets.show', $ticket))->assertForbidden();
    }

    public function test_agent_can_create_support_ticket(): void
    {
        [$agentUser] = $this->agentUser();

        $this->actingAs($agentUser)->post(route('agent.support.tickets.store'), [
            'subject' => 'Commission query',
            'category' => 'other',
            'body' => 'Please clarify my commission.',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'created_by_user_id' => $agentUser->id,
            'subject' => 'Commission query',
        ]);
    }

    public function test_agent_cannot_view_another_agents_ticket(): void
    {
        [, $ticket] = $this->agentTicket();
        [$otherAgent] = $this->agentUser();

        $this->actingAs($otherAgent)->get(route('agent.support.tickets.show', $ticket))->assertForbidden();
    }

    public function test_staff_can_view_agency_tickets(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        $staff = $this->staffUser($customer->current_agency_id);

        $this->actingAs($staff)->get(route('staff.support.tickets.index'))->assertOk();
        $this->actingAs($staff)->get(route('staff.support.tickets.show', $ticket))->assertOk();
    }

    public function test_staff_public_reply_visible_to_customer(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        $staff = $this->staffUser($customer->current_agency_id);

        $this->actingAs($staff)->post(route('staff.support.tickets.reply', $ticket), [
            'body' => 'We are reviewing your request.',
        ])->assertRedirect();

        $this->actingAs($customer)->get(route('customer.support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('We are reviewing your request', false);
    }

    public function test_internal_note_not_visible_to_customer(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        $staff = $this->staffUser($customer->current_agency_id);

        $this->actingAs($staff)->post(route('staff.support.tickets.reply', $ticket), [
            'body' => 'Internal ops note only',
            'visibility' => 'internal',
        ])->assertRedirect();

        $this->actingAs($customer)->get(route('customer.support.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Internal ops note only', false);
    }

    public function test_status_change_staff_only(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        $staff = $this->staffUser($customer->current_agency_id);

        $this->actingAs($customer)->patch(route('staff.support.tickets.status', $ticket), [
            'status' => 'resolved',
        ])->assertForbidden();

        $this->actingAs($staff)->patch(route('staff.support.tickets.status', $ticket), [
            'status' => 'resolved',
        ])->assertRedirect();

        $this->assertSame('resolved', $ticket->fresh()->status->value);
    }

    public function test_ticket_creation_triggers_notification_routing(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        $platformAdmin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($customer->current_agency_id);

        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $agency,
            OtaNotificationEvent::SupportTicketCreated->value,
            $ticket->booking,
            $customer,
            [
                'ticket_creator_email' => $customer->email,
                'notify_buckets' => ['admin', 'ticket_creator'],
            ],
        );

        $this->assertContains(strtolower($platformAdmin->email), $resolved['to']);
        $this->assertContains(strtolower($customer->email), $resolved['to']);
    }

    public function test_ticket_reply_triggers_creator_notification_for_staff_reply(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        $agency = Agency::query()->findOrFail($customer->current_agency_id);
        $staff = $this->staffUser($customer->current_agency_id);

        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $agency,
            OtaNotificationEvent::SupportTicketReplied->value,
            $ticket->booking,
            $staff,
            [
                'ticket_creator_email' => $customer->email,
                'notify_buckets' => ['ticket_creator'],
            ],
        );

        $this->assertContains(strtolower($customer->email), $resolved['to']);
        $this->assertNotContains('admin@ota.demo', $resolved['to']);
    }

    public function test_sidebar_support_ticket_routes_load(): void
    {
        [$customer] = $this->customerUser();
        [$agent] = $this->agentUser();
        $staff = $this->staffUser($customer->current_agency_id);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($customer)->get(route('customer.support.tickets.index'))->assertOk();
        $this->actingAs($agent)->get(route('agent.support.tickets.index'))->assertOk();
        $this->actingAs($staff)->get(route('staff.support.tickets.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.support.tickets.index'))->assertOk();
    }

    public function test_customer_support_status_labels_on_index(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        $ticket->update(['status' => SupportTicketStatus::Pending]);

        $this->actingAs($customer)->get(route('customer.support.tickets.index'))
            ->assertOk()
            ->assertSee('Pending', false)
            ->assertDontSee('>open<', false);

        $ticket->update(['status' => SupportTicketStatus::Open]);
        $this->actingAs($customer)->get(route('customer.support.tickets.index'))
            ->assertOk()
            ->assertSee('Under review', false);

        $ticket->update(['status' => SupportTicketStatus::Resolved]);
        $this->actingAs($customer)->get(route('customer.support.tickets.index'))
            ->assertOk()
            ->assertSee('Finalised', false);
    }

    public function test_ticket_show_does_not_render_supplier_secrets(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'visibility' => SupportTicketMessageVisibility::CustomerVisible,
            'body' => 'My issue',
        ]);

        $this->actingAs($customer)->get(route('customer.support.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Authorization', false)
            ->assertDontSee('duffel_test_', false);
    }

    public function test_customer_reply_creates_communication_log_on_notification_send(): void
    {
        [$customer, $ticket] = $this->customerTicket();
        $staff = $this->staffUser($customer->current_agency_id);

        $this->actingAs($customer)->post(route('customer.support.tickets.reply', $ticket), [
            'body' => 'Following up on this.',
        ])->assertRedirect();

        $this->assertGreaterThan(0, CommunicationLog::query()
            ->where('event', OtaNotificationEvent::SupportTicketReplied->value)
            ->count());
    }

    /**
     * @return array{0: User, 1: Booking}
     */
    private function customerUser(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_reference' => 'BKG-'.fake()->unique()->numberBetween(10000, 99999),
        ]);

        return [$customer, $booking];
    }

    /**
     * @return array{0: User, 1: SupportTicket}
     */
    private function customerTicket(): array
    {
        [$customer, $booking] = $this->customerUser();
        $ticket = SupportTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'created_by_user_id' => $customer->id,
            'subject' => 'Help needed',
            'category' => 'booking',
            'status' => SupportTicketStatus::Open,
            'last_reply_at' => now(),
        ]);
        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'visibility' => SupportTicketMessageVisibility::CustomerVisible,
            'body' => 'Initial message',
        ]);

        return [$customer, $ticket];
    }

    /**
     * @return array{0: User}
     */
    private function agentUser(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $user = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);
        Agent::query()->create([
            'user_id' => $user->id,
            'agency_id' => $agency->id,
            'code' => 'AGT-'.fake()->unique()->numberBetween(100, 999),
            'commission_percent' => 0,
            'is_active' => true,
        ]);

        return [$user];
    }

    /**
     * @return array{0: User, 1: SupportTicket}
     */
    private function agentTicket(): array
    {
        [$user] = $this->agentUser();
        $agencyId = $user->current_agency_id;
        $ticket = SupportTicket::query()->create([
            'agency_id' => $agencyId,
            'created_by_user_id' => $user->id,
            'subject' => 'Agent ticket',
            'category' => 'other',
            'status' => SupportTicketStatus::Open,
            'last_reply_at' => now(),
        ]);

        return [$user, $ticket];
    }

    private function staffUser(int $agencyId): User
    {
        $this->seed(OtaFoundationSeeder::class);

        return User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agencyId,
        ]);
    }
}
