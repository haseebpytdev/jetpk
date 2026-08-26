<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Sabre\SabreSandboxQaLifecycleGuard;
use App\Support\Suppliers\SupplierPublicRoutingGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPublicRoutingAndSandboxQaGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_connection_is_excluded_from_public_fanout(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'settings' => [
                'public_customer_routing' => false,
                'production_default_routing' => false,
                'qa_sandbox_only' => true,
            ],
        ]);

        $this->assertTrue(SupplierPublicRoutingGuard::shouldSkipForChannel($connection, 'public_guest'));
        $this->assertTrue(SupplierPublicRoutingGuard::shouldSkipForChannel($connection, 'agent'));
        $this->assertFalse(SupplierPublicRoutingGuard::shouldSkipForChannel($connection, 'admin_qa_sandbox'));
    }

    public function test_live_connection_remains_public_fanout_eligible(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.platform.sabre.com',
        ]);

        $this->assertFalse(SupplierPublicRoutingGuard::shouldSkipForChannel($connection, 'public_guest'));
    }

    public function test_sandbox_qa_guard_blocks_production_host_and_live_environment(): void
    {
        $liveHostSandboxEnv = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.platform.sabre.com',
            'name' => 'bad-sandbox',
        ]);
        $blocked = SabreSandboxQaLifecycleGuard::assertSandboxQaAllowed($liveHostSandboxEnv);
        $this->assertFalse($blocked['allowed']);
        $this->assertSame('resolved_host_is_production_sabre', $blocked['block_reason']);

        $liveEnv = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'live-row',
        ]);
        $blockedLive = SabreSandboxQaLifecycleGuard::assertSandboxQaAllowed($liveEnv);
        $this->assertFalse($blockedLive['allowed']);
        $this->assertSame('connection_environment_production', $blockedLive['block_reason']);
    }

    public function test_sandbox_qa_guard_allows_cert_host_sandbox_connection(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'sabre-sandbox-qa',
        ]);
        $guard = SabreSandboxQaLifecycleGuard::assertSandboxQaAllowed($connection);
        $this->assertTrue($guard['allowed']);
        $this->assertSame('non_production', $guard['host_classification']);
        $this->assertFalse($guard['production_sabre_host_selected']);
    }

    public function test_sandbox_qa_guard_blocks_forbidden_live_connection_id(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'sabre-sandbox-qa',
        ]);
        $guard = SabreSandboxQaLifecycleGuard::assertSandboxQaAllowed($connection, $connection->id);
        $this->assertFalse($guard['allowed']);
        $this->assertSame('live_production_connection_selected_for_sandbox_qa', $guard['block_reason']);
    }
}
