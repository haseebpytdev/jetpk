<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Dashboard\Api\DashboardCustomersReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardCustomersJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_columns_use_country_code_not_country(): void
    {
        $this->assertTrue(Schema::hasColumn('user_profiles', 'country_code'));
        $this->assertFalse(Schema::hasColumn('user_profiles', 'country'));
    }

    public function test_admin_customers_list_json_uses_country_code_and_returns_200(): void
    {
        $admin = $this->platformAdmin();
        $customer = User::factory()->customer()->create([
            'name' => 'QA Customer Country',
            'email' => 'qa.customer.country@example.test',
        ]);
        UserProfile::query()->updateOrCreate(
            ['user_id' => $customer->id],
            [
                'phone' => '+923001112233',
                'whatsapp' => '+923001112233',
                'city' => 'Lahore',
                'country_code' => 'PK',
                'nationality' => 'PK',
            ],
        );

        $this->actingAs($admin)
            ->getJson('/api/dashboard/customers?pageSize=25&sort=newest')
            ->assertOk()
            ->assertJsonPath('schemaVersion', 'dash-read-only-v1')
            ->assertJsonStructure([
                'data' => [
                    'customers',
                    'summary',
                ],
                'pagination' => ['page', 'pageSize', 'total', 'pageCount'],
            ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/dashboard/customers?q='.rawurlencode('QA Customer Country'));

        $response->assertOk();
        $items = $response->json('data.customers') ?? [];
        $this->assertNotEmpty($items);
        $match = collect($items)->firstWhere('fullName', 'QA Customer Country');
        $this->assertIsArray($match);
        $this->assertSame('PK', $match['country'] ?? null);
        $this->assertSame('Lahore', $match['city'] ?? null);
        $this->assertGreaterThanOrEqual(0, (int) ($match['bookingCount'] ?? -1));
    }

    public function test_admin_customer_detail_json_eager_loads_profile_and_booking_aggregates(): void
    {
        $admin = $this->platformAdmin();
        $customer = User::factory()->customer()->create([
            'name' => 'QA Customer Detail',
            'email' => 'qa.customer.detail@example.test',
        ]);

        UserProfile::query()->updateOrCreate(
            ['user_id' => $customer->id],
            [
                'phone' => '+923009998877',
                'city' => 'Karachi',
                'country_code' => 'PK',
                'nationality' => 'PK',
            ],
        );

        $this->assertTrue($admin->isPlatformAdmin(), 'admin must be platform admin');

        $direct = app(DashboardCustomersReadService::class)->detail($admin, (string) $customer->id);
        $this->assertIsArray($direct, 'service detail() must resolve customer id='.$customer->id);
        $this->assertSame('PK', $direct['country'] ?? null);
        $this->assertSame('Karachi', $direct['city'] ?? null);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/customers/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('schemaVersion', 'dash-read-only-v1')
            ->assertJsonPath('data.id', 'CU-'.$customer->id)
            ->assertJsonPath('data.city', 'Karachi')
            ->assertJsonPath('data.country', 'PK')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'fullName',
                    'email',
                    'phone',
                    'city',
                    'country',
                    'nationality',
                    'accountStatus',
                    'verificationStatus',
                    'bookingCount',
                    'lastBookingDate',
                ],
            ]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/customers/CU-'.$customer->id)
            ->assertOk()
            ->assertJsonPath('data.id', 'CU-'.$customer->id)
            ->assertJsonPath('data.country', 'PK');
    }

    public function test_admin_customers_search_filter_and_sort_contracts(): void
    {
        $admin = $this->platformAdmin();
        $active = User::factory()->customer()->create([
            'name' => 'Alpha Search Customer',
            'email' => 'alpha.search@example.test',
            'status' => UserAccountStatus::Active,
        ]);
        UserProfile::query()->updateOrCreate(
            ['user_id' => $active->id],
            ['phone' => '+923001234001', 'city' => 'Islamabad', 'country_code' => 'PK'],
        );

        $this->actingAs($admin)
            ->getJson('/api/dashboard/customers?q=Alpha%20Search&accountStatus=active&sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('filters.q', 'Alpha Search')
            ->assertJsonPath('filters.accountStatus', 'active')
            ->assertJsonPath('filters.sort', 'name');

        $names = collect($this->actingAs($admin)
            ->getJson('/api/dashboard/customers?q=Alpha%20Search&sort=name&direction=asc')
            ->json('data.customers') ?? [])
            ->pluck('fullName')
            ->all();

        $this->assertContains('Alpha Search Customer', $names);
    }

    protected function platformAdmin(): User
    {
        return User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
    }
}
