<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SupplierRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_uninstalled_catalogue_provider_is_not_a_configured_failure(): void
    {
        $this->assertSame(
            SupplierRegistry::ADAPTER_NOT_INSTALLED,
            SupplierRegistry::stateForUnprovisioned(SupplierProvider::Amadeus),
        );
        $this->assertSame(
            SupplierRegistry::ADAPTER_INSTALLED,
            SupplierRegistry::stateForUnprovisioned(SupplierProvider::Sabre),
        );
    }

    public function test_connection_states_follow_credentials_and_status(): void
    {
        $agency = Agency::factory()->create();

        $empty = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre empty',
            'display_name' => 'Sabre empty',
            'credentials' => null,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
        ]);
        $this->assertSame(SupplierRegistry::CONNECTION_NOT_CONFIGURED, SupplierRegistry::stateForConnection($empty));

        $testing = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre testing',
            'display_name' => 'Sabre testing',
            'credentials' => ['username' => 'masked-only'],
            'status' => SupplierConnectionStatus::Testing,
            'is_active' => false,
        ]);
        $this->assertSame(SupplierRegistry::PENDING_ACTIVATION, SupplierRegistry::stateForConnection($testing));

        $enabled = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre enabled',
            'display_name' => 'Sabre enabled',
            'credentials' => ['username' => 'masked-only'],
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
        ]);
        $this->assertSame(SupplierRegistry::CONFIGURED_ENABLED, SupplierRegistry::stateForConnection($enabled));

        $disabled = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre disabled',
            'display_name' => 'Sabre disabled',
            'credentials' => ['username' => 'masked-only'],
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
        ]);
        $this->assertSame(SupplierRegistry::CONFIGURED_DISABLED, SupplierRegistry::stateForConnection($disabled));
    }
}
