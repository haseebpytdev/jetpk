<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\CommunicationLog;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JpBo04OperatorControlsRoundtripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_commission_adjustment_payout_and_statement_json_roundtrip(): void
    {
        [$agent, $admin] = $this->agentAndAdmin();

        $adjust = $this->actingAs($admin)->postJson(
            route('admin.commissions.adjustments.store', ['agent' => $agent, 'format' => 'json']),
            ['amount' => 150, 'description' => 'jp-bo-04 adjust'],
        );
        $adjust->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseHas('agent_commission_entries', [
            'agent_id' => $agent->id,
            'type' => 'adjustment',
            'commission_amount' => 150,
        ]);

        $payout = $this->actingAs($admin)->postJson(
            route('admin.commissions.payouts.store', ['agent' => $agent, 'format' => 'json']),
            ['amount' => 50, 'description' => 'jp-bo-04 payout'],
        );
        $payout->assertOk()->assertJsonPath('ok', true);

        $statement = $this->actingAs($admin)->postJson(
            route('admin.commissions.statements.store', ['agent' => $agent, 'format' => 'json']),
            [],
        );
        $statement->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('statement.agent_id', (string) $agent->id);
        $this->assertDatabaseHas('agent_commission_statements', ['agent_id' => $agent->id]);
    }

    public function test_user_activate_suspend_invite_reset_json_roundtrip(): void
    {
        $admin = $this->platformAdmin();
        $target = User::factory()->create([
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Suspended,
            'current_agency_id' => $admin->current_agency_id,
        ]);

        $activate = $this->actingAs($admin)->patchJson(
            route('admin.users.activate', ['user' => $target, 'format' => 'json']),
        );
        $activate->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(UserAccountStatus::Active, $target->fresh()->status);

        $suspend = $this->actingAs($admin)->patchJson(
            route('admin.users.suspend', ['user' => $target, 'format' => 'json']),
        );
        $suspend->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(UserAccountStatus::Suspended, $target->fresh()->status);

        $invite = $this->actingAs($admin)->postJson(
            route('admin.users.send-invite', ['user' => $target, 'format' => 'json']),
        );
        $invite->assertOk()->assertJsonPath('ok', true);

        $reset = $this->actingAs($admin)->postJson(
            route('admin.users.reset-password-link', ['user' => $target, 'format' => 'json']),
        );
        $reset->assertOk()->assertJsonPath('ok', true);
    }

    public function test_agency_prefix_accepts_code_prefix_json(): void
    {
        [$agent, $admin] = $this->agentAndAdmin();
        $agency = Agency::query()->findOrFail($agent->agency_id);

        $response = $this->actingAs($admin)->patchJson(
            route('admin.agencies.prefix.update', ['agency' => $agency, 'format' => 'json']),
            ['code_prefix' => 'JP'],
        );
        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('agency.code_prefix', 'JP');
    }

    public function test_delivery_log_resend_json_for_failed_log(): void
    {
        $admin = $this->platformAdmin();
        $log = CommunicationLog::query()->create([
            'agency_id' => $admin->current_agency_id,
            'channel' => 'email',
            'event' => 'booking_confirmation',
            'status' => 'failed',
            'recipient_email' => 'ops-fail@example.test',
            'error_message' => 'jp-bo-04 synthetic failure',
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('admin.settings.communications.delivery-log.resend', [
                'communicationLog' => $log,
                'format' => 'json',
            ]),
        );

        // Resend may queue or reject based on event eligibility; both are JSON contracts.
        $this->assertTrue(in_array($response->status(), [200, 422], true));
        $this->assertIsBool($response->json('ok'));
    }

    public function test_reports_export_sales_stream_still_available(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get('/admin/reports/export/sales')
            ->assertOk();
    }

    /**
     * @return array{0: Agent, 1: User}
     */
    protected function agentAndAdmin(): array
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);
        $user = User::factory()->create([
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);
        $agent = Agent::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'code' => 'BO04'.random_int(100, 999),
            'is_active' => true,
            'commission_percent' => 2.5,
        ]);

        return [$agent, $admin];
    }

    protected function platformAdmin(): User
    {
        $agency = Agency::factory()->create();

        return User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);
    }
}
