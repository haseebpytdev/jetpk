<?php

namespace Tests\Unit\Suppliers\AlHaider;

use App\Services\Suppliers\AlHaider\AlHaiderClient;
use App\Services\Suppliers\AlHaider\AlHaiderProviderException;
use App\Services\Suppliers\AlHaider\AlHaiderTokenRecord;
use App\Services\Suppliers\AlHaider\AlHaiderTokenStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlHaiderClientAuthTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_USERNAME = 'alhaider-audit-user@example.test';

    private const TEST_PASSWORD = 'alhaider-audit-password-value';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->clearDurableTokenFile();

        Config::set('suppliers.al_haider.enabled', true);
        Config::set('suppliers.al_haider.token', '');
        Config::set('suppliers.al_haider.username', self::TEST_USERNAME);
        Config::set('suppliers.al_haider.password', self::TEST_PASSWORD);
        Config::set('suppliers.al_haider.default_base_url', 'https://alhaider.test');
        Config::set('suppliers.al_haider.login_path', '/api/login');
        Config::set('suppliers.al_haider.login_lock_seconds', 5);
        Config::set('suppliers.al_haider.login_lock_wait_seconds', 5);
        Config::set('suppliers.al_haider.token_limit_block_seconds', 300);
        Config::set('suppliers.al_haider.token_generation_enabled', true);
        Config::set('suppliers.al_haider.token_validity_seconds', 31_536_000);
        Config::set('suppliers.al_haider.token_expiry_margin_seconds', 86_400);
        Config::set('suppliers.al_haider.token_performance_cache_max_seconds', 3600);
    }

    protected function tearDown(): void
    {
        $this->clearDurableTokenFile();
        parent::tearDown();
    }

    public function test_token_cache_hit_avoids_login_call(): void
    {
        Cache::put(AlHaiderClient::TOKEN_CACHE_KEY, 'cached-bearer-token', 600);

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        app(AlHaiderClient::class)->listGroups();

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
    }

    public function test_cache_miss_loads_valid_durable_token_without_login(): void
    {
        $store = app(AlHaiderTokenStore::class);
        $store->save(new AlHaiderTokenRecord(
            token: 'durable-bearer-token',
            issuedAt: time(),
            expiresAt: time() + 31_536_000,
            source: 'manual',
        ));

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        app(AlHaiderClient::class)->listGroups();

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
        $this->assertSame('durable-bearer-token', Cache::get(AlHaiderClient::TOKEN_CACHE_KEY));
    }

    public function test_generation_disabled_reuses_valid_persisted_token_without_login(): void
    {
        Config::set('suppliers.al_haider.token_generation_enabled', false);

        $store = app(AlHaiderTokenStore::class);
        $store->save(new AlHaiderTokenRecord(
            token: 'persisted-valid-token',
            issuedAt: time(),
            expiresAt: time() + 31_536_000,
            source: 'manual',
        ));
        Cache::forget(AlHaiderClient::TOKEN_CACHE_KEY);

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        app(AlHaiderClient::class)->listGroups();

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
        $this->assertSame('persisted-valid-token', Cache::get(AlHaiderClient::TOKEN_CACHE_KEY));
        $this->assertTrue($store->hasValidToken());
    }

    public function test_generation_disabled_without_token_does_not_call_login(): void
    {
        Config::set('suppliers.al_haider.token_generation_enabled', false);

        Http::fake();

        $this->expectException(AlHaiderProviderException::class);
        $this->expectExceptionMessage('Authentication required');

        try {
            app(AlHaiderClient::class)->listGroups();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_probe_without_token_and_generation_disabled_reports_missing_token(): void
    {
        Config::set('suppliers.al_haider.token_generation_enabled', false);

        Http::fake();

        $result = app(AlHaiderClient::class)->probeAuthentication();

        $this->assertSame('supplier_auth_token_missing', $result['reason_code']);
        $this->assertFalse($result['token_obtained']);
        Http::assertNothingSent();
    }

    public function test_token_limit_response_maps_to_supplier_auth_token_limit_without_json_retry(): void
    {
        Http::fake([
            'alhaider.test/api/login' => Http::response([
                'message' => 'You have reached the maximum of 10 active tokens. Please revoke an existing token before creating a new one.',
            ], 422),
        ]);

        $result = app(AlHaiderClient::class)->probeAuthentication();

        $this->assertSame('supplier_auth_token_limit', $result['reason_code']);
        $this->assertFalse($result['token_obtained']);
        Http::assertSentCount(1);
        $this->assertTrue(app(AlHaiderClient::class)->isTokenLimitBlocked());
    }

    public function test_401_with_generation_enabled_refreshes_once_and_persists_replacement(): void
    {
        $store = app(AlHaiderTokenStore::class);
        $store->save(new AlHaiderTokenRecord(
            token: 'stale-token',
            issuedAt: time(),
            expiresAt: time() + 31_536_000,
        ));
        Cache::put(AlHaiderClient::TOKEN_CACHE_KEY, 'stale-token', 600);

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::sequence()
                ->push(['message' => 'Unauthenticated.'], 401)
                ->push(['groups' => [['id' => 1]]], 200),
            'alhaider.test/api/login' => Http::response([
                'token' => 'fresh-token-value',
                'expires_in' => 31_536_000,
            ], 200),
        ]);

        $result = app(AlHaiderClient::class)->listGroups();

        $this->assertArrayHasKey('groups', $result);
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'), 1);

        $persisted = $store->load();
        $this->assertNotNull($persisted);
        $this->assertSame('fresh-token-value', $persisted->token);
    }

    public function test_401_with_generation_disabled_does_not_call_login(): void
    {
        Config::set('suppliers.al_haider.token_generation_enabled', false);

        $store = app(AlHaiderTokenStore::class);
        $store->save(new AlHaiderTokenRecord(
            token: 'stale-token',
            issuedAt: time(),
            expiresAt: time() + 31_536_000,
        ));
        Cache::put(AlHaiderClient::TOKEN_CACHE_KEY, 'stale-token', 600);

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        $this->expectException(AlHaiderProviderException::class);

        try {
            app(AlHaiderClient::class)->listGroups();
        } finally {
            Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
        }
    }

    public function test_login_uses_cache_lock_and_reuses_token_for_concurrent_miss(): void
    {
        Http::fake([
            'alhaider.test/api/login' => Http::response(['token' => 'single-login-token'], 200),
            'alhaider.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        $client = app(AlHaiderClient::class);
        $client->clearTokenCache();
        $client->listGroups();
        $client->listGroups();

        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'), 1);
        $this->assertTrue(app(AlHaiderTokenStore::class)->hasValidToken());
    }

    public function test_failed_login_does_not_overwrite_existing_durable_token(): void
    {
        $store = app(AlHaiderTokenStore::class);
        $store->save(new AlHaiderTokenRecord(
            token: 'existing-valid-token',
            issuedAt: time() - 3600,
            expiresAt: time() + 31_536_000,
        ));
        Cache::forget(AlHaiderClient::TOKEN_CACHE_KEY);

        Http::fake([
            'alhaider.test/api/login' => Http::response(['message' => 'Invalid credentials'], 401),
        ]);

        $this->expectException(AlHaiderProviderException::class);

        try {
            app(AlHaiderClient::class)->listGroups();
        } finally {
            $persisted = $store->load();
            $this->assertNotNull($persisted);
            $this->assertSame('existing-valid-token', $persisted->token);
        }
    }

    private function clearDurableTokenFile(): void
    {
        $path = app(AlHaiderTokenStore::class)->absolutePath();
        if (is_file($path)) {
            File::delete($path);
        }
    }
}
