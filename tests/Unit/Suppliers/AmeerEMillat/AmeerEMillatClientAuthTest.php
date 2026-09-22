<?php

namespace Tests\Unit\Suppliers\AmeerEMillat;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AmeerEMillat\AmeerEMillatClient;
use App\Services\Suppliers\AmeerEMillat\AmeerEMillatProviderException;
use App\Support\Suppliers\AmeerEMillatSupplierConnectionNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmeerEMillatClientAuthTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_EMAIL = 'ameer-audit-user@example.test';

    private const TEST_PASSWORD = 'ameer-audit-password-value';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('suppliers.ameer_e_millat.enabled', true);
        Config::set('suppliers.ameer_e_millat.token', '');
        Config::set('suppliers.ameer_e_millat.email', self::TEST_EMAIL);
        Config::set('suppliers.ameer_e_millat.password', self::TEST_PASSWORD);
        Config::set('suppliers.ameer_e_millat.default_base_url', 'https://ameer.test');
        Config::set('suppliers.ameer_e_millat.login_path', '/api/login');
        Config::set('suppliers.ameer_e_millat.profile_path', '/api/user');
        Config::set('suppliers.ameer_e_millat.groups_path', '/api/available/groups');
        Config::set('suppliers.ameer_e_millat.login_lock_seconds', 5);
        Config::set('suppliers.ameer_e_millat.login_lock_wait_seconds', 5);
    }

    public function test_token_cache_hit_avoids_login_call(): void
    {
        Cache::put(AmeerEMillatClient::TOKEN_CACHE_KEY, 'cached-bearer-token', 600);

        Http::fake([
            'ameer.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        app(AmeerEMillatClient::class)->listGroups();

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
    }

    public function test_probe_user_profile_returns_ok_when_profile_succeeds(): void
    {
        Cache::put(AmeerEMillatClient::TOKEN_CACHE_KEY, 'cached-bearer-token', 600);

        Http::fake([
            'ameer.test/api/user' => Http::response(['id' => 1, 'email' => self::TEST_EMAIL], 200),
        ]);

        $result = app(AmeerEMillatClient::class)->probeUserProfile();

        $this->assertTrue($result['profile_ok']);
        $this->assertSame('ok', $result['reason_code']);
    }

    public function test_401_clears_token_and_retries_once_only(): void
    {
        Cache::put(AmeerEMillatClient::TOKEN_CACHE_KEY, 'stale-token', 600);

        Http::fake([
            'ameer.test/api/available/groups*' => Http::sequence()
                ->push(['message' => 'Unauthenticated.'], 401)
                ->push(['groups' => [['id' => 1]]], 200),
            'ameer.test/api/login' => Http::response(['token' => 'fresh-token-value'], 200),
        ]);

        $result = app(AmeerEMillatClient::class)->listGroups();

        $this->assertArrayHasKey('groups', $result);
        Http::assertSentCount(3);
    }

    public function test_manual_token_connection_is_used_without_login(): void
    {
        $this->seedConnectionToken('connection-manual-token');

        Http::fake([
            'ameer.test/api/available/groups*' => function ($request) {
                $this->assertSame('Bearer connection-manual-token', $request->header('Authorization')[0] ?? '');

                return Http::response(['groups' => []], 200);
            },
        ]);

        app(AmeerEMillatClient::class)->listGroups();

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
    }

    public function test_manual_token_401_does_not_retry_login(): void
    {
        $this->seedConnectionToken('rejected-manual-token');

        Http::fake([
            'ameer.test/api/available/groups*' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        $this->expectException(AmeerEMillatProviderException::class);
        $this->expectExceptionMessage('Token expired / rejected');

        try {
            app(AmeerEMillatClient::class)->listGroups();
        } finally {
            Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
        }
    }

    private function seedConnectionToken(string $token): void
    {
        $agency = Agency::factory()->create();
        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::AmeerEMillat,
            'name' => 'Ameer manual token',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'auth_mode' => AmeerEMillatSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => $token,
            ],
        ]);
    }
}
