<?php

namespace Tests\Unit\Suppliers\AlHaider;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AlHaider\AlHaiderClient;
use App\Services\Suppliers\AlHaider\AlHaiderManagedTokenRenewalService;
use App\Services\Suppliers\AlHaider\AlHaiderProviderException;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mocked managed-token renewal certification — never hits real Al-Haider login.
 */
class AlHaiderManagedTokenRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('suppliers.al_haider.enabled', true);
        Config::set('suppliers.al_haider.token', '');
        Config::set('suppliers.al_haider.username', '');
        Config::set('suppliers.al_haider.password', '');
        Config::set('suppliers.al_haider.default_base_url', 'https://alhaider.test');
        Config::set('suppliers.al_haider.login_path', '/api/login');
        Config::set('suppliers.al_haider.groups_path', '/api/available/groups');
        Config::set('suppliers.al_haider.token_generation_enabled', false);
        Config::set('suppliers.al_haider.login_lock_seconds', 5);
        Config::set('suppliers.al_haider.login_lock_wait_seconds', 2);
        Config::set('suppliers.al_haider.token_validity_seconds', 31_536_000);
    }

    public function test_a_valid_token_normal_request_sends_zero_logins(): void
    {
        $this->seedManagedConnection([
            'existing_token' => 'valid-managed-token',
            'token_expires_at' => now()->addYear()->toIso8601String(),
            'auto_renew' => '1',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ]);

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        app(AlHaiderClient::class)->listGroups();

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
        $this->assertTrue(app(AlHaiderClient::class)->isConfigured());
    }

    public function test_b_valid_token_401_before_expiry_fails_closed_zero_logins(): void
    {
        $this->seedManagedConnection([
            'existing_token' => 'still-valid-token',
            'token_expires_at' => now()->addMonths(6)->toIso8601String(),
            'auto_renew' => '1',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ]);

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::response(['message' => 'Unauthenticated'], 401),
        ]);

        try {
            app(AlHaiderClient::class)->listGroups();
            $this->fail('Expected fail-closed exception');
        } catch (AlHaiderProviderException $e) {
            $this->assertSame('supplier_auth_token_rejected', $e->errorCode);
        }

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
    }

    public function test_c_expired_token_auto_renew_disabled_zero_logins(): void
    {
        $this->seedManagedConnection([
            'existing_token' => 'expired-token',
            'token_expires_at' => '2000-01-01T00:00:00Z',
            'auto_renew' => '0',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ]);

        Http::fake();

        $this->expectException(AlHaiderProviderException::class);
        try {
            app(AlHaiderClient::class)->listGroups();
        } finally {
            Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
        }
    }

    public function test_d_expired_token_auto_renew_enabled_one_login_persisted(): void
    {
        $connection = $this->seedManagedConnection([
            'existing_token' => 'expired-token',
            'token_expires_at' => '2000-01-01T00:00:00Z',
            'auto_renew' => '1',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ]);

        Http::fake([
            'alhaider.test/api/login' => Http::response(['token' => 'renewed-token-value'], 200),
            'alhaider.test/api/available/groups*' => function ($request) {
                $this->assertSame('Bearer renewed-token-value', $request->header('Authorization')[0] ?? '');

                return Http::response(['groups' => [['id' => 1]]], 200);
            },
        ]);

        app(AlHaiderClient::class)->listGroups();

        Http::assertSentCount(2);
        $connection->refresh();
        $this->assertSame('renewed-token-value', $connection->credentials['existing_token']);
        $this->assertNotEmpty($connection->credentials['token_expires_at']);
        $this->assertSame(1, (int) data_get($connection->settings, 'token_issuance.automatic_generation_count'));
    }

    public function test_e_concurrent_expired_requests_send_one_login(): void
    {
        $connection = $this->seedManagedConnection([
            'existing_token' => 'expired-token',
            'token_expires_at' => '2000-01-01T00:00:00Z',
            'auto_renew' => '1',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ]);

        $loginCount = 0;
        Http::fake([
            'alhaider.test/api/login' => function () use (&$loginCount) {
                $loginCount++;
                usleep(50_000);

                return Http::response(['token' => 'concurrent-renewed-token'], 200);
            },
            'alhaider.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        $service = app(AlHaiderManagedTokenRenewalService::class);
        $a = $service->renewIfExpired($connection);
        $b = $service->renewIfExpired($connection->fresh());

        $this->assertSame(1, $loginCount);
        $this->assertSame('concurrent-renewed-token', $a['token']);
        $this->assertSame('concurrent-renewed-token', $b['token']);
        $this->assertTrue($a['renewed']);
        $this->assertFalse($b['renewed']);
    }

    public function test_f_login_timeout_ambiguous_zero_automatic_retries(): void
    {
        $connection = $this->seedManagedConnection([
            'existing_token' => 'expired-token',
            'token_expires_at' => '2000-01-01T00:00:00Z',
            'auto_renew' => '1',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ]);

        Http::fake([
            'alhaider.test/api/login' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        try {
            app(AlHaiderManagedTokenRenewalService::class)->renewIfExpired($connection);
            $this->fail('Expected ambiguous exception');
        } catch (AlHaiderProviderException $e) {
            $this->assertSame('supplier_auth_ambiguous', $e->errorCode);
        }

        $connection->refresh();
        $this->assertSame(
            AlHaiderManagedTokenRenewalService::GENERATION_STATE_AMBIGUOUS,
            data_get($connection->settings, 'token_issuance.generation_state')
        );

        // Second attempt must not retry login while ambiguous.
        Http::fake([
            'alhaider.test/api/login' => Http::response(['token' => 'should-not-happen'], 200),
        ]);

        try {
            app(AlHaiderManagedTokenRenewalService::class)->renewIfExpired($connection->fresh());
            $this->fail('Expected ambiguous block');
        } catch (AlHaiderProviderException $e) {
            $this->assertSame('supplier_auth_ambiguous', $e->errorCode);
        }

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
    }

    public function test_g_successful_renewal_reuses_stored_token(): void
    {
        $this->seedManagedConnection([
            'existing_token' => 'expired-token',
            'token_expires_at' => '2000-01-01T00:00:00Z',
            'auto_renew' => '1',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ]);

        Http::fake([
            'alhaider.test/api/login' => Http::response(['token' => 'post-renew-token'], 200),
            'alhaider.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        $client = app(AlHaiderClient::class);
        $client->listGroups();
        $client->listGroups();

        $loginSent = 0;
        Http::recorded(function ($request) use (&$loginSent): void {
            if (str_contains((string) $request->url(), '/api/login')) {
                $loginSent++;
            }
        });
        $this->assertSame(1, $loginSent);
    }

    public function test_h_annual_budget_blocks_next_automatic_generation(): void
    {
        $connection = $this->seedManagedConnection([
            'existing_token' => 'expired-token',
            'token_expires_at' => '2000-01-01T00:00:00Z',
            'auto_renew' => '1',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ], [
            'token_issuance' => [
                'automatic_generation_count' => 1,
                'rolling_period_started_at' => now()->subDays(10)->toIso8601String(),
                'next_automatic_generation_eligible_at' => now()->addDays(355)->toIso8601String(),
                'generation_state' => 'idle',
            ],
        ]);

        Http::fake([
            'alhaider.test/api/login' => Http::response(['token' => 'should-not-issue'], 200),
        ]);

        try {
            app(AlHaiderManagedTokenRenewalService::class)->renewIfExpired($connection);
            $this->fail('Expected budget block');
        } catch (AlHaiderProviderException $e) {
            $this->assertSame('supplier_auth_token_budget', $e->errorCode);
        }

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
    }

    public function test_db_managed_token_is_configured_without_env_secrets(): void
    {
        Config::set('suppliers.al_haider.token', '');
        Config::set('suppliers.al_haider.username', '');
        Config::set('suppliers.al_haider.password', '');

        $this->seedManagedConnection([
            'existing_token' => 'db-only-token',
            'token_expires_at' => now()->addYear()->toIso8601String(),
            'auto_renew' => '0',
        ]);

        $this->assertTrue(app(AlHaiderClient::class)->isConfigured());
    }

    public function test_manual_to_managed_preserves_token(): void
    {
        $existing = new SupplierConnection([
            'provider' => SupplierProvider::AlHaider,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'owner-current-token',
                'token_expires_at' => '2099-01-01',
            ],
        ]);

        $payload = AlHaiderSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::AlHaider->value,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANAGED,
                'auto_renew' => '1',
                'username' => 'renew@example.test',
                'password' => 'renew-secret',
            ],
        ], $existing);

        $this->assertSame('owner-current-token', $payload['credentials']['existing_token']);
        $this->assertSame('2099-01-01', $payload['credentials']['token_expires_at']);
        $this->assertSame('managed_token', $payload['credentials']['auth_mode']);
    }

    public function test_cache_clear_does_not_reset_issuance_budget(): void
    {
        $connection = $this->seedManagedConnection([
            'existing_token' => 'tok',
            'token_expires_at' => now()->addYear()->toIso8601String(),
            'auto_renew' => '1',
            'username' => 'renew@example.test',
            'password' => 'renew-secret',
        ], [
            'token_issuance' => [
                'automatic_generation_count' => 1,
                'rolling_period_started_at' => now()->subDays(30)->toIso8601String(),
                'generation_state' => 'idle',
            ],
        ]);

        Cache::flush();
        $connection->refresh();
        $this->assertSame(1, (int) data_get($connection->settings, 'token_issuance.automatic_generation_count'));
        $this->assertFalse(
            app(AlHaiderManagedTokenRenewalService::class)->automaticBudgetAvailable(
                app(AlHaiderManagedTokenRenewalService::class)->issuanceMeta($connection)
            )
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    private function seedManagedConnection(array $credentials, array $settings = []): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();

        return SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::AlHaider,
            'name' => 'Al-Haider Managed',
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://alhaider.test',
            'credentials' => array_merge([
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANAGED,
            ], $credentials),
            'settings' => $settings,
        ]);
    }
}
