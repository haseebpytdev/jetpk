<?php

namespace Tests\Unit\Suppliers\AlHaider;

use App\Services\Suppliers\AlHaider\AlHaiderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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

        Config::set('suppliers.al_haider.enabled', true);
        Config::set('suppliers.al_haider.token', '');
        Config::set('suppliers.al_haider.username', self::TEST_USERNAME);
        Config::set('suppliers.al_haider.password', self::TEST_PASSWORD);
        Config::set('suppliers.al_haider.default_base_url', 'https://alhaider.test');
        Config::set('suppliers.al_haider.login_path', '/api/login');
        Config::set('suppliers.al_haider.login_lock_seconds', 5);
        Config::set('suppliers.al_haider.login_lock_wait_seconds', 5);
        Config::set('suppliers.al_haider.token_limit_block_seconds', 300);
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

    public function test_401_clears_token_and_retries_once_only(): void
    {
        Cache::put(AlHaiderClient::TOKEN_CACHE_KEY, 'stale-token', 600);

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::sequence()
                ->push(['message' => 'Unauthenticated.'], 401)
                ->push(['groups' => [['id' => 1]]], 200),
            'alhaider.test/api/login' => Http::response(['token' => 'fresh-token-value'], 200),
        ]);

        $result = app(AlHaiderClient::class)->listGroups();

        $this->assertArrayHasKey('groups', $result);
        Http::assertSentCount(3);
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
    }
}
