<?php

namespace Tests\Unit\Integrations;

use App\Enums\AccountType;
use App\Enums\IntegrationHealthStatus;
use App\Models\Agency;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Integrations\IntegrationHealthRecorder;
use App\Services\Integrations\IntegrationTestThrottle;
use App\Services\Integrations\Managers\AbhiPayIntegrationManager;
use App\Services\Payments\PaymentGatewaySettingsService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class JpInt01HealthAndThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Cache::flush();
        $this->agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $this->agency->id,
        ]);
    }

    public function test_health_auth_failure_classification(): void
    {
        app(PaymentGatewaySettingsService::class)->saveAbhiPay($this->agency, $this->admin, [
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'BAD',
            'merchant_secret_key' => 'bad-secret',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
        ]);

        Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

        $result = app(AbhiPayIntegrationManager::class)->testConnection($this->admin, $this->agency->id);
        $this->assertFalse($result['ok']);
        $this->assertSame('AUTHENTICATION_FAILED', $result['status']);
        $this->assertDatabaseHas('integration_health_checks', [
            'provider' => 'abhipay',
            'status' => IntegrationHealthStatus::AuthFailed->value,
        ]);
    }

    public function test_health_network_failure_classification(): void
    {
        app(PaymentGatewaySettingsService::class)->saveAbhiPay($this->agency, $this->admin, [
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'NET',
            'merchant_secret_key' => 'net-secret',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
        ]);

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('boom'));

        $result = app(AbhiPayIntegrationManager::class)->testConnection($this->admin, $this->agency->id);
        $this->assertFalse($result['ok']);
        $this->assertSame('NETWORK_ERROR', $result['status']);
    }

    public function test_health_recorder_sanitizes_secrets(): void
    {
        $row = app(IntegrationHealthRecorder::class)->record(
            provider: 'abhipay',
            testType: 'connection',
            status: IntegrationHealthStatus::ProviderError,
            actor: $this->admin,
            message: 'Authorization: Bearer abcdefghijklmnopqrstuvwxyz012345 failure',
            meta: ['merchant_secret' => 'should-not-persist', 'http_status' => 500],
        );

        $this->assertStringContainsString('[redacted]', (string) $row->sanitized_message);
        $this->assertArrayNotHasKey('merchant_secret', $row->meta ?? []);
    }

    public function test_connection_test_throttle(): void
    {
        $throttle = new IntegrationTestThrottle(30);
        $throttle->mark($this->admin, 'abhipay', 'connection');
        $this->expectException(RuntimeException::class);
        $throttle->assertAllowed($this->admin, 'abhipay', 'connection');
    }
}
