<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUsersJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_directory_includes_platform_account_types(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
            'name' => 'QA Platform Admin',
        ]);
        User::factory()->create([
            'account_type' => AccountType::Staff,
            'name' => 'QA Staff User',
        ]);
        User::factory()->customer()->create([
            'name' => 'QA Directory Customer',
            'email' => 'qa.directory.customer@example.test',
        ]);
        User::factory()->create([
            'account_type' => AccountType::Agent,
            'name' => 'QA Agent Owner',
        ]);
        User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'name' => 'QA Agent Staff',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/dashboard/users?pageSize=50&sort=newest');

        $response->assertOk()
            ->assertJsonPath('schemaVersion', 'dash-read-only-v1');

        $users = collect($response->json('data.users') ?? []);
        $this->assertNotEmpty($users);

        $types = $users->pluck('accountType')->unique()->sort()->values()->all();
        foreach (['platform_admin', 'staff', 'customer', 'agent', 'agent_staff'] as $expected) {
            $this->assertContains($expected, $types, 'missing account type '.$expected);
        }

        $customer = $users->firstWhere('fullName', 'QA Directory Customer');
        $this->assertIsArray($customer);
        $this->assertSame('customer', $customer['userType'] ?? null);
        $this->assertSame('Customer', $customer['userTypeLabel'] ?? null);
        $this->assertFalse((bool) ($customer['effectiveAccessSummary']['previewOnly'] ?? true));

        $agentStaff = $users->firstWhere('fullName', 'QA Agent Staff');
        $this->assertIsArray($agentStaff);
        $this->assertSame('agentStaff', $agentStaff['userType'] ?? null);
        $this->assertSame('Agent Staff', $agentStaff['userTypeLabel'] ?? null);
    }

    public function test_admin_users_filter_by_customer_account_type(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        User::factory()->customer()->create([
            'name' => 'Filter Customer Only',
            'email' => 'filter.customer.only@example.test',
        ]);
        User::factory()->create([
            'account_type' => AccountType::Staff,
            'name' => 'Filter Staff Only',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/dashboard/users?accountType=customer&pageSize=50');

        $response->assertOk();
        $names = collect($response->json('data.users') ?? [])->pluck('fullName')->all();
        $this->assertContains('Filter Customer Only', $names);
        $this->assertNotContains('Filter Staff Only', $names);
    }
}
