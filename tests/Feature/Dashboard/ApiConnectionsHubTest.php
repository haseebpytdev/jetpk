<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiConnectionsHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_api_settings_index_redirects_to_canonical_api_connections_hub(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/api-settings')
            ->assertRedirect('/admin/dashboard/api-connections');
    }

    public function test_json_list_includes_provider_catalog_and_provider_cards(): void
    {
        [$admin] = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/admin/api-settings?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $providers = $response->json('providers');
        $this->assertIsArray($providers);
        $this->assertNotEmpty($providers);
        $this->assertArrayHasKey('key', $providers[0]);
        $this->assertArrayHasKey('label', $providers[0]);
        $this->assertArrayHasKey('installed', $providers[0]);

        $cards = $response->json('providerCards');
        $this->assertIsArray($cards);
        $this->assertNotEmpty($cards);
        $this->assertArrayHasKey('key', $cards[0]);
        $this->assertArrayHasKey('label', $cards[0]);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('password', strtolower((string) json_encode($response->json('connections'))));
        $this->assertIsString($payload);
    }

    public function test_json_connection_payload_includes_audit_and_advanced_shapes(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'QA Audit Contract Connection',
            'display_name' => 'QA Audit Contract Connection',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
            'credentials' => [
                'client_id' => 'qa-client-id',
                'client_secret' => 'qa-plaintext-secret',
            ],
            'settings' => [
                'api_channel' => 'gds',
                'notes' => 'qa-safe-setting',
            ],
        ]);

        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $admin->id,
            'action' => 'updated',
            'auditable_type' => SupplierConnection::class,
            'auditable_id' => $connection->id,
            'properties' => [
                'name' => ['old' => 'Old Name', 'new' => 'QA Audit Contract Connection'],
                'client_secret' => 'must-not-leak',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/api-settings?format=json')
            ->assertOk();

        $connections = collect($response->json('connections') ?? []);
        $first = $connections->firstWhere('id', (string) $connection->id) ?? $connections->first();
        $this->assertNotNull($first);
        $this->assertArrayHasKey('maskedCredentials', $first);
        $this->assertArrayHasKey('advanced', $first);
        $this->assertArrayHasKey('audit', $first);
        $this->assertIsArray($first['advanced']['fields'] ?? null);
        $this->assertNotEmpty($first['advanced']['fields'] ?? []);
        $this->assertIsArray($first['audit']['history'] ?? null);
        $this->assertNotEmpty($first['audit']['history'] ?? []);

        $encoded = strtolower((string) json_encode($first));
        $this->assertStringNotContainsString('"password":"', $encoded);
        $this->assertStringNotContainsString('"client_secret":"qa-plaintext-secret"', $encoded);
        $this->assertStringNotContainsString('must-not-leak', $encoded);
    }

    public function test_admin_navigation_exposes_single_next_api_connections_entry(): void
    {
        [$admin] = $this->platformAdmin();

        $payload = app(BackOfficeCapabilitiesPresenter::class)->present($admin, 'admin');
        $navigation = collect($payload['navigation'] ?? []);
        $api = $navigation->firstWhere('key', 'api-connections');

        $this->assertNotNull($api);
        $this->assertSame('/api-connections', $api['href']);
        $this->assertSame('dashboard', $api['target'] ?? null);
        $this->assertSame(1, $navigation->where('key', 'api-connections')->count());
        $this->assertNull($navigation->firstWhere('key', 'api-settings'));
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->first();
        if ($admin === null) {
            $this->seed(OtaFoundationSeeder::class);
            $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        }

        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
