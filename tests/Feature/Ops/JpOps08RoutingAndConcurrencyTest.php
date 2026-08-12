<?php

namespace Tests\Feature\Ops;

use App\Enums\AccountType;
use App\Enums\AgentWalletStatus;
use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Exceptions\StaleOperationalStateException;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Ops\OpsInboxService;
use App\Services\Support\SupportTicketService;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JpOps08RoutingAndConcurrencyTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_department_routing_support_only_staff_does_not_receive_finance_deposit(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->enableAgentDepositsModule();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $supportStaff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
            'meta' => [
                'staff_permissions' => [
                    StaffPermission::SupportView,
                    StaffPermission::SupportReply,
                    StaffPermission::SupportStatus,
                ],
                'permission_group' => StaffPermission::PresetSupport,
            ],
        ]);
        $financeStaff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
            'meta' => [
                'staff_permissions' => [
                    StaffPermission::PaymentsVerify,
                    StaffPermission::LedgerView,
                ],
                'permission_group' => 'finance',
            ],
        ]);

        [$agent, $agentUser] = $this->makeAgentWithWallet($agency);

        app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 12,
            'payment_method' => 'Bank',
            'reference' => 'JP-OPS08-ROUTE-'.fake()->unique()->numberBetween(1000, 9999),
        ]);

        $inbox = app(OpsInboxService::class);
        $this->assertGreaterThanOrEqual(1, $inbox->unreadCount($admin->fresh()));
        $this->assertGreaterThanOrEqual(1, $inbox->unreadCount($financeStaff->fresh()));
        $this->assertSame(0, $inbox->unreadCount($supportStaff->fresh()));

        $financeItems = collect($inbox->listForUser($financeStaff->fresh())['items']);
        $this->assertTrue($financeItems->contains(fn (array $row): bool => ($row['event_type'] ?? '') === 'agent.deposit_submitted'));
        $this->assertTrue($financeItems->contains(fn (array $row): bool => ($row['deep_link'] ?? '') === 'agents/deposits'));
    }

    public function test_support_create_does_not_fan_out_to_finance_only_staff(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $supportStaff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
            'meta' => [
                'staff_permissions' => [StaffPermission::SupportView, StaffPermission::SupportReply],
            ],
        ]);
        $financeStaff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
            'meta' => [
                'staff_permissions' => [StaffPermission::PaymentsVerify, StaffPermission::LedgerView],
            ],
        ]);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        app(SupportTicketService::class)->createTicket($customer, $agency, [
            'subject' => 'JP-OPS-08 dept support',
            'category' => 'other',
            'body' => 'private support body',
        ]);

        $inbox = app(OpsInboxService::class);
        $this->assertGreaterThanOrEqual(1, $inbox->unreadCount($supportStaff->fresh()));
        $this->assertSame(0, $inbox->unreadCount($financeStaff->fresh()));
    }

    public function test_agent_deposit_duplicate_event_key_and_agency_isolation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->enableAgentDepositsModule();
        $agencyA = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agencyB = Agency::query()->create([
            'name' => 'JP OPS08 Finance B',
            'slug' => 'jp-ops08-finance-b',
            'is_active' => true,
        ]);
        $adminA = $this->platformAdmin();
        $adminA->forceFill(['current_agency_id' => $agencyA->id])->save();
        $staffB = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agencyB->id,
            'meta' => [
                'staff_permissions' => [StaffPermission::PaymentsVerify, StaffPermission::LedgerView],
            ],
        ]);

        [$agent, $agentUser] = $this->makeAgentWithWallet($agencyA);
        $service = app(AgentWalletService::class);
        $deposit = $service->submitDepositRequest($agent, $agentUser, [
            'amount' => 8,
            'payment_method' => 'Bank',
            'reference' => 'JP-OPS08-DEDUP-'.fake()->unique()->numberBetween(1000, 9999),
        ]);

        // Replay fan-out with same event key must not double unread for admin.
        $before = app(OpsInboxService::class)->unreadCount($adminA->fresh());
        app(\App\Services\Ops\OpsEventDispatcher::class)->agentDepositSubmitted($deposit, $agentUser);
        $this->assertSame($before, app(OpsInboxService::class)->unreadCount($adminA->fresh()));

        $this->assertSame(0, app(OpsInboxService::class)->unreadCount($staffB->fresh()));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent.deposit_submitted',
            'auditable_id' => $deposit->id,
        ]);
    }

    public function test_stale_assign_after_concurrent_status_change_is_rejected_with_fresh_state(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
            'meta' => ['staff_permissions' => [StaffPermission::SupportView, StaffPermission::SupportReply, StaffPermission::SupportStatus]],
        ]);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        $tickets = app(SupportTicketService::class);
        $ticket = $tickets->createTicket($customer, $agency, [
            'subject' => 'JP-OPS-08 concurrency',
            'category' => 'other',
            'body' => 'open',
        ]);
        $staleUpdatedAt = $ticket->fresh()->updated_at?->toIso8601String();

        // Actor B changes authoritative state.
        $tickets->updateStatus($ticket->fresh(), SupportTicketStatus::Closed, $staff);
        $this->assertSame(SupportTicketStatus::Closed, $ticket->fresh()->status);

        // Actor A attempts assign based on stale updated_at.
        try {
            $tickets->assign($ticket->fresh(), $staff, $admin, $staleUpdatedAt);
            $this->fail('Expected StaleOperationalStateException');
        } catch (StaleOperationalStateException $e) {
            $this->assertSame('closed', $e->freshState['ticket']['status'] ?? null);
        }

        $this->assertNull($ticket->fresh()->assigned_to_user_id);

        // Without expected stamp, closed ticket still rejects assign.
        $this->expectException(StaleOperationalStateException::class);
        $tickets->assign($ticket->fresh(), $staff, $admin, null);
    }

    public function test_outward_status_propagates_to_customer_without_internal_assignment_leak(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
            'meta' => ['staff_permissions' => [StaffPermission::SupportView, StaffPermission::SupportStatus]],
        ]);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        $tickets = app(SupportTicketService::class);
        $inbox = app(OpsInboxService::class);
        $ticket = $tickets->createTicket($customer, $agency, [
            'subject' => 'JP-OPS-08 outward',
            'category' => 'other',
            'body' => 'need help',
        ]);

        $before = $inbox->unreadCount($customer->fresh());
        $tickets->assign($ticket->fresh(), $staff, $admin);
        // Assignment must not create a customer-facing inbox item.
        $this->assertSame($before, $inbox->unreadCount($customer->fresh()));

        $tickets->updateStatus($ticket->fresh(), SupportTicketStatus::Pending, $staff);
        $customer->refresh();
        $this->assertGreaterThan($before, $inbox->unreadCount($customer));
        $items = collect($inbox->listForUser($customer)['items']);
        $this->assertTrue($items->contains(fn (array $row): bool => ($row['event_type'] ?? '') === 'support.status_changed'));
        $this->assertFalse($items->contains(fn (array $row): bool => str_contains((string) ($row['summary'] ?? ''), 'assigned')));

        $beforeInternal = $inbox->unreadCount($customer->fresh());
        $tickets->reply($ticket->fresh(), $staff, 'INTERNAL ONLY', SupportTicketMessageVisibility::Internal);
        $this->assertFalse(
            collect($inbox->listForUser($customer->fresh())['items'])
                ->contains(fn (array $row): bool => str_contains((string) ($row['summary'] ?? ''), 'INTERNAL ONLY')),
        );
        $this->assertSame($beforeInternal, $inbox->unreadCount($customer->fresh()));
    }

    /**
     * @return array{0: Agent, 1: User}
     */
    private function makeAgentWithWallet(Agency $agency): array
    {
        $agentUser = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($agentUser->id, ['role' => 'agent']);
        $agent = Agent::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $agentUser->id,
            'code' => 'OPS08R'.fake()->unique()->numberBetween(100, 999),
            'status' => 'active',
        ]);
        AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agentUser->id,
            'currency' => 'PKR',
            'balance' => 50.00,
            'status' => AgentWalletStatus::Active,
        ]);

        return [$agent, $agentUser];
    }

    private function enableAgentDepositsModule(): void
    {
        if (! class_exists(\App\Models\PlatformModuleSetting::class)) {
            return;
        }
        \App\Models\PlatformModuleSetting::query()->updateOrCreate(
            ['module_key' => 'agent_deposits'],
            ['enabled' => true],
        );
        $service = app(\App\Services\Platform\PlatformModuleSettingsService::class);
        if (method_exists($service, 'forgetCache')) {
            $service->forgetCache();
        }
    }
}
