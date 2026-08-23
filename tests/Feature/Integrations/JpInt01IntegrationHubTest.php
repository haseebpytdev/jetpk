<?php

namespace Tests\Feature\Integrations;

use App\Enums\AccountType;
use App\Enums\IntegrationHealthStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\IntegrationHealthCheck;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Integrations\AbhiPayDiagnosticPaymentService;
use App\Services\Integrations\IntegrationHubService;
use App\Services\Integrations\IntegrationManagerResolver;
use App\Services\Payments\PaymentGatewaySettingsService;
use App\Support\Integrations\IntegrationAuthorization;
use App\Support\Integrations\IntegrationRegistry;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JpInt01IntegrationHubTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $this->agency->id,
        ]);
    }

    public function test_registry_includes_real_providers_only_as_installed_when_adapters_exist(): void
    {
        $codes = array_map(static fn ($d) => $d->code, IntegrationRegistry::all());
        $this->assertContains('sabre', $codes);
        $this->assertContains('iati', $codes);
        $this->assertContains('abhipay', $codes);

        $abhi = IntegrationRegistry::find('abhipay');
        $this->assertTrue($abhi?->adapterInstalled);
        $this->assertTrue($abhi?->supportsTestTransaction);

        $hotelbeds = IntegrationRegistry::find('hotelbeds');
        $this->assertFalse($hotelbeds?->canActivateRuntime);
    }

    public function test_hub_overview_returns_metrics_and_cards(): void
    {
        $hub = app(IntegrationHubService::class)->overview(null, $this->agency->id);
        $this->assertArrayHasKey('metrics', $hub);
        $this->assertArrayHasKey('integrations', $hub);
        $this->assertGreaterThan(0, $hub['metrics']['total']);
        $this->assertNotEmpty($hub['categories']);
    }

    public function test_hub_survives_when_one_provider_manager_throws(): void
    {
        $codes = array_map(static fn ($d) => $d->code, IntegrationRegistry::all());
        $this->assertGreaterThanOrEqual(10, count($codes));
        $this->assertContains('abhipay', $codes);

        $inner = app(IntegrationManagerResolver::class);

        $throwingSabre = new class implements \App\Contracts\Integrations\IntegrationManager {
            public function code(): string
            {
                return 'sabre';
            }

            public function getStatus(?int $agencyId = null): \App\Enums\IntegrationOperationalStatus
            {
                throw new \Error('Undefined constant App\\Enums\\SupplierConnectionStatus::Disabled');
            }

            public function getConfigurationSummary(?int $agencyId = null): array
            {
                throw new \Error('Undefined constant App\\Enums\\SupplierConnectionStatus::Disabled');
            }

            public function getSettingsDefinition(): array
            {
                return [];
            }

            public function saveSettings(\App\Models\User $actor, array $data, ?int $agencyId = null): array
            {
                return [];
            }

            public function testConnection(\App\Models\User $actor, ?int $agencyId = null): array
            {
                return [];
            }

            public function activate(\App\Models\User $actor, ?int $agencyId = null): void {}

            public function deactivate(\App\Models\User $actor, ?int $agencyId = null): void {}

            public function getHealth(?int $agencyId = null): array
            {
                return [];
            }

            public function supportsTestTransaction(): bool
            {
                return false;
            }

            public function createTestTransaction(\App\Models\User $actor, array $options = [], ?int $agencyId = null): array
            {
                return [];
            }
        };

        $resolver = new class($inner, $throwingSabre) extends IntegrationManagerResolver {
            public function __construct(
                private readonly IntegrationManagerResolver $inner,
                private readonly \App\Contracts\Integrations\IntegrationManager $sabreOverride,
            ) {}

            public function forDefinition(\App\Support\Integrations\IntegrationDefinition $definition): \App\Contracts\Integrations\IntegrationManager
            {
                if ($definition->code === 'sabre') {
                    return $this->sabreOverride;
                }

                return $this->inner->forDefinition($definition);
            }
        };

        $hub = (new IntegrationHubService($resolver))->overview(null, $this->agency->id);

        $this->assertSame(count($codes), $hub['metrics']['total']);
        $this->assertCount(count($codes), $hub['integrations']);
        $this->assertGreaterThanOrEqual(1, $hub['metrics']['needs_attention']);

        $sabre = collect($hub['integrations'])->firstWhere('code', 'sabre');
        $this->assertNotNull($sabre);
        $this->assertTrue((bool) ($sabre['resolution_error'] ?? false));
        $this->assertSame('degraded', $sabre['status']);
        $this->assertTrue((bool) ($sabre['needs_attention'] ?? false));
        $encoded = (string) json_encode($sabre);
        $this->assertStringNotContainsString('SupplierConnectionStatus', $encoded);
        $this->assertStringNotContainsString('Undefined constant', $encoded);

        $others = collect($hub['integrations'])->where('code', '!=', 'sabre');
        $this->assertTrue($others->isNotEmpty());
        $this->assertTrue($others->contains(fn (array $row) => ! ($row['resolution_error'] ?? false)));

        $abhipay = collect($hub['integrations'])->firstWhere('code', 'abhipay');
        $this->assertNotNull($abhipay);
        $this->assertFalse((bool) ($abhipay['resolution_error'] ?? false));
    }

    public function test_inactive_supplier_status_maps_without_undefined_disabled_case(): void
    {
        $manager = app(IntegrationManagerResolver::class)->resolve('sabre');
        // Must not throw Error for missing SupplierConnectionStatus::Disabled
        $status = $manager->getStatus($this->agency->id);
        $this->assertInstanceOf(\App\Enums\IntegrationOperationalStatus::class, $status);
    }

    public function test_legacy_api_settings_html_redirects_to_integrations(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.api-settings'))
            ->assertRedirect('/admin/dashboard/integrations');
    }

    public function test_abhipay_visible_when_unconfigured(): void
    {
        PaymentGateway::query()->where('code', 'abhipay')->delete();

        $hub = app(IntegrationHubService::class)->overview('payments', $this->agency->id);
        $abhi = collect($hub['integrations'])->firstWhere('code', 'abhipay');
        $this->assertNotNull($abhi);
        $this->assertSame('abhipay', $abhi['code']);
        $this->assertFalse((bool) ($abhi['configured'] ?? true));
    }

    public function test_rbac_platform_admin_can_view_integrations_route(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.integrations.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['hub' => ['metrics', 'integrations'], 'permissions']);
    }

    public function test_rbac_non_admin_forbidden(): void
    {
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($staff)
            ->getJson(route('admin.integrations.index', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_abhipay_settings_save_encrypts_and_masks_secrets(): void
    {
        $this->actingAs($this->admin)
            ->patchJson(route('admin.integrations.update', ['code' => 'abhipay', 'format' => 'json']), [
                'environment' => 'test',
                'is_active' => false,
                'merchant_id' => 'MERCHANT-SYNTH-01',
                'merchant_secret_key' => 'super-secret-value-never-log',
                'base_url' => 'https://api.abhipay.com.pk/api/v3',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $gateway = PaymentGateway::query()->where('agency_id', $this->agency->id)->where('code', 'abhipay')->firstOrFail();
        $this->assertTrue($gateway->isConfigured());
        $this->assertSame('•••••••••• configured', app(PaymentGatewaySettingsService::class)->presentAbhiPay($gateway)['merchant_secret_masked']);

        $json = $this->actingAs($this->admin)
            ->getJson(route('admin.integrations.show', ['code' => 'abhipay', 'format' => 'json']))
            ->assertOk()
            ->json();

        $encoded = json_encode($json);
        $this->assertStringNotContainsString('super-secret-value-never-log', (string) $encoded);
        $this->assertStringNotContainsString('"merchant_secret_key":"super', (string) $encoded);

        $audit = AuditLog::query()->where('action', 'payment_gateway.abhipay.secret_replaced')->latest('id')->first();
        $this->assertNotNull($audit);
        $props = json_encode($audit->properties);
        $this->assertStringNotContainsString('super-secret-value-never-log', (string) $props);
    }

    public function test_blank_secret_retains_existing_value(): void
    {
        $service = app(PaymentGatewaySettingsService::class);
        $service->saveAbhiPay($this->agency, $this->admin, [
            'environment' => 'test',
            'is_active' => false,
            'merchant_id' => 'KEEP-ME',
            'merchant_secret_key' => 'original-secret-value',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
        ]);

        $service->saveAbhiPay($this->agency, $this->admin, [
            'environment' => 'test',
            'is_active' => false,
            'merchant_id' => 'KEEP-ME',
            'merchant_secret_key' => null,
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
        ]);

        $gateway = PaymentGateway::query()->where('agency_id', $this->agency->id)->where('code', 'abhipay')->firstOrFail();
        $this->assertSame('original-secret-value', $gateway->merchant_secret_key);
    }

    public function test_abhipay_connection_test_does_not_create_order(): void
    {
        app(PaymentGatewaySettingsService::class)->saveAbhiPay($this->agency, $this->admin, [
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-TEST',
            'merchant_secret_key' => 'secret-test-value',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
        ]);

        Http::fake([
            '*/orders/by-rrn/*' => Http::response(['payload' => null], 404),
            '*/orders' => Http::response(['should' => 'not-be-called'], 500),
        ]);

        $before = PaymentTransaction::query()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.integrations.test-connection', ['code' => 'abhipay', 'format' => 'json']))
            ->assertOk()
            ->assertJsonPath('result.status', 'CONNECTED')
            ->assertJsonPath('result.ok', true);

        $this->assertSame($before, PaymentTransaction::query()->count());
        $this->assertDatabaseHas('integration_health_checks', [
            'provider' => 'abhipay',
            'test_type' => 'connection',
            'status' => IntegrationHealthStatus::Healthy->value,
        ]);

        Http::assertSentCount(1);
    }

    public function test_abhipay_test_payment_blocked_in_live_environment(): void
    {
        app(PaymentGatewaySettingsService::class)->saveAbhiPay($this->agency, $this->admin, [
            'environment' => 'live',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-LIVE',
            'merchant_secret_key' => 'secret-live-value',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
        ]);

        IntegrationHealthCheck::query()->create([
            'provider' => 'abhipay',
            'test_type' => 'connection',
            'status' => IntegrationHealthStatus::Healthy,
            'tested_at' => now(),
            'tested_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.integrations.test-payment', ['code' => 'abhipay', 'format' => 'json']), [
                'confirm' => true,
                'amount' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_abhipay_test_payment_creates_integration_test_purpose_in_test_mode(): void
    {
        app(PaymentGatewaySettingsService::class)->saveAbhiPay($this->agency, $this->admin, [
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-TEST',
            'merchant_secret_key' => 'secret-test-value',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
        ]);

        IntegrationHealthCheck::query()->create([
            'provider' => 'abhipay',
            'test_type' => 'connection',
            'status' => IntegrationHealthStatus::Healthy,
            'tested_at' => now(),
            'tested_by' => $this->admin->id,
            'environment' => 'test',
        ]);

        Http::fake([
            '*/orders' => Http::response([
                'resultCode' => '00000',
                'payload' => [
                    'orderId' => 'ORD-DIAG-1',
                    'paymentUrl' => 'https://pay.example.test/checkout/diag',
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.integrations.test-payment', ['code' => 'abhipay', 'format' => 'json']), [
                'confirm' => true,
                'amount' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('result.purpose', AbhiPayDiagnosticPaymentService::PURPOSE)
            ->assertJsonPath('result.amount', '1.00');

        $txn = PaymentTransaction::query()->latest('id')->first();
        $this->assertNotNull($txn);
        $this->assertSame(AbhiPayDiagnosticPaymentService::PURPOSE, $txn->purpose);
        $this->assertNull($txn->booking_id);
        $this->assertSame('1.00', (string) $txn->amount);
    }

    public function test_draft_custom_api_activation_blocked(): void
    {
        $manager = app(IntegrationManagerResolver::class)->resolve('hotelbeds');
        $this->expectException(\RuntimeException::class);
        $manager->activate($this->admin, $this->agency->id);
    }

    public function test_legacy_payment_settings_redirects_to_integrations(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.payments.index'))
            ->assertRedirect();

        $target = $this->actingAs($this->admin)->get(route('admin.settings.payments.index'))->headers->get('Location');
        $this->assertStringContainsString('/admin/dashboard/integrations', (string) $target);
        $this->assertStringContainsString('provider=abhipay', (string) $target);
    }

    public function test_enable_disable_affects_checkout_availability(): void
    {
        app(PaymentGatewaySettingsService::class)->saveAbhiPay($this->agency, $this->admin, [
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-ON',
            'merchant_secret_key' => 'secret-on-value',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
        ]);

        $gateway = PaymentGateway::query()->where('agency_id', $this->agency->id)->where('code', 'abhipay')->firstOrFail();
        $this->assertTrue($gateway->isAvailableForCheckout());

        $this->actingAs($this->admin)
            ->postJson(route('admin.integrations.deactivate', ['code' => 'abhipay', 'format' => 'json']))
            ->assertOk();

        $gateway->refresh();
        $this->assertFalse($gateway->is_active);
        $this->assertFalse($gateway->isAvailableForCheckout());
    }

    public function test_authorization_keys_exist(): void
    {
        foreach (IntegrationAuthorization::allKeys() as $key) {
            $this->assertTrue(IntegrationAuthorization::can($this->admin, $key));
        }
    }
}
