<?php

namespace Tests\Unit\Policies;

use App\Enums\CustomerQueryStatus;
use App\Models\CustomerQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CustomerQueryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_and_update_customer_queries(): void
    {
        $admin = User::factory()->create(['account_type' => 'platform_admin']);
        $query = CustomerQuery::query()->create([
            'name' => 'Policy Test',
            'email' => 'policy-test@jetpakistan.pk',
            'phone_raw' => '03001234567',
            'contact_consent' => true,
            'consent_timestamp' => now(),
            'consent_source' => 'ask_jetpakistan',
            'source' => 'ask_jetpakistan',
            'status' => CustomerQueryStatus::New,
            'last_activity_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', CustomerQuery::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $query));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $query));
    }

    public function test_customer_user_cannot_access_customer_queries(): void
    {
        $customer = User::factory()->create(['account_type' => 'customer']);
        $query = CustomerQuery::query()->create([
            'name' => 'Policy Test',
            'email' => 'policy-test@jetpakistan.pk',
            'phone_raw' => '03001234567',
            'contact_consent' => true,
            'consent_timestamp' => now(),
            'consent_source' => 'ask_jetpakistan',
            'source' => 'ask_jetpakistan',
            'status' => CustomerQueryStatus::New,
            'last_activity_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($customer)->allows('viewAny', CustomerQuery::class));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $query));
        $this->assertFalse(Gate::forUser($customer)->allows('update', $query));
    }
}
