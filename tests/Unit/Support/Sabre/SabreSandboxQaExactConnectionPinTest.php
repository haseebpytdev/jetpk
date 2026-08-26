<?php

namespace Tests\Unit\Support\Sabre;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Sabre\SabreSandboxQaConnectionPin;
use App\Support\Sabre\SabreSandboxQaLifecycleGuard;
use App\Support\Suppliers\SupplierPublicRoutingGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreSandboxQaExactConnectionPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_live_eligible_and_sandbox_skipped(): void
    {
        $live = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.platform.sabre.com',
            'name' => 'live-sabre',
        ]);
        $sandbox = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'sabre-sandbox-qa',
            'settings' => [
                'qa_sandbox_only' => true,
                'public_customer_routing' => false,
                'production_default_routing' => false,
            ],
        ]);

        $this->assertFalse(SupplierPublicRoutingGuard::shouldSkipForChannel($live, 'public_guest'));
        $this->assertTrue(SupplierPublicRoutingGuard::shouldSkipForChannel($sandbox, 'public_guest'));
    }

    public function test_exact_pin_allows_only_target_sandbox_row(): void
    {
        $live = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'base_url' => 'https://api.platform.sabre.com',
            'name' => 'live-sabre',
        ]);
        $sandboxA = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'sabre-sandbox-qa',
            'settings' => [
                'qa_sandbox_only' => true,
                'public_customer_routing' => false,
            ],
        ]);
        $sandboxB = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'other-sandbox',
            'settings' => [
                'qa_sandbox_only' => true,
                'public_customer_routing' => false,
            ],
        ]);

        $ok = SabreSandboxQaConnectionPin::resolveExact($sandboxA->id, $live->id);
        $this->assertTrue($ok['allowed']);
        $this->assertSame(1, $ok['connection_count']);
        $this->assertFalse($ok['live_connection_eligible']);

        $liveBlocked = SabreSandboxQaConnectionPin::resolveExact($live->id, $live->id);
        $this->assertFalse($liveBlocked['allowed']);
        $this->assertTrue($liveBlocked['live_connection_eligible']);

        $other = SabreSandboxQaConnectionPin::resolveExact($sandboxB->id, $live->id);
        $this->assertTrue($other['allowed']);
        $this->assertSame($sandboxB->id, $other['connection']->id);
    }

    public function test_sandbox_with_production_host_blocked_for_pnr_and_cancel(): void
    {
        $bad = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.platform.sabre.com',
            'name' => 'misconfigured',
            'settings' => [
                'qa_sandbox_only' => true,
                'public_customer_routing' => false,
            ],
        ]);

        $pnr = SabreSandboxQaLifecycleGuard::assertSandboxQaPnrCreateAllowed($bad);
        $this->assertFalse($pnr['allowed']);

        $cancel = SabreSandboxQaLifecycleGuard::assertSandboxQaCancelAllowed($bad);
        $this->assertFalse($cancel['allowed']);
    }

    public function test_live_target_in_sandbox_qa_blocked(): void
    {
        $live = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'base_url' => 'https://api.platform.sabre.com',
            'name' => 'live-sabre',
        ]);

        $guard = SabreSandboxQaLifecycleGuard::assertSandboxQaPnrCreateAllowed($live, $live->id);
        $this->assertFalse($guard['allowed']);
    }

    public function test_valid_cert_sandbox_pnr_create_allowed(): void
    {
        $live = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'base_url' => 'https://api.platform.sabre.com',
            'name' => 'live-sabre',
        ]);
        $sandbox = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'sabre-sandbox-qa',
            'settings' => [
                'qa_sandbox_only' => true,
                'public_customer_routing' => false,
            ],
        ]);

        $guard = SabreSandboxQaLifecycleGuard::assertSandboxQaPnrCreateAllowed($sandbox, $live->id);
        $this->assertTrue($guard['allowed']);
        $this->assertSame('non_production', $guard['host_classification']);
        $this->assertFalse($guard['production_sabre_host_selected']);
    }

    public function test_two_sandbox_rows_pin_returns_only_requested_id(): void
    {
        $a = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'sabre-sandbox-qa',
            'settings' => [
                'qa_sandbox_only' => true,
                'public_customer_routing' => false,
            ],
        ]);
        $b = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'name' => 'other-sandbox',
            'settings' => [
                'qa_sandbox_only' => true,
                'public_customer_routing' => false,
            ],
        ]);

        $pinA = SabreSandboxQaConnectionPin::resolveExact($a->id);
        $pinB = SabreSandboxQaConnectionPin::resolveExact($b->id);
        $this->assertTrue($pinA['allowed']);
        $this->assertTrue($pinB['allowed']);
        $this->assertSame(1, $pinA['connection_count']);
        $this->assertSame(1, $pinB['connection_count']);
        $this->assertSame($a->id, $pinA['connection']->id);
        $this->assertSame($b->id, $pinB['connection']->id);
    }
}
