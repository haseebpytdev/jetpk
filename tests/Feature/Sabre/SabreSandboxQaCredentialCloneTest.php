<?php

namespace Tests\Feature\Sabre;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreSandboxQaConnectionProvisioner;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreSandboxQaCredentialCloneTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_authorized_clone_creates_separate_sandbox_row_without_mutating_source(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();

        $live = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'name' => 'live-sabre',
            'base_url' => 'https://api.platform.sabre.com',
            'credentials' => [
                'sign_in' => 'cloneUser',
                'password' => 'cloneSecret',
                'pcc' => 'ABCD',
            ],
            'settings' => ['epr_domain' => 'AA'],
        ]);

        $provisioner = app(SabreSandboxQaConnectionProvisioner::class);
        $hashBefore = $provisioner->sanitizedConfigHash($live);

        $result = $provisioner->ensure(
            agency: $agency,
            alias: 'sabre-sandbox-qa',
            forbiddenLiveConnectionId: $live->id,
            cloneCredentialsFromConnectionId: $live->id,
            activateOnlyIfAuthReady: false,
        );

        $sandbox = $result['connection'];
        $this->assertTrue($result['created']);
        $this->assertNotSame($live->id, $sandbox->id);
        $this->assertSame(SupplierEnvironment::Sandbox, $sandbox->environment);
        $this->assertSame('https://api.cert.platform.sabre.com', rtrim((string) $sandbox->base_url, '/'));
        $this->assertFalse((bool) data_get($sandbox->settings, 'public_customer_routing'));
        $this->assertTrue((bool) data_get($sandbox->settings, 'qa_sandbox_only'));
        $this->assertSame('owner_authorized_clone', $result['credential_source']);

        $liveFresh = $live->fresh();
        $this->assertSame($hashBefore, $provisioner->sanitizedConfigHash($liveFresh));
        $this->assertSame(SupplierEnvironment::Live, $liveFresh->environment);
        $this->assertSame('https://api.platform.sabre.com', rtrim((string) $liveFresh->base_url, '/'));
        $this->assertSame('cloneUser', data_get($liveFresh->credentials, 'sign_in'));
    }

    public function test_clone_refuses_to_overwrite_live_row_when_alias_collides_with_live_id(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();

        $live = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'name' => 'sabre-sandbox-qa',
            'base_url' => 'https://api.platform.sabre.com',
            'credentials' => [
                'sign_in' => 'u',
                'password' => 'p',
                'pcc' => 'PCC1',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(SabreSandboxQaConnectionProvisioner::class)->ensure(
            agency: $agency,
            alias: 'sabre-sandbox-qa',
            forbiddenLiveConnectionId: $live->id,
            cloneCredentialsFromConnectionId: $live->id,
        );
    }
}
