<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
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
    }
}
