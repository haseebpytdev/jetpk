<?php

namespace Tests\Feature\Ops;

use App\Enums\AccountType;
use App\Enums\AgentWalletStatus;
use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Ops\OpsInboxService;
use App\Services\Support\SupportTicketService;
use App\Support\Platform\PlatformModuleEnforcer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JpOps08AgentFinanceAndStaleStateTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_agent_deposit_submit_fans_out_without_balance_mutation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->enableAgentDepositsModule();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $agentUser = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($agentUser->id, ['role' => 'agent']);

        $agent = Agent::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $agentUser->id,
            'code' => 'OPS08A'.fake()->unique()->numberBetween(100, 999),
            'status' => 'active',
        ]);

        $wallet = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agentUser->id,
            'currency' => 'PKR',
            'balance' => 100.00,
            'status' => AgentWalletStatus::Active,
        ]);

        $balanceBefore = (float) $wallet->balance;

        $deposit = app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 10,
            'payment_method' => 'Bank',
            'reference' => 'JP-OPS08-DEP-'.fake()->unique()->numberBetween(1000, 9999),
            'agent_note' => 'QA nonfinancial intake',
        ]);

        $this->assertSame($balanceBefore, (float) $wallet->fresh()->balance);
        $this->assertSame('submitted', (string) ($deposit->status?->value ?? $deposit->status));

        $admin->refresh();
        $this->assertGreaterThanOrEqual(1, app(OpsInboxService::class)->unreadCount($admin));

        $items = app(OpsInboxService::class)->listForUser($admin)['items'];
        $this->assertTrue(collect($items)->contains(fn (array $row): bool => ($row['event_type'] ?? '') === 'agent.deposit_submitted'));

        $this->actingAs($agentUser)
            ->getJson(route('api.dashboard.ops.inbox'))
            ->assertForbidden();
    }

    public function test_stale_reply_to_closed_ticket_is_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        $tickets = app(SupportTicketService::class);
        $ticket = $tickets->createTicket($customer, $agency, [
            'subject' => 'JP-OPS-08 stale concurrency',
            'category' => 'other',
            'body' => 'open message',
        ]);

        $tickets->updateStatus($ticket, SupportTicketStatus::Closed, $admin);

        $this->expectException(InvalidArgumentException::class);
        $tickets->reply($ticket->fresh(), $staff, 'stale reply', SupportTicketMessageVisibility::CustomerVisible);
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

        if (app()->bound(PlatformModuleEnforcer::class)) {
            // Forget caches if service exposes forget.
            $service = app(\App\Services\Platform\PlatformModuleSettingsService::class);
            if (method_exists($service, 'forgetCache')) {
                $service->forgetCache();
            }
        }
    }
}
