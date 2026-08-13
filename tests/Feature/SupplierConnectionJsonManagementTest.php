<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\User;
use App\Support\Suppliers\SabreCapabilityTruth;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierConnectionJsonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_list_create_toggle_test_and_delete_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $list = $this->actingAs($admin)->getJson('/admin/api-settings?format=json');
        $list->assertOk();

        $create = $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => SupplierProvider::Duffel->value,
            'name' => 'QA Duffel sandbox',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => ['access_token' => 'qa-fixture-token'],
            'settings_json' => '{}',
        ]);
        $create->assertOk()->assertJsonPath('ok', true);
        $id = (string) $create->json('connection.id');
        $this->assertNotSame('', $id);
        $create->assertJsonMissing(['qa-fixture-token']);

        $this->actingAs($admin)
            ->patchJson('/admin/api-settings/'.$id.'/toggle-status?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $test = $this->actingAs($admin)->patchJson('/admin/api-settings/'.$id.'/test?format=json');
        $test->assertOk()->assertJsonPath('ok', true);
        $encoded = $test->getContent();
        $this->assertStringNotContainsString('qa-fixture-token', $encoded);

        $this->actingAs($admin)
            ->deleteJson('/admin/api-settings/'.$id.'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_staff_cannot_mutate_api_connections(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->postJson('/admin/api-settings?format=json', [
                'provider' => SupplierProvider::Duffel->value,
                'name' => 'Forbidden',
                'environment' => SupplierEnvironment::Sandbox->value,
                'status' => SupplierConnectionStatus::Inactive->value,
                'credentials' => ['access_token' => 'x'],
            ])
            ->assertForbidden();
    }

    public function test_sabre_ndc_supported_comes_from_adapters_not_provider_label_alone(): void
    {
        $this->assertTrue(SabreCapabilityTruth::gdsSupported());
        $this->assertTrue(SabreCapabilityTruth::ndcSupported());
    }

    protected function platformAdmin(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
