<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCommissionsJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_commissions_index_json(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.commissions.index').'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'kpis' => ['pending', 'approved_unpaid', 'paid_this_month', 'active_agents'],
                'pending_entries',
                'agents',
            ]);
    }
}
