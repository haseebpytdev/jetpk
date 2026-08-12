<?php

namespace Tests\Unit\Services\Dashboard;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Services\Dashboard\Api\DashboardUsersReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardUsersStaffScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function staff_scope_excludes_agents_and_customers(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
        ]);
        User::factory()->create([
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Active,
            'name' => 'Internal Staff One',
        ]);
        User::factory()->create([
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
            'name' => 'Agency Agent One',
        ]);
        User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'name' => 'Agency Staff One',
        ]);
        User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'name' => 'Customer One',
        ]);

        $service = app(DashboardUsersReadService::class);
        $staff = $service->paginate($admin, Request::create('/api/dashboard/users', 'GET', [
            'scope' => 'staff',
            'pageSize' => 50,
        ]));
        $users = $service->paginate($admin, Request::create('/api/dashboard/users', 'GET', [
            'scope' => 'users',
            'pageSize' => 50,
        ]));

        foreach ($staff['items'] as $row) {
            $this->assertSame('staff', $row['accountType']);
            $this->assertSame('Staff', $row['userTypeLabel']);
        }

        $types = collect($users['items'])->pluck('accountType')->unique()->values()->all();
        $this->assertContains('customer', $types);
        $this->assertContains('agent', $types);
        $this->assertContains('agent_staff', $types);
    }
}
