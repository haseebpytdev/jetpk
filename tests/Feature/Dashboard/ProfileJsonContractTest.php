<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileJsonContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_platform_admin_can_load_profile_json(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson('/profile?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'user' => ['name', 'email', 'username'],
                'profile' => ['phone', 'city', 'country_code', 'whatsapp', 'profile_photo_url'],
                'account' => [
                    'account_type',
                    'status',
                    'is_customer',
                    'is_platform_admin',
                    'is_staff',
                    'is_agent_portal',
                ],
                'update_url',
                'supported_fields',
            ])
            ->assertJsonPath('account.is_customer', false)
            ->assertJsonPath('account.is_platform_admin', true);
    }

    public function test_staff_can_load_and_update_profile_json_without_email_change(): void
    {
        $staff = User::factory()->staff()->create([
            'username' => 'staff.profile.qa',
            'email' => 'staff.profile.qa@example.test',
        ]);
        $staff->profile()->firstOrCreate([])->update(['city' => 'Karachi']);

        $this->actingAs($staff)
            ->getJson('/profile?format=json')
            ->assertOk()
            ->assertJsonPath('account.is_staff', true)
            ->assertJsonPath('profile.city', 'Karachi');

        $this->actingAs($staff)
            ->patchJson('/profile?format=json', [
                'name' => $staff->name,
                'email' => $staff->email,
                'username' => $staff->username,
                'city' => 'Islamabad',
                'phone' => '+92-300-1112233',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('profile.profile.city', 'Islamabad');

        $this->assertSame('Islamabad', $staff->fresh()->profile?->city);
        $this->assertNotNull($staff->fresh()->email_verified_at);
    }

    public function test_agent_can_load_profile_json(): void
    {
        $agent = User::factory()->agent()->create([
            'username' => 'agent.profile.qa',
        ]);

        $this->actingAs($agent)
            ->getJson('/profile?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('account.is_agent_portal', true)
            ->assertJsonPath('account.is_customer', false);
    }

    public function test_customer_profile_json_contract_is_preserved(): void
    {
        $customer = User::factory()->customer()->create([
            'username' => 'customer.profile.qa',
        ]);

        $this->actingAs($customer)
            ->getJson('/profile?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('account.is_customer', true)
            ->assertJsonStructure([
                'user' => ['name', 'email', 'username', 'email_verified'],
                'profile' => ['phone', 'city', 'country_code'],
                'countries',
                'update_url',
                'password_update_url',
                'supported_fields',
            ]);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
