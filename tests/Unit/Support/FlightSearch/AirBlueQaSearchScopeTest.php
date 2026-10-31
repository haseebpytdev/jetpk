<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Enums\AccountType;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\FlightSearch\AirBlueQaSearchScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirBlueQaSearchScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeConnection(array $overrides = []): SupplierConnection
    {
        $defaults = [
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'is_active' => true,
            'status' => 'active',
            'base_url' => 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx',
            'credentials' => [
                'protocol_version' => '3.0',
                'service_target' => 'Test',
                'client_id' => 'x',
                'client_key' => 'x',
                'agent_type' => 'x',
                'agent_id' => 'x',
                'agent_password' => 'x',
            ],
        ];

        $connection = new SupplierConnection(array_merge($defaults, $overrides));
        $connection->id = (int) ($overrides['id'] ?? 23);

        return $connection;
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }

    public function test_allows_pinned_sandbox_v3_test_connection(): void
    {
        config(['ota.qa_staff_airblue.connection_id' => 23]);

        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $result = app(AirBlueQaSearchScope::class)->evaluate($this->makeConnection(), $admin);

        $this->assertTrue($result['allowed']);
        $this->assertSame(23, $result['connection_id']);
        $this->assertSame('otatest4.zapways.com', $result['test_host']);
        $this->assertSame('3.0', $result['protocol']);
    }

    public function test_rejects_wrong_connection_id(): void
    {
        config(['ota.qa_staff_airblue.connection_id' => 23]);

        $result = app(AirBlueQaSearchScope::class)->evaluate($this->makeConnection(['id' => 22]));
        $this->assertFalse($result['allowed']);
        $this->assertSame('connection_id_mismatch', $result['reason']);
    }

    public function test_rejects_live_environment(): void
    {
        config(['ota.qa_staff_airblue.connection_id' => 23]);

        $result = app(AirBlueQaSearchScope::class)->evaluate($this->makeConnection([
            'environment' => SupplierEnvironment::Live,
        ]));
        $this->assertFalse($result['allowed']);
        $this->assertSame('environment_not_test', $result['reason']);
    }

    public function test_rejects_wrong_host(): void
    {
        config(['ota.qa_staff_airblue.connection_id' => 23]);

        $result = app(AirBlueQaSearchScope::class)->evaluate($this->makeConnection([
            'base_url' => 'https://api.zapways.com/v3.0/OTAAPI.asmx',
        ]));
        $this->assertFalse($result['allowed']);
        $this->assertSame('host_not_approved_test', $result['reason']);
    }

    public function test_rejects_wrong_protocol(): void
    {
        config(['ota.qa_staff_airblue.connection_id' => 23]);

        $result = app(AirBlueQaSearchScope::class)->evaluate($this->makeConnection([
            'credentials' => [
                'protocol_version' => '2.0',
                'service_target' => 'Test',
            ],
        ]));
        $this->assertFalse($result['allowed']);
        $this->assertSame('protocol_not_v3', $result['reason']);
    }

    public function test_rejects_customer_actor(): void
    {
        config(['ota.qa_staff_airblue.connection_id' => 23]);

        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $result = app(AirBlueQaSearchScope::class)->evaluate($this->makeConnection(), $customer);
        $this->assertFalse($result['allowed']);
        $this->assertSame('unauthorized_actor', $result['reason']);
    }

    public function test_rejects_when_supplier_search_module_disabled(): void
    {
        config(['ota.qa_staff_airblue.connection_id' => 23]);
        $this->planModuleOff('supplier_search');

        $result = app(AirBlueQaSearchScope::class)->evaluate($this->makeConnection());
        $this->assertFalse($result['allowed']);
        $this->assertSame('supplier_search_module_disabled', $result['reason']);
    }

    public function test_rejects_non_airblue_provider(): void
    {
        config(['ota.qa_staff_airblue.connection_id' => 23]);

        $result = app(AirBlueQaSearchScope::class)->evaluate($this->makeConnection([
            'provider' => SupplierProvider::Sabre,
        ]));
        $this->assertFalse($result['allowed']);
        $this->assertSame('provider_not_airblue', $result['reason']);
    }
}
