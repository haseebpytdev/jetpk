<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBatch4QueuesJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_cancellation_and_refund_queues(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.bookings.cancellations.index').'?format=json&queue=review')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['cancellations', 'meta']);

        $this->actingAs($admin)
            ->getJson(route('admin.bookings.refunds.index').'?format=json&queue=execution')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['refunds', 'meta']);
    }

    public function test_admin_can_load_notification_failures_and_ops_inbox_json(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.settings.communications.delivery-log.index').'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('safety.blind_retry', false)
            ->assertJsonStructure(['failures', 'kpis', 'meta']);

        $this->actingAs($admin)
            ->getJson(route('admin.operations.inbox').'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('fixture_rows', 0)
            ->assertJsonStructure(['events', 'assigned_bookings', 'assigned_support', 'kpis']);
    }

    public function test_admin_agent_applications_data_includes_structured_rows(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.agent-applications.data'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['applications', 'meta', 'kpis']);
    }
}
