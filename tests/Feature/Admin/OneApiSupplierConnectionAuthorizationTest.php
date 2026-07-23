<?php

namespace Tests\Feature\Admin;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Support\OneApiReadinessService;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class OneApiSupplierConnectionAuthorizationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unauthenticated_user_denied_api_settings(): void
    {
        $this->get(route('admin.api-settings'))->assertRedirect();
    }

    public function test_agency_admin_denied_api_settings_index(): void
    {
        $legacy = $this->legacyAgencyAdminFromSeed();
        $this->actingAs($legacy)->get(route('admin.api-settings'))->assertForbidden();
    }

    public function test_authorized_platform_admin_can_view_create_form(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->get(route('admin.api-settings.create', ['provider' => 'one_api']))->assertOk();
    }

    public function test_platform_admin_can_create_and_update_one_api_connection(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::factory()->create();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $response = $this->actingAs($admin)->post(route('admin.api-settings.store'), [
            'provider' => 'one_api',
            'name' => 'One API Phase 9',
            'environment' => 'sandbox',
            'status' => 'active',
            'is_active' => '1',
            'agency_id' => $agency->id,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'AGENT',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => '',
            ],
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $connection = SupplierConnection::query()->where('name', 'One API Phase 9')->first();
        $this->assertNotNull($connection);
        $connection = $connection ?? SupplierConnection::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.api-settings.edit', $connection))->assertOk();

        $this->actingAs($admin)->patch(route('admin.api-settings.update', $connection), [
            'provider' => 'one_api',
            'name' => 'One API Phase 9 Updated',
            'environment' => 'sandbox',
            'status' => 'active',
            'is_active' => true,
            'agency_id' => $agency->id,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => '',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => '',
            ],
        ])->assertRedirect();

        $connection->refresh();
        $this->assertSame('ONE_API_TEST_PASSWORD', $connection->credentials['password']);
        $this->actingAs($admin)->get(route('admin.api-settings.edit', $connection))
            ->assertOk()
            ->assertDontSee('ONE_API_TEST_PASSWORD');
    }

    public function test_blank_soap_url_readiness_shows_soap_blocked(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => '',
            ],
        ]);
        $dims = app(OneApiReadinessService::class)->dimensions($connection);
        $this->assertFalse($dims['SOAP_endpoint_present']['ready']);
    }

    public function test_test_connection_does_not_invoke_booking_or_price_services(): void
    {
        $admin = $this->platformAdmin();
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'agency_id' => $admin->current_agency_id,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
            ],
        ]);

        $this->actingAs($admin)->patch(route('admin.api-settings.test', $connection))
            ->assertRedirect()
            ->assertSessionHas('test_result');
    }
}
